<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReminderPolicy
{
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->hasRole(RoleName::Admin->value)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $authUser): bool
    {
        return $authUser->hasAnyRole([
            RoleName::Landlord->value,
            RoleName::Tenant->value,
        ]);
    }

    public function view(User $authUser, Reminder $reminder): bool
    {
        if (
            $authUser->hasRole(RoleName::Tenant->value)
            && ! $authUser->hasRole(RoleName::Landlord->value)
        ) {
            return $reminder->assigned_to_id === $authUser->id
                && $this->canViewRemindable($authUser, $reminder->remindable);
        }

        return $this->canViewRemindable($authUser, $reminder->remindable);
    }

    public function createForRemindable(User $authUser, Model $remindable): bool
    {
        return $this->canUpdateRemindable($authUser, $remindable);
    }

    public function update(User $authUser, Reminder $reminder): bool
    {
        return $this->canUpdateRemindable($authUser, $reminder->remindable);
    }

    public function delete(User $authUser, Reminder $reminder): bool
    {
        return $this->update($authUser, $reminder);
    }

    public function restore(User $authUser, Reminder $reminder): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, Reminder $reminder): bool
    {
        return false;
    }

    private function canViewRemindable(User $authUser, ?Model $remindable): bool
    {
        if (
            $remindable instanceof Document
            || $remindable instanceof RentalAgreement
            || $remindable instanceof Payment
        ) {
            return $authUser->can('view', $remindable);
        }

        return false;
    }

    private function canUpdateRemindable(User $authUser, ?Model $remindable): bool
    {
        if (
            $remindable instanceof Document
            || $remindable instanceof RentalAgreement
            || $remindable instanceof Payment
        ) {
            return $authUser->can('update', $remindable);
        }

        return false;
    }
}
