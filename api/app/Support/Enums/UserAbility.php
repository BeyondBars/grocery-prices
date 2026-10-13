<?php

namespace App\Support\Enums;

enum UserAbility: string
{
    case ReadUsers = 'read users';
    case WriteUsers = 'write users';
    case ManageEmailTemplates = 'manage email templates';

    /**
     * Abilities grouped for display/syncing. "excludes" lists role values that may never hold it.
     */
    public static function grouped(): array
    {
        return [
            'users' => [
                'read' => ['name' => self::ReadUsers, 'excludes' => []],
                'write' => ['name' => self::WriteUsers, 'excludes' => []],
            ],
            'email templates' => [
                'manage' => ['name' => self::ManageEmailTemplates, 'excludes' => []],
            ],
        ];
    }

    /**
     * All ability values, minus any excluded for the given role.
     */
    public static function forRole(UserRole $role): array
    {
        $abilities = [];

        foreach (static::grouped() as $group) {
            foreach ($group as $ability) {
                if (!in_array($role->value, $ability['excludes'], true)) {
                    $abilities[] = $ability['name']->value;
                }
            }
        }

        return $abilities;
    }
}
