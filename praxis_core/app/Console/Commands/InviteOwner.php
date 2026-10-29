<?php

namespace App\Console\Commands;

use App\Mail\UserInvitation;
use App\Models\User;
use App\Support\Access;
use App\Support\MailLocale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Creates the owner's staff account (Owner role) and emails a one-time setup link to
 * hq.jbspraxis.com/setup/…, where the owner chooses their own password. Nobody else
 * ever sees or sets it. Used once after the database is rebuilt (S5/S6); later staff
 * are invited from HQ → System & Access → Accounts.
 */
class InviteOwner extends Command
{
    protected $signature = 'app:invite-owner
        {email : The owner\'s email address}
        {--first-name=JBS Praxis : First name shown in HQ}
        {--last-name=Owner : Last name shown in HQ}
        {--force : Invite even though an active owner already exists}
        {--show-link : Also print the setup link (only if the email cannot be delivered)}';

    protected $description = 'Create the owner account and email a one-time setup link (the owner chooses the password)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not a valid email address: {$email}");
            return self::FAILURE;
        }

        $ownerRoleId = DB::table('roles')->where('audience', 'staff')->where('is_protected', 1)->whereNull('deleted_at')->value('id');
        if (! $ownerRoleId) {
            $this->error('There is no Owner role yet. Run php artisan db:seed (or access:sync) first.');
            return self::FAILURE;
        }

        $activeOwners = DB::table('users')->join('staff_roles', 'staff_roles.user_id', '=', 'users.id')
            ->where('staff_roles.role_id', $ownerRoleId)->where('users.status', 'Active')->where('users.lifecycle_stage', 'Active')
            ->where('users.email', '!=', $email)->count();
        if ($activeOwners > 0 && ! $this->option('force')) {
            $this->error('An owner has already set up their account. Invite people from HQ → System & Access → Accounts, or use --force.');
            return self::FAILURE;
        }

        $existing = DB::table('users')->where('email', $email)->first();
        if ($existing && $existing->lifecycle_stage === 'Active' && $existing->password !== '') {
            DB::table('staff_roles')->insertOrIgnore(['user_id' => $existing->id, 'role_id' => $ownerRoleId, 'created_at' => now()]);
            Access::flush();
            $this->info("{$email} already has an account; it now holds the Owner role. No email sent.");
            return self::SUCCESS;
        }

        $token = Str::random(60);
        $firstName = $this->option('first-name');

        DB::transaction(function () use ($existing, $email, $token, $firstName, $ownerRoleId, &$userId) {
            if ($existing) {
                // Not set up yet: a fresh link replaces any earlier one
                DB::table('users')->where('id', $existing->id)->update([
                    'invitation_token' => $token, 'lifecycle_stage' => 'Onboarding', 'status' => 'Active', 'updated_at' => now(),
                ]);
                $userId = $existing->id;
            } else {
                $userId = (string) Str::uuid();
                DB::table('users')->insert([
                    'id' => $userId,
                    'organization_id' => $this->internalOrganizationId(),
                    'first_name' => $firstName,
                    'last_name' => $this->option('last-name'),
                    'email' => $email,
                    'password' => '', // set by the owner on the setup page; an empty hash never matches
                    'invitation_token' => $token,
                    'lifecycle_stage' => 'Onboarding',
                    'status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('staff_roles')->insertOrIgnore(['user_id' => $userId, 'role_id' => $ownerRoleId, 'created_at' => now()]);
        });
        Access::flush();

        $invitation = new UserInvitation($firstName, $token, false);
        try {
            Mail::to($email)->locale(MailLocale::forRecipient(null))->send($invitation);
        } catch (\Throwable $e) {
            $this->error('The account is ready, but the email could not be sent: ' . $e->getMessage());
            $this->line('Fix the MAIL_* settings and run this command again (it sends a fresh link), or use --show-link.');
            if ($this->option('show-link')) {
                $this->line('Setup link (one use): ' . $invitation->setupUrl);
            }
            return self::FAILURE;
        }

        $this->info("Owner invitation sent to {$email}. The link opens " . parse_url($invitation->setupUrl, PHP_URL_HOST) . '/setup/… and works once.');
        if ($this->option('show-link')) {
            $this->line('Setup link (one use): ' . $invitation->setupUrl);
        }

        return self::SUCCESS;
    }

    private function internalOrganizationId(): string
    {
        // JBS Praxis itself: the Editorial account's organisation (CoreSeeder), else the internal organisation
        return DB::table('users')->where('id', User::EDITORIAL_ID)->value('organization_id')
            ?? DB::table('organizations')->where('status', 'Internal')->where('name', 'JBS Praxis')->value('id');
    }
}
