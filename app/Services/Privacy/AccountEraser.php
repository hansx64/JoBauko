<?php

namespace App\Services\Privacy;

use App\Models\User;
use App\Services\Applications\ApplicationWorkflow;
use App\Services\Resumes\ResumeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        Storage::disk(ResumeService::DISK)->delete($resumes->filter()->all());
        Storage::disk(ApplicationWorkflow::DISK)->delete($documents->filter()->all());

        foreach ($applicationIds as $id) {
            Storage::disk(ApplicationWorkflow::DISK)->deleteDirectory("applications/{$id}");
        }
    }
}
