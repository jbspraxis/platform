<?php

namespace App\Support;

use App\Models\User;
use App\Support\Mail\MailNav;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Facades\DB;

/**
 * HQ's navigation, decided once (docs/HQ_PHONE.md). The rail on computers, and on phones the bottom bar, the More
 * sheet and the strip of a module's pages all draw what sections() returns, so a link shows in all of them or in
 * none. Each section and page shows only when the person can open it (roles and permissions, config/access.php).
 * Hiding a link is not a permission: every route still checks access itself.
 *
 * Names follow docs/BRAND.md section 5: plain module names, sentence case. The icons are Lucide names, written as
 * each section()'s fourth argument or as barIcon: 'name'; scripts/icons.mjs reads them there, so the bundle keeps them.
 */
final class HqNav
{
    /** The bottom bar on phones: these four when the person can open them (owner, 12 Nov 2026), then More */
    public const BAR = ['workspace', 'mail', 'leads', 'projects'];

    /** A place in the bar the person cannot open goes to the next of these they can, in this order */
    public const RESERVE = ['finance', 'team', 'campaigns', 'letters', 'overview', 'marketing', 'company-profile', 'mdm', 'system'];

    /** The More sheet's groups, in order */
    public const GROUPS = ['Work', 'Company'];

    /**
     * Every section the person can open, in rail order. Each one:
     *   key, label (the rail's), short (one word, the bottom bar), tile (at most two words, More), icon, bar_icon,
     *   group (More), foot (the rail's foot), href (the first page the person can open), active (this request),
     *   count (things waiting, on the bar and the tile), count_label (the same in words, for screen readers),
     *   count_tone (teal: unread; warning: waiting for a decision),
     *   subs (its pages: label, href, active, group (a heading in the rail), suffix).
     *
     * @return list<array<string, mixed>>
     */
    public static function sections(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $on = fn (string ...$routes) => request()->routeIs(...$routes);
        $can = fn (string $permission) => $user->can($permission);
        $canAny = fn (string ...$permissions) => collect($permissions)->contains(fn ($p) => $user->can($p));
        // The first route whose permission the person holds, in the order given
        $first = fn (array $byPermission) => collect($byPermission)->first(fn ($route, $permission) => $user->can($permission));
        $page = fn (string $label, string $route, array $active = [], ?string $group = null, ?string $suffix = null) => [
            'label' => $label,
            'href' => route($route),
            'active' => $on(...($active ?: [$route])),
            'group' => $group,
            'suffix' => $suffix,
        ];
        $sections = [];

        $sections[] = self::section('workspace', 'My workspace', 'Home', 'user-round', route('hq.dash.workspace'), $on('hq.dash.workspace'), [], barIcon: 'house');

        // Mail (docs/MAIL.md): its own gate, the Mail permission and Mail open to the person
        if ($user->can('use-mail')) {
            $unread = MailNav::unread($user);
            $sections[] = self::section('mail', 'Mail', 'Mail', 'mail', route('hq.mail.inbox'), $on('hq.mail.*'), [
                $page('Inbox', 'hq.mail.inbox'),
                $page('Mailboxes', 'hq.mail.mailboxes'),
                $page('Signature', 'hq.mail.signature'),
            ], count: $unread, countLabel: $unread . ' unread');
        }

        if ($canAny('dashboard.view', 'audit.view')) {
            $sections[] = self::section('overview', 'Overview', 'Overview', 'layout-dashboard',
                route($can('dashboard.view') ? 'hq.dash.admin' : 'hq.dash.audit'), $on('hq.dash.admin', 'hq.dash.investor', 'hq.dash.audit'), array_values(array_filter([
                    $can('dashboard.view') ? $page('Company overview', 'hq.dash.admin') : null,
                    $can('dashboard.view') ? $page('Investor view', 'hq.dash.investor') : null,
                    $can('audit.view') ? $page('Audit log', 'hq.dash.audit') : null,
                ])), group: 'Company');
        }

        // docs/OPPORTUNITIES.md: one record per piece of work with a client
        $opportunities = OpportunityAccess::canOpenBoard($user);
        if ($opportunities || $can('crm.view')) {
            $sections[] = self::section('leads', 'Leads and proposals', 'Leads', 'target',
                route($opportunities ? 'hq.crm.opportunities' : 'hq.crm.leads'), $on('hq.crm.*'), array_values(array_filter([
                    $opportunities ? $page(__('opportunities.nav'), 'hq.crm.opportunities', ['hq.crm.opportunities', 'hq.crm.opportunity']) : null,
                    ...($can('crm.view') ? [
                        $page('Leads', 'hq.crm.leads', ['hq.crm.leads', 'hq.crm.leads.new', 'hq.crm.lead']),
                        $page('My follow-ups', 'hq.crm.leads.follow-ups'),
                        $page('Free audits', 'hq.crm.consult'),
                        $page('Proposals', 'hq.crm.proposal'),
                    ] : []),
                ])));
        }

        // Campaigns (docs/CAMPAIGNS.md): Broadcasts to people who know us; Outreach to researched prospects
        if ($canAny('broadcasts.view', 'outreach.view')) {
            $sections[] = self::section('campaigns', 'Campaigns', 'Campaigns', 'send',
                route($can('broadcasts.view') ? 'hq.campaigns.broadcasts' : 'hq.campaigns.outreach'), $on('hq.campaigns.*'), array_values(array_filter([
                    ...($can('broadcasts.view') ? [
                        $page('Broadcasts', 'hq.campaigns.broadcasts', ['hq.campaigns.broadcasts', 'hq.campaigns.broadcast', 'hq.campaigns.report']),
                        $page('Audiences', 'hq.campaigns.audiences'),
                    ] : []),
                    $can('outreach.view') ? $page('Outreach', 'hq.campaigns.outreach', ['hq.campaigns.outreach', 'hq.campaigns.outreach.*']) : null,
                ])));
        }

        // Documents (docs/DOCUMENTS.md): formal documents on the company letterhead, signed and issued here
        if ($can('letters.view')) {
            $sections[] = self::section('letters', 'Documents', 'Documents', 'file-signature', route('hq.letters.register'), $on('hq.letters.*'), [
                $page('Register', 'hq.letters.register', ['hq.letters.register', 'hq.letters.document']),
                $page('My signature', 'hq.letters.signature'),
            ]);
        }

        // Company profile (docs/COMPANY_PROFILE.md): the firm's own profile, in English and French, and the client
        // logo library the profile, the deck and the website all read
        if ($can('company_profile.view')) {
            $sections[] = self::section('company-profile', 'Company profile', 'Profile', 'book-open', route('hq.company-profile.editor'), $on('hq.company-profile.*'), [
                $page('Sections', 'hq.company-profile.editor'),
                $page('Versions', 'hq.company-profile.versions', ['hq.company-profile.versions', 'hq.company-profile.preview']),
                $page('Client logos', 'hq.company-profile.logos'),
                $page('Share links', 'hq.company-profile.shares'),
            ], tile: 'Company profile');
        }

        if ($canAny('projects.view', 'qa.view', 'timesheets.view', 'signoffs.view')) {
            $home = $first(['projects.view' => 'hq.ops.deployments', 'qa.view' => 'hq.ops.qa', 'timesheets.view' => 'hq.ops.timers', 'signoffs.view' => 'hq.ops.signoffs']);
            $sections[] = self::section('projects', 'Projects', 'Projects', 'folder-kanban', route($home), $on('hq.ops.*'), array_values(array_filter([
                ...($can('projects.view') ? [
                    $page('All projects', 'hq.ops.deployments', ['hq.ops.deployments', 'hq.ops.project']),
                    $page('Milestone boards', 'hq.ops.boards.directory', ['hq.ops.boards.directory', 'hq.ops.board']),
                ] : []),
                $can('qa.view') ? $page('Quality and issues', 'hq.ops.qa') : null,
                $can('timesheets.view') ? $page('Time tracking', 'hq.ops.timers') : null,
                $can('signoffs.view') ? $page('Sign-offs', 'hq.ops.signoffs') : null,
            ])));
        }

        if ($canAny('finance.view', 'payroll.view', 'expenses.view', 'expenses.create')) {
            $home = $first(['finance.view' => 'hq.finance.bi', 'payroll.view' => 'hq.finance.payroll', 'expenses.view' => 'hq.finance.manager', 'expenses.create' => 'hq.finance.request']);
            $treasury = 'Treasury and IFRS';
            $payroll = 'Payroll and tax';
            $sections[] = self::section('finance', 'Finance', 'Finance', 'landmark', route($home), $on('hq.finance.*'), array_values(array_filter([
                ...($can('finance.view') ? [
                    $page('Finance overview', 'hq.finance.bi'),
                    $page('Invoices', 'hq.finance.invoices'),
                    $page('Payment verification', 'hq.finance.escrow'),
                    $page('Project budgets', 'hq.finance.budgets', group: $treasury),
                ] : []),
                $can('expenses.create') ? $page('Cash requests', 'hq.finance.request', group: $treasury) : null,
                $can('expenses.view') ? $page('Cash request approvals', 'hq.finance.manager', group: $treasury) : null,
                ...($can('finance.view') ? [
                    $page('Chart of accounts', 'hq.finance.coa', group: $treasury),
                    $page('General ledger', 'hq.finance.gl', group: $treasury),
                ] : []),
                ...($can('payroll.view') ? [
                    $page('Tax rules', 'hq.finance.tax-config', group: $payroll),
                    $page('Variable pay', 'hq.finance.variables', group: $payroll),
                    $page('Payroll', 'hq.finance.payroll', group: $payroll),
                    $page('Payroll compliance', 'hq.finance.matrix', group: $payroll),
                    $page('Payslips', 'hq.finance.payslips', group: $payroll),
                ] : []),
            ])), group: 'Company');
        }

        if ($can('hr.view')) {
            $sections[] = self::section('team', 'Team', 'Team', 'users', route('hq.mdm.employees'), $on('hq.mdm.employees', 'hq.hr.*'), [
                $page('Staff directory', 'hq.mdm.employees'),
                $page('Time and attendance', 'hq.hr.time'),
                $page('Contracts and appraisals', 'hq.hr.compliance'),
            ], group: 'Company');
        }

        if ($canAny('marketing.view', 'website.view', 'kaizen.view')) {
            $home = $first(['marketing.view' => 'hq.marketing.campaigns', 'website.view' => 'hq.marketing.website', 'kaizen.view' => 'hq.marketing.insights']);
            // Articles sent through the website that nobody has decided on yet, for the people who decide
            try {
                $waiting = $can('kaizen.approve') ? DB::table('kaizen_articles')->where('status', 'Pending Moderation')->count() : 0;
            } catch (\Throwable) {
                $waiting = 0; // the count is a convenience: never stop a page for it
            }
            $sections[] = self::section('marketing', 'Marketing', 'Marketing', 'megaphone', route($home), $on('hq.marketing.*'), array_values(array_filter([
                // Paid ads; email campaigns are under Campaigns
                $can('marketing.view') ? $page('Ad campaigns', 'hq.marketing.campaigns') : null,
                $can('website.view') ? $page('Website settings', 'hq.marketing.website') : null,
                $can('kaizen.view') ? $page('Insights articles', 'hq.marketing.insights', suffix: $waiting > 0 ? "({$waiting} waiting)" : null) : null,
            ])), count: $waiting, countLabel: $waiting . ' ' . ($waiting === 1 ? 'Insights article' : 'Insights articles') . ' waiting for review', countTone: 'warning');
        }

        if ($can('mdm.view')) {
            $mdm = ['hq.mdm.dictionary', 'hq.mdm.copq', 'hq.mdm.tax', 'hq.mdm.remittance', 'hq.mdm.legal', 'hq.mdm.documents*'];
            $sections[] = self::section('mdm', 'Master data', 'Data', 'database', route('hq.mdm.dictionary'), $on(...$mdm), [
                $page('Financial dictionary', 'hq.mdm.dictionary'),
                $page('Cost of poor quality tables', 'hq.mdm.copq'),
                $page('Tax compliance', 'hq.mdm.tax'),
                $page('Remittance accounts', 'hq.mdm.remittance'),
                $page('Legal entities', 'hq.mdm.legal'),
                $page('Company documents', 'hq.mdm.documents', ['hq.mdm.documents*']),
            ], group: 'Company', tile: 'Master data');
        }

        if ($canAny('access.view', 'system.view', 'integrations.manage')) {
            $home = $can('access.view') ? 'hq.system.roles' : ($can('system.view') ? 'hq.system.health' : 'hq.system.integrations');
            $sections[] = self::section('system', 'Settings and access', 'Settings', 'settings', route($home), $on('hq.system.*'), array_values(array_filter([
                ...($can('access.view') ? [
                    $page('Accounts', 'hq.system.roles'),
                    $page('Roles and permissions', 'hq.system.permissions'),
                ] : []),
                $can('system.view') ? $page('System health', 'hq.system.health') : null,
                $can('integrations.manage') ? $page(__('integrations.nav'), 'hq.system.integrations') : null,
                $can('key-vault') ? $page('Key vault', 'hq.system.key-vault') : null,
            ])), group: 'Company', foot: true);
        }

        return $sections;
    }

