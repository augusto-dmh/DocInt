<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view documents');
    }

    public function view(User $user, Document $document): bool
    {
        return $user->can('view documents');
    }

    public function create(User $user): bool
    {
        return $user->can('create documents');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->can('edit documents');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('delete documents');
    }

    public function approve(User $user, Document $document): bool
    {
        return $user->can('approve documents');
    }
}
