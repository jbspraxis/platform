<?php

namespace App\Support\Clients;

use App\Models\User;

/**
 * Who may do what on HQ › Clients (docs/CLIENTS.md, owner's answers 13 Nov 2026).
 *
 *   open the list and a client     Leads access (crm.view) or Finance (finance.view)
 *   change the invoice details     Sales (crm.edit) or Finance (finance.edit): Finance makes the invoices they print on
 *   see what is owed               Finance (finance.view)
 *   invite to the Client Portal    whoever may create accounts (access.create), as on Settings and access › Accounts;
 *                                  sending an invitation again also with access.edit, as there
 *
 * Hiding a button is not the check: every action asks here again.
 */
final class ClientAccess
{
    public static function canView(?User $user): bool
    {
        return (bool) ($user?->can('crm.view') || $user?->can('finance.view'));
    }

    public static function canEdit(?User $user): bool
    {
        return (bool) ($user?->can('crm.edit') || $user?->can('finance.edit'));
    }

    public static function canSeeMoney(?User $user): bool
    {
        return (bool) $user?->can('finance.view');
    }

    public static function canInvite(?User $user): bool
    {
        return (bool) $user?->can('access.create');
    }

    public static function canResend(?User $user): bool
    {
        return (bool) ($user?->can('access.create') || $user?->can('access.edit'));
    }
}
