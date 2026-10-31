<?php

namespace App\Services\Opportunities;

use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Recommendation\MatchRefresher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creating and editing opportunities and moving them through their
 * lifecycle. All status rules live here so they are enforced the same way
 * from every screen.
 */
class OpportunityService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create the opportunity as a draft. Publishing is a separate step so a
     * failed publish never loses the entered data.
     *
     * @param  array<string, mixed>  $data  validated OpportunityRequest data
     */
    public function create(Organization $organization, User $author, array $data): Opportunity
    {
        return DB::transaction(function () use ($organization, $author, $data) {
            $opportunity = new Opportunity($this->attributes($data));
            $opportunity->category = OpportunityCategory::from($data['category']);
            $opportunity->organization_id = $organization->id;
            $opportunity->created_by = $author->id;
            $opportunity->slug = $this->uniqueSlug($data['title']);
            $opportunity->status = OpportunityStatus::Draft;
            $opportunity->save();

            $this->syncRelated($opportunity, $data);

            $this->audit->log('opportunity.created', $opportunity, new: ['title' => $opportunity->title, 'category' => $opportunity->category->value], organizationId: $organization->id);

            return $opportunity;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated OpportunityRequest data
     */
    public function update(Opportunity $opportunity, array $data): Opportunity
    {
        $this->ensureEditable($opportunity);

        return DB::transaction(function () use ($opportunity, $data) {
            $opportunity->fill($this->attributes($data));

            // The category decides which details exist, so it can only
            // change before the opportunity has ever been published.
            if (isset($data['category']) && $data['category'] !== $opportunity->category->value) {
                if ($opportunity->status !== OpportunityStatus::Draft || $opportunity->published_at !== null) {
                    throw ValidationException::withMessages(['category' => 'The category of a published opportunity cannot be changed.']);
                }
                $opportunity->category = OpportunityCategory::from($data['category']);
            }

            $opportunity->save();
            $this->audit->logChanges('opportunity.updated', $opportunity, $opportunity->organization_id);

            $this->syncRelated($opportunity, $data);

            MatchRefresher::invalidateAll();

            return $opportunity;
        });
    }

    public function publish(Opportunity $opportunity): void
    {
        $this->ensureStatus($opportunity, [OpportunityStatus::Draft], 'publish');

        if (! $opportunity->organization->isVerified()) {
            throw ValidationException::withMessages(['status' => 'Your organization must be verified before it can publish opportunities.']);
        }

        if ($problems = $this->publishProblems($opportunity)) {
            throw ValidationException::withMessages(['status' => $problems]);
        }

        $this->setStatus($opportunity, OpportunityStatus::Published, 'opportunity.published', ['published_at' => now()]);
    }

    /**
     * Take a published opportunity back to draft so it can be reworked.
     */
    public function unpublish(Opportunity $opportunity): void
    {
        $this->ensureStatus($opportunity, [OpportunityStatus::Published], 'unpublish');

        $this->setStatus($opportunity, OpportunityStatus::Draft, 'opportunity.unpublished');
    }

    /**
     * Stop accepting applications before the deadline.
     */
    public function close(Opportunity $opportunity): void
    {
        $this->ensureStatus($opportunity, [OpportunityStatus::Published], 'close');

        $this->setStatus($opportunity, OpportunityStatus::Closed, 'opportunity.closed');
    }

    /**
     * Accept applications again, with a new deadline. Covers both closed and
     * expired opportunities.
     */
    public function reopen(Opportunity $opportunity, string $deadline): void
    {
        $this->ensureStatus($opportunity, [OpportunityStatus::Closed, OpportunityStatus::Published], 'reopen');

        if ($opportunity->status === OpportunityStatus::Published && ! $opportunity->isExpired()) {
            throw ValidationException::withMessages(['status' => 'This opportunity is already accepting applications. Extend the deadline instead.']);
        }

        if (! $opportunity->organization->isVerified()) {
            throw ValidationException::withMessages(['status' => 'Your organization must be verified to reopen opportunities.']);
        }

        $this->ensureFutureDeadline($deadline, $opportunity, mustExtend: false);

        $this->setStatus($opportunity, OpportunityStatus::Published, 'opportunity.reopened', [
            'application_deadline' => $deadline,
            'reopened_at' => now(),
        ]);
    }

    public function extendDeadline(Opportunity $opportunity, string $deadline): void
    {
        $this->ensureStatus($opportunity, [OpportunityStatus::Published], 'extend the deadline of');

        if ($opportunity->isExpired()) {
            throw ValidationException::withMessages(['status' => 'This opportunity has expired. Reopen it with a new deadline instead.']);
        }

        $this->ensureFutureDeadline($deadline, $opportunity);

        $old = $opportunity->application_deadline?->toDateString();
        $opportunity->forceFill(['application_deadline' => $deadline])->save();

        $this->audit->log('opportunity.deadline_extended', $opportunity, ['application_deadline' => $old], ['application_deadline' => $deadline], $opportunity->organization_id);

        MatchRefresher::invalidateAll();
    }

    public function archive(Opportunity $opportunity): void
    {
        $this->ensureStatus($opportunity, [OpportunityStatus::Draft, OpportunityStatus::Published, OpportunityStatus::Closed], 'archive');

        $this->setStatus($opportunity, OpportunityStatus::Archived, 'opportunity.archived');
    }

    /**
     * What still needs to be filled in before the opportunity can go live.
     *
     * @return list<string>
     */
    public function publishProblems(Opportunity $opportunity): array
    {
        $problems = [];

        if (blank($opportunity->description)) {
            $problems[] = 'Add a description.';
        }

        if ($opportunity->application_deadline === null) {
            $problems[] = 'Set an application deadline.';
        } elseif ($opportunity->application_deadline->lt(today())) {
            $problems[] = 'The application deadline has already passed.';
        }

        if ($opportunity->barangay_id === null && blank($opportunity->location_text) && ! $opportunity->is_remote) {
            $problems[] = 'Set a location (a Bauko barangay, another location, or remote).';
        }

        $missingDetails = match ($opportunity->category) {
            OpportunityCategory::Job => ! $opportunity->jobDetail()->exists(),
            OpportunityCategory::Scholarship => ! $opportunity->scholarshipDetail()->exists(),
            OpportunityCategory::Livelihood => ! $opportunity->livelihoodDetail()->exists(),
        };

        if ($missingDetails) {
            $problems[] = match ($opportunity->category) {
                OpportunityCategory::Job => 'Choose the employment type.',
                OpportunityCategory::Scholarship => 'Choose the educational level the scholarship is for.',
                OpportunityCategory::Livelihood => 'Choose the type of livelihood assistance.',
            };
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = collect($data)->only((new Opportunity)->getFillable())->except('category')->all();
        $attributes['is_remote'] = (bool) ($data['is_remote'] ?? false);

        return $attributes;
    }

    /**
     * Save the category details, requirements, required documents and skills.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncRelated(Opportunity $opportunity, array $data): void
    {
        $this->syncDetails($opportunity, $data);

        if (array_key_exists('requirements', $data)) {
            $opportunity->requirements()->delete();
            foreach (array_values($data['requirements'] ?? []) as $index => $description) {
                $opportunity->requirements()->create(['description' => $description, 'sort_order' => $index]);
            }
        }

        if (array_key_exists('documents', $data)) {
            $this->syncRequiredDocuments($opportunity, $data['documents'] ?? []);
        }

        if (array_key_exists('skills', $data)) {
            $ids = collect($data['skills'] ?? [])->map(fn (string $name) => Skill::findOrCreateByName($name)->id);
            $opportunity->skills()->sync($ids->mapWithKeys(fn (int $id) => [$id => ['is_required' => true]])->all());
        }
    }

    /**
     * Keep only the details table matching the category. A details row is
     * created once its key field is chosen, so drafts can be incomplete.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncDetails(Opportunity $opportunity, array $data): void
    {
        $relations = [
            OpportunityCategory::Job->value => ['jobDetail', 'job', 'employment_type'],
            OpportunityCategory::Scholarship->value => ['scholarshipDetail', 'scholarship', 'education_level'],
            OpportunityCategory::Livelihood->value => ['livelihoodDetail', 'livelihood', 'assistance_type'],
        ];

        foreach ($relations as $category => [$relation, $key, $requiredField]) {
            if ($category !== $opportunity->category->value) {
                $opportunity->{$relation}()->delete();

                continue;
            }

            $details = $data[$key] ?? null;

            if (is_array($details) && filled($details[$requiredField] ?? null)) {
                $opportunity->{$relation}()->updateOrCreate([], $details);
            }
        }
    }

    /**
     * Update documents in place by id so existing application uploads keep
     * pointing at the right requirement.
     *
     * @param  list<array{id?: int|null, name: string, is_required?: bool}>  $documents
     */
    private function syncRequiredDocuments(Opportunity $opportunity, array $documents): void
    {
        $keep = [];

        foreach ($documents as $document) {
            $attributes = ['name' => $document['name'], 'is_required' => (bool) ($document['is_required'] ?? true)];
            $existing = isset($document['id']) ? $opportunity->requiredDocuments()->find($document['id']) : null;

            if ($existing) {
                $existing->update($attributes);
                $keep[] = $existing->id;
            } else {
                $keep[] = $opportunity->requiredDocuments()->create($attributes)->id;
            }
        }

        $opportunity->requiredDocuments()->whereNotIn('id', $keep)->delete();
    }

    private function ensureEditable(Opportunity $opportunity): void
    {
        if (in_array($opportunity->status, [OpportunityStatus::Archived, OpportunityStatus::Suspended], true)) {
            throw ValidationException::withMessages(['status' => "{$opportunity->status->label()} opportunities can't be edited."]);
        }
    }

    /**
     * @param  list<OpportunityStatus>  $allowed
     */
    private function ensureStatus(Opportunity $opportunity, array $allowed, string $verb): void
    {
        if (! in_array($opportunity->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "You can't {$verb} an opportunity that is {$opportunity->displayStatusLabel()}.",
            ]);
        }
    }

    private function ensureFutureDeadline(string $deadline, Opportunity $opportunity, bool $mustExtend = true): void
    {
        $date = CarbonImmutable::parse($deadline);

        if ($date->lt(today())) {
            throw ValidationException::withMessages(['application_deadline' => 'The deadline cannot be in the past.']);
        }

        if ($mustExtend && $opportunity->application_deadline && ! $date->gt($opportunity->application_deadline)) {
            throw ValidationException::withMessages(['application_deadline' => 'Choose a date later than the current deadline.']);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function setStatus(Opportunity $opportunity, OpportunityStatus $to, string $action, array $extra = []): void
    {
        $from = $opportunity->status;

        $opportunity->forceFill(['status' => $to, ...$extra])->save();

        MatchRefresher::invalidateAll();

        $this->audit->log(
            $action,
            $opportunity,
            ['status' => $from->value],
            ['status' => $to->value, ...array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v, $extra)],
            $opportunity->organization_id,
        );
    }

    private function uniqueSlug(string $title): string
    {
        return Str::limit(Str::slug($title) ?: 'opportunity', 200, '').'-'.Str::lower(Str::random(6));
    }
}
