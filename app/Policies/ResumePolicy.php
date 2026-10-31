<?php

namespace App\Policies;

use App\Models\Resume;
use App\Models\User;

class ResumePolicy
{
    /**
     * Viewing or downloading the file from the seeker's own pages.
     * Organizations get the resume submitted with an application through
     * org.applications.resume, authorized by ApplicationPolicy.
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