    /**
     * The phone's bottom bar: four sections and More. The four in BAR the person can open keep their places; a
     * place they cannot open goes to the next section in RESERVE they can, so everyone has a full bar and nobody
     * sees a section they cannot open.
     *
     * @param  list<array<string, mixed>>  $sections  from sections()
     * @return list<array<string, mixed>>
     */
    public static function bar(array $sections): array
    {
        $byKey = array_column($sections, null, 'key');
        $bar = array_values(array_filter(array_map(fn ($key) => $byKey[$key] ?? null, self::BAR)));
        foreach (self::RESERVE as $key) {
            if (count($bar) >= count(self::BAR)) {
                break;
            }
            if (isset($byKey[$key])) {
                $bar[] = $byKey[$key];
            }
        }

        return $bar;
    }

    /**
     * The More sheet: every section the person can open that is not in their bar, by group, in rail order.
     *
     * @param  list<array<string, mixed>>  $sections  from sections()
     * @return array<string, list<array<string, mixed>>>  group => sections; empty groups left out
     */
    public static function more(array $sections): array
    {
        $inBar = array_column(self::bar($sections), 'key');
        $groups = array_fill_keys(self::GROUPS, []);
        foreach ($sections as $section) {
            if (! in_array($section['key'], $inBar, true)) {
                $groups[$section['group']][] = $section;
            }
        }

        return array_filter($groups);
    }

    /** The section this request is in, if any */
    public static function current(array $sections): ?array
    {
        foreach ($sections as $section) {
            if ($section['active']) {
                return $section;
            }
        }

        return null;
    }

    private static function section(
        string $key,
        string $label,
        string $short,
        string $icon,
        string $href,
        bool $active,
        array $subs,
        ?string $barIcon = null,
        string $group = 'Work',
        ?string $tile = null,
        int $count = 0,
        ?string $countLabel = null,
        string $countTone = 'teal',
        bool $foot = false,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'short' => $short,
            'tile' => $tile ?? $short,
            'icon' => $icon,
            'bar_icon' => $barIcon ?? $icon,
            'group' => $group,
            'foot' => $foot,
            'href' => $href,
            'active' => $active,
            'count' => $count,
            'count_label' => $count > 0 ? $countLabel : null,
            'count_tone' => $countTone,
            'subs' => $subs,
        ];
    }
}
