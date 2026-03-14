<?php

namespace App\Policies;

use App\Models\Matter;
use App\Models\User;

class MatterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view matters');
    }

    public function view(User $user, Matter $matter): bool
    {
        return $user->can('view matters');
    }

    public function create(User $user): bool
    {
        return $user->can('create matters');
    }

    public function update(User $user, Matter $matter): bool
    {
        return $user->can('edit matters');
    }

    public function delete(User $user, Matter $matter): bool
    {
        return $user->can('delete matters');
    }
}
