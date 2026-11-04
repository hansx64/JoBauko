<?php

namespace App\Services\Privacy;

use App\Models\User;
use App\Support\UploadPaths;
use App\Support\Uploads;
use Illuminate\Support\Facades\DB;

/**
 * Permanently erases a person's account at their request (Data Privacy Act
 * right to erasure). Database rows go through foreign-key cascades; uploaded
 * files are removed here, since the database cannot delete them. Audit log
 * entries are kept with the person's id cleared, as a record of what
 * organizations and administrators did.
 */
class AccountEraser
{
    public function erase(User $user): void
    {
        $resumes = DB::table('resumes')->where('user_id', $user->id)->pluck('disk_path');
        $applicationIds = DB::table('applications')->where('user_id', $user->id)->pluck('id');
        $documents = DB::table('application_documents')->whereIn('application_id', $applicationIds)->pluck('disk_path');

        DB::transaction(fn () => $user->forceDelete());

        $paths = $resumes->merge($documents)->filter();
        Uploads::disk()->delete($paths->all());

        // The person's own folders are named after them, so remove those too.
        $folders = $paths->map(fn (string $path) => UploadPaths::ownFolder($path))->filter()->unique();
        foreach ([...$folders, ...$applicationIds->map(fn ($id) => "applications/{$id}")] as $folder) {
            Uploads::disk()->deleteDirectory($folder);
        }
    }
}
