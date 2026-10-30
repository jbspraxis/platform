<?php

/*
|--------------------------------------------------------------------------
| Roles and permissions (RBAC)
|--------------------------------------------------------------------------
| A permission is "<module>.<action>" for staff (HQ) and "portal.<module>.<action>"
| for client accounts. Roles grant permissions; there is no record scope.
| The Owner role is protected: it always has every staff permission, including
| modules added later, and cannot be edited or deleted.
|
| `php artisan access:sync` (also run by the seeders) writes this catalogue to
| the permissions table. Starter roles are created once; after that the owner
| edits them on the Roles & Permissions screen and re-seeding leaves them alone.
*/

$all = ['view', 'create', 'edit', 'delete', 'approve', 'export'];

return [

    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'approve' => 'Approve',
        'export' => 'Export',
    ],

    'modules' => [
        // HQ (staff accounts)
        'staff' => [
            'dashboard' => ['label' => 'Command overview', 'actions' => ['view', 'export']],
            'audit' => ['label' => 'Audit log', 'actions' => ['view', 'export']],
            'projects' => ['label' => 'Projects & sprints', 'actions' => $all],
            'qa' => ['label' => 'QA hub', 'actions' => ['view', 'create', 'edit', 'approve']],
            'timesheets' => ['label' => 'Time tracking', 'actions' => ['view', 'create', 'edit', 'approve', 'export']],
            'signoffs' => ['label' => 'Project sign-offs', 'actions' => ['view', 'create', 'edit', 'approve']],
            'kaizen' => ['label' => 'Kaizen articles', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
            'crm' => ['label' => 'Leads & proposals', 'actions' => $all],
            'broadcasts' => ['label' => 'Broadcasts', 'actions' => ['view', 'create', 'approve']],
            'marketing' => ['label' => 'Marketing campaigns', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
            // What jbspraxis.com shows: company details, contact, social links, leadership (HQ → Marketing → Website settings).
            // Roles that already exist get it from the migration 2026_10_30_000002_grant_website_settings_permissions.
            'website' => ['label' => 'Website settings', 'actions' => ['view', 'edit']],
            'expenses' => ['label' => 'Cash requests', 'actions' => ['view', 'create', 'approve', 'export']],
            'finance' => ['label' => 'Invoices, ledger & budgets', 'actions' => $all],
            'payroll' => ['label' => 'Payroll & tax', 'actions' => $all],
            'hr' => ['label' => 'People & HR', 'actions' => $all],
            'mdm' => ['label' => 'Master data', 'actions' => ['view', 'create', 'edit', 'delete']],
            'access' => ['label' => 'Accounts, roles & permissions', 'actions' => ['view', 'create', 'edit', 'delete']],
            'system' => ['label' => 'System health', 'actions' => ['view']],
        ],

        // Client portal (client accounts); permission names start with "portal."
        'client' => [
            'projects' => ['label' => 'Projects & progress', 'actions' => ['view', 'approve']],
            'qa' => ['label' => 'QA & messages', 'actions' => ['view', 'create']],
            'billing' => ['label' => 'Invoices & payments', 'actions' => ['view', 'create']],
            'documents' => ['label' => 'Document vault', 'actions' => ['view', 'export']],
            'team' => ['label' => 'Team', 'actions' => ['view', 'create', 'edit']],
            'settings' => ['label' => 'Branding', 'actions' => ['edit']],
            'support' => ['label' => 'Redline escalation', 'actions' => ['create']],
        ],
    ],

    /*
    | Starter roles. 'grant' lists permissions: "module.*" = every action of that
    | module, "*.view" = that action on every module, "*" = everything.
    */
    'starter_roles' => [
        'staff' => [
            'owner' => ['name' => 'Owner', 'protected' => true, 'description' => 'Full access, always. Cannot be edited or deleted.', 'grant' => ['*']],
            'partner' => ['name' => 'Partner / Director', 'description' => 'Runs the firm: every module.', 'grant' => ['*']],
            'finance' => ['name' => 'Finance & Payroll', 'description' => 'Money in, money out, payroll and tax.', 'grant' => [
                'dashboard.*', 'audit.view', 'expenses.*', 'finance.*', 'payroll.*', 'mdm.view', 'mdm.create', 'mdm.edit', 'projects.view', 'crm.view',
            ]],
            'delivery' => ['name' => 'Delivery Lead', 'description' => 'Runs projects, sprints, QA and sign-offs.', 'grant' => [
                'dashboard.view', 'projects.*', 'qa.*', 'timesheets.*', 'signoffs.*', 'kaizen.view', 'kaizen.create', 'kaizen.edit', 'crm.view', 'expenses.create',
            ]],
            'engineer' => ['name' => 'Engineer', 'description' => 'Works on assigned tasks and QA tickets.', 'grant' => [
                'projects.view', 'projects.edit', 'qa.view', 'qa.create', 'qa.edit', 'timesheets.view', 'timesheets.create', 'kaizen.view', 'kaizen.create', 'expenses.create',
            ]],
            // marketing.delete also opens the social-account password vault, so it stays with Owner/Partner
            'sales' => ['name' => 'Sales & Marketing', 'description' => 'Leads, proposals, broadcasts, campaigns and website settings.', 'grant' => [
                'dashboard.view', 'crm.*', 'broadcasts.*', 'marketing.view', 'marketing.create', 'marketing.edit', 'marketing.approve',
                'website.*', 'kaizen.*', 'projects.view', 'expenses.create',
            ]],
            'hr' => ['name' => 'HR', 'description' => 'People records, attendance, contracts and appraisals.', 'grant' => [
                'hr.*', 'payroll.view', 'timesheets.view', 'timesheets.approve', 'timesheets.export', 'mdm.view', 'expenses.create',
            ]],
            'auditor' => ['name' => 'Auditor', 'description' => 'Reads and exports everything, changes nothing.', 'grant' => ['*.view', '*.export']],
        ],
        'client' => [
            'client-admin' => ['name' => 'Client Admin', 'description' => 'Everything in the portal, including the team.', 'grant' => ['*']],
            'client-finance' => ['name' => 'Client Finance', 'description' => 'Invoices, payments and documents.', 'grant' => [
                'projects.view', 'qa.view', 'billing.*', 'documents.*',
            ]],
            'client-member' => ['name' => 'Client Member', 'description' => 'Follows projects, raises QA tickets and messages.', 'grant' => [
                'projects.view', 'qa.*', 'documents.view', 'support.create',
            ]],
        ],
    ],

    // The role a client organisation's primary account always has
    'client_admin_role' => 'client-admin',
];
