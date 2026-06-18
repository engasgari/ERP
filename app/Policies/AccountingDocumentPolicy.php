<?php

namespace App\Policies;

use App\Models\AccountingDocument;
use App\Models\User;

class AccountingDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.view');
    }

    public function view(User $user, AccountingDocument $document): bool
    {
        return $user->hasPermission('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.documents.create') || $user->hasPermission('accounting.manage');
    }

    public function update(User $user, AccountingDocument $document): bool
    {
        return $user->hasPermission('accounting.documents.edit') || $user->hasPermission('accounting.manage');
    }

    public function delete(User $user, AccountingDocument $document): bool
    {
        return $user->hasPermission('accounting.documents.delete');
    }

    public function post(User $user, AccountingDocument $document): bool
    {
        return $user->hasPermission('accounting.documents.post');
    }
}
