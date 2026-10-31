<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\Classification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\Barangay;
use App\Services\Notifications\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/announcements/index', [
            'announcements' => Announcement::query()
                ->with('author:id,name')
                ->latest()
                ->paginate(20)
                ->through(fn ($a) => $this->listRow($a)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'channels' => $a->channels,
            'recipient_count' => $a->recipient_count,
            'author' => $a->author?->name,
            'created_at' => $a->created_at->toIso8601String(),
            'sent_at' => $a->sent_at?->toIso8601String(),
        ];
    }

    public function create(): Response
    {
        return Inertia::render('admin/announcements/form', [
            'announcement' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(AnnouncementRequest $request, AnnouncementService $announcements): RedirectResponse
    {
        $announcement = $announcements->saveDraft(null, $request->user(), $request->validated());

        return to_route('admin.announcements.show', $announcement)->with('success', 'Draft saved. Check the audience, then send it.');
    }

    public function show(Announcement $announcement): Response
    {
        $announcement->load('author:id,name');

        return Inertia::render('admin/announcements/show', [
            'announcement' => [
                ...$this->formData($announcement),
                'status' => $announcement->status->value,
                'status_label' => $announcement->status->label(),
                'author' => $announcement->author?->name,
                'recipient_count' => $announcement->recipient_count,
                'created_at' => $announcement->created_at->toIso8601String(),
                'sent_at' => $announcement->sent_at?->toIso8601String(),
            ],
            'audienceSummary' => $this->audienceSummary($announcement),
            'reach' => $announcement->isDraft() ? AnnouncementService::reach($announcement->audience, $announcement->channels) : null,
            'delivery' => $announcement->isDraft() ? null : [
                'sms' => $announcement->smsLogs()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'email' => $announcement->emailLogs()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            ],
        ]);
    }

    public function edit(Announcement $announcement): Response|RedirectResponse
    {
        if (! $announcement->isDraft()) {
            return to_route('admin.announcements.show', $announcement);
        }

        return Inertia::render('admin/announcements/form', [
            'announcement' => $this->formData($announcement),
            'options' => $this->options(),
        ]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement, AnnouncementService $announcements): RedirectResponse
    {
        $announcements->saveDraft($announcement, $request->user(), $request->validated());

        return to_route('admin.announcements.show', $announcement)->with('success', 'Draft updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        if (! $announcement->isDraft()) {
            throw ValidationException::withMessages(['announcement' => 'Sent announcements are kept as a record.']);
        }

        $announcement->delete();

        return to_route('admin.announcements.index')->with('success', 'Draft deleted.');
    }

    public function send(Announcement $announcement, AnnouncementService $announcements): RedirectResponse
    {
        $announcements->send($announcement);

        return to_route('admin.announcements.show', $announcement)->with('success', 'The announcement is being sent.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'audience' => AnnouncementService::normalizeAudience($announcement->audience),
            'channels' => array_values(array_diff($announcement->channels, ['in_app'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'accountTypes' => AccountType::options(),
            'classifications' => Classification::options(),
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return list<string>
     */
    private function audienceSummary(Announcement $announcement): array
    {
        $audience = AnnouncementService::normalizeAudience($announcement->audience);
        $lines = [];

        foreach ($audience['account_types'] as $type) {
            $label = AccountType::from($type)->label();

            if ($type === AccountType::Seeker->value) {
                $classifications = array_map(fn (string $c) => Classification::from($c)->label(), $audience['classifications']);
                $barangays = Barangay::whereIn('id', $audience['barangays'])->orderBy('name')->pluck('name')->all();
                $label .= 's'.($classifications ? ': '.implode(', ', $classifications) : ', all classifications');
                $label .= $barangays ? '; in '.implode(', ', $barangays) : '; all barangays';
            } else {
                $label .= $type === AccountType::Organization->value ? ' personnel' : 's';
            }

            $lines[] = $label;
        }

        return $lines;
    }
}
