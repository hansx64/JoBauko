<?php

namespace App\Policies;

use App\Models\Resume;
use App\Models\User;

class ResumePolicy
{
    /**
     * Viewing or downloading the file. Organization access to applicants'
     * resumes is added with application management.
     */
    public function view(User $user, Resume $resume): bool
    {
        return (int) $resume->user_id === (int) $user->id;
    }

    public function update(User $user, Resume $resume): bool
    {
        return (int) $resume->user_id === (int) $user->id;
    }

    public function delete(User $user, Resume $resume): bool
    {
        return (int) $resume->user_id === (int) $user->id;
    }
}
