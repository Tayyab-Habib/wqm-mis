<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

/**
 * All user roles as defined in the `roles` table.
 *
 * Values match the `name` column exactly (used by Spatie Permission).
 * IDs are provided as comments for reference only — do NOT use IDs in code,
 * always match by name for portability across environments.
 */
enum UserRoleEnum: string
{
    use ArrayableEnum;

    case SYSTEM_ADMINISTRATOR = 'system-administrator'; // id: 1
    case SYSTEM_MANAGER       = 'system-manager';       // id: 2
    case JUNIOR_CLERK         = 'junior-clerk';          // id: 3
    case LABORATORY_ASSISTANT = 'laboratory-assistant';  // id: 4
    case QA                   = 'QA';                   // id: 5
    case TEST                 = 'test';                  // id: 6
    case DIRECTOR_LABS        = 'Director Labs';         // id: 7
    case DIRECTOR_TECHNICAL   = 'Director Technical';   // id: 8
    case VIEWER               = 'Viewer';               // id: 9
    case SECRETARY            = 'secretary';             // id: 10
    case CE                   = 'ce';                   // id: 11
    case SE                   = 'se';                   // id: 12
    case XEN                  = 'xen';                  // id: 13

    /**
     * Returns the role names for all XEN-tier officers who receive
     * SLA notifications and access the XEN Dashboard.
     *
     * @return string[]
     */
    public static function xenTierRoles(): array
    {
        return [
            self::XEN->value,
            self::CE->value,
            self::SE->value,
            self::SECRETARY->value,
        ];
    }

    /**
     * Returns the role names that have full lab/system access.
     *
     * @return string[]
     */
    public static function adminRoles(): array
    {
        return [
            self::SYSTEM_ADMINISTRATOR->value,
            self::SYSTEM_MANAGER->value,
        ];
    }

    /**
     * Returns the role names for lab staff who create/analyze samples.
     *
     * @return string[]
     */
    public static function labRoles(): array
    {
        return [
            self::LABORATORY_ASSISTANT->value,
            self::JUNIOR_CLERK->value,
            self::QA->value,
        ];
    }
}
