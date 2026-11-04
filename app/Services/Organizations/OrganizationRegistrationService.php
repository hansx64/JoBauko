<?php

namespace App\Services\Organizations;

use App\Enums\AccountType;
use App\Enums\ConsentType;
use App\Enums\OrganizationRole;
use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\ConsentRecorder;
use App\Support\UploadPaths;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OrganizationRegistrationService
{
    public function __construct(
        private readonly ConsentRecorder $consents,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create the organization (pending verification), its first administrator
     * account and its verification documents.
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $documents
     */
    public function register(array $data, ?UploadedFile $logo, array $documents, Request $request): User
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($data, $logo, $documents, $request, &$stored) {
                $user = new User([
                    'name' => $data['representative_name'],
                    'email' => $data['email'],
                    'mobile_number' => $data['mobile_number'] ?? null,
                    'password' => $data['password'],
                ]);
                $user->account_type = AccountType::Organization;
                $user->save();

                $organization = new Organization(collect($data)->only([
                    'name', 'type', 'official_email', 'contact_number', 'office_address', 'barangay_id',
                    'description', 'representative_name', 'representative_position',
                ])->all());
                $organization->slug = $this->uniqueSlug($data['name']);
                $organization->verification_status = VerificationStatus::Pending;

                $organization->save();

                // After saving: the logo's folder is named with the organization's ID.
                if ($logo) {
                    $organization->logo_path = $stored[] = $this->storeLogo($organization, $logo);
                    $organization->save();
                }

                $organization->members()->create([
                    'user_id' => $user->id,
                    'role' => OrganizationRole::Admin,
                    'is_active' => true,
                ]);

                foreach ($documents as $file) {
                    $this->storeDocument($organization, $file, 'verification', $stored);
                }

                $this->consents->grant($user, ConsentType::PrivacyNotice, $request);

                $this->audit->log('organization.registered', $organization, new: ['name' => $organization->name], organizationId: $organization->id);

                // Reload so database defaults (e.g. is_active) are present.
                return $user->refresh();
            });
        } catch (Throwable $e) {
            // Don't leave orphaned files when the database work fails.
            foreach ($stored as $path) {
                Uploads::disk()->delete($path);
            }

            throw $e;
        }
    }

    /**
     * @param  list<string>  $stored  collects stored paths for cleanup
     */
    public function storeDocument(Organization $organization, UploadedFile $file, string $type, array &$stored = []): void
    {
        $path = $file->storeAs(
            UploadPaths::organizationDocuments($organization),
            UploadPaths::fileName($file, $file->guessExtension() ?: $file->getClientOriginalExtension()),
            Uploads::name(),
        );
        $stored[] = $path;

        $organization->documents()->create([
            'document_type' => $type,
            'disk_path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    public function storeLogo(Organization $organization, UploadedFile $logo): string
    {
        return $logo->storeAs(
            UploadPaths::organizationLogos($organization),
            UploadPaths::fileName('Logo', $logo->guessExtension() ?: $logo->getClientOriginalExtension()),
            Uploads::name(),
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
