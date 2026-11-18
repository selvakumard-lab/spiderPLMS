<?php

/*
|--------------------------------------------------------------------------
| Spider PLMS configuration
|--------------------------------------------------------------------------
|
| Platform-level settings and the role → permission matrix. The matrix is
| the single source of truth for RBAC; run `php artisan db:seed
| --class=RolePermissionSeeder` after changing it.
|
*/

return [

    'central_domain' => env('PLMS_CENTRAL_DOMAIN', 'localhost'),

    'otp_ttl_minutes' => 10,

    /*
     * TEMPORARY: when set, every sign-in accepts this fixed 6-digit code and no email is sent.
     * Leave empty (the default) to send real one-time codes. Never set this in production.
     */
    'static_otp' => env('PLMS_STATIC_OTP'),

    /*
     * Workspace slugs that cannot be registered (they collide with platform sub-domains).
     */
    'reserved_slugs' => ['login', 'register', 'sa', 'superadmin', 'superAdmin', 'logout', 'public', 'storage', 'build', 'pricing', 'www', 'app', 'api', 'admin', 'mail', 'docs', 'status', 'billing', 'support', 'help'],

    /*
     * Every permission in the system, grouped by module.
     */
    'permissions' => [
        'dashboard' => ['dashboard.view'],
        'projects' => ['projects.view', 'projects.view-all', 'projects.create', 'projects.update', 'projects.delete', 'projects.change-stage', 'projects.approve'],
        'requirements' => ['requirements.view', 'requirements.manage', 'requirements.approve'],
        'tasks' => ['tasks.view', 'tasks.create', 'tasks.update', 'tasks.update-own', 'tasks.delete', 'tasks.assign'],
        'team' => ['team.view', 'team.invite', 'team.manage', 'team.allocate'],
        'time' => ['time.log', 'time.view-all', 'time.approve'],
        'documents' => ['documents.view', 'documents.upload', 'documents.delete'],
        'discussions' => ['discussions.view', 'discussions.post', 'discussions.internal'],
        'reports' => ['reports.view', 'reports.export'],
        'invoices' => ['invoices.view', 'invoices.manage', 'payments.record'],
        'clients' => ['clients.view', 'clients.manage'],
        'settings' => ['settings.manage', 'billing.manage', 'roles.manage'],
    ],

    /*
     * Default permissions per tenant role. "*" grants every permission.
     */
    'roles' => [
        'org-admin' => ['*'],

        'project-manager' => [
            'dashboard.view',
            'projects.view', 'projects.view-all', 'projects.create', 'projects.update', 'projects.change-stage', 'projects.approve',
            'requirements.view', 'requirements.manage', 'requirements.approve',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign',
            'team.view', 'team.allocate',
            'time.log', 'time.view-all', 'time.approve',
            'documents.view', 'documents.upload', 'documents.delete',
            'discussions.view', 'discussions.post', 'discussions.internal',
            'reports.view', 'reports.export',
            'invoices.view', 'invoices.manage',
            'clients.view', 'clients.manage',
        ],

        'team-leader' => [
            'dashboard.view',
            'projects.view',
            'requirements.view', 'requirements.manage',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
            'team.view',
            'time.log', 'time.view-all', 'time.approve',
            'documents.view', 'documents.upload',
            'discussions.view', 'discussions.post', 'discussions.internal',
            'reports.view',
        ],

        'developer' => [
            'dashboard.view',
            'projects.view',
            'requirements.view',
            'tasks.view', 'tasks.create', 'tasks.update-own',
            'team.view',
            'time.log',
            'documents.view', 'documents.upload',
            'discussions.view', 'discussions.post', 'discussions.internal',
        ],

        'client' => [
            'dashboard.view',
            'projects.view',
            'requirements.view', 'requirements.approve',
            'tasks.view',
            'documents.view', 'documents.upload',
            'discussions.view', 'discussions.post',
            'invoices.view',
        ],
    ],
];
