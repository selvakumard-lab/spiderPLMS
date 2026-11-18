<?php

namespace App\Enums;

/**
 * Tenant-level roles. "Super Admin" is a platform flag on users (is_super_admin),
 * not a tenant role, because it spans every tenant.
 */
enum Role: string
{
    case OrgAdmin = 'org-admin';
    case ProjectManager = 'project-manager';
    case TeamLeader = 'team-leader';
    case Developer = 'developer';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::OrgAdmin => 'Organization Admin',
            self::ProjectManager => 'Project Manager',
            self::TeamLeader => 'Team Leader',
            self::Developer => 'Developer / Employee',
            self::Client => 'Client / Customer',
        };
    }
}
