<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Landlord = 'landlord';
    case Tenant = 'tenant';
    case User = 'user';

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
