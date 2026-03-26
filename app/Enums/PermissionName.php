<?php

namespace App\Enums;

enum PermissionName: string
{
    case UsersViewAny = 'users.viewAny';
    case UsersView = 'users.view';
    case UsersUpdate = 'users.update';
    case UsersAssignRoles = 'users.assignRoles';

    case ProfileViewOwn = 'profile.viewOwn';
    case ProfileUpdateOwn = 'profile.updateOwn';

    case TenantsViewOwn = 'tenants.viewOwn';
    case TenantsUpdateOwn = 'tenants.updateOwn';
    case LandlordsViewOwn = 'landlords.viewOwn';

    case MessagesViewOwn = 'messages.viewOwn';
    case MessagesCreateOwn = 'messages.createOwn';

    case InvoicesViewOwn = 'invoices.viewOwn';
    case DocumentsViewOwn = 'documents.viewOwn';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
