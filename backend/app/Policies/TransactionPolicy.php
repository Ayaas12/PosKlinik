<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function cancel(User $user, Transaction $transaction): bool
    {
        // Only admin can cancel transactions
        return $user->isAdmin();
    }
}
