<?php

namespace App\Support\Leads;

use App\Models\ClientUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A lead's contacts (owner's decision 3, docs/LEADS.md section 6): any number of client_users rows, each with a name,
 * job title, email, phone and deal role, one of them the main contact (is_main_contact). A contact added here is a lead
 * (lifecycle Lead, no password, no portal access); portal accounts of the same organisation show too, but only their
 * deal role and main flag change here (the portal's Team page runs the rest).
 *
 * An email belongs to one person in HQ (client_users.email is unique): adding someone whose email is already a contact
 * elsewhere is refused with where they are, so the duplicate can be opened instead.
 */
final class Contacts
{
    public const DEAL_ROLES = ['decision_maker' => 'Decision maker', 'finance' => 'Finance', 'technical' => 'Technical', 'user' => 'User'];

    /** Rules for one contact's fields (name, job_title, email, phone, deal_role), each key prefixed with $prefix */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}name" => ['required', 'string', 'max:100'],
            "{$prefix}job_title" => ['nullable', 'string', 'max:100'],
            "{$prefix}email" => ['nullable', 'email', 'max:100'],
            "{$prefix}phone" => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ().\-]{4,26}$/'],
            "{$prefix}deal_role" => ['nullable', Rule::in(array_keys(self::DEAL_ROLES))],
        ];
    }

    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}name.required" => 'Enter the contact’s name.',
            "{$prefix}name.max" => 'Keep the name under 100 characters.',
            "{$prefix}job_title.max" => 'Keep the job title under 100 characters.',
            "{$prefix}email.email" => 'Enter an email address like name@company.com.',
            "{$prefix}email.max" => 'Keep the email address under 100 characters.',
            "{$prefix}phone.regex" => 'Enter a phone number with digits only, e.g. +234 800 000 0000.',
            "{$prefix}phone.max" => 'Keep the phone number under 30 characters.',
            "{$prefix}deal_role.in" => 'Choose a role from the list.',
        ];
    }

    /** The lead's contacts, the main contact first, then in the order they were added */
    public static function of(string $organizationId): Collection
    {
        return ClientUser::query()->where('organization_id', $organizationId)
            ->orderByDesc('is_main_contact')->orderBy('created_at')->get();
    }

    /** The contact who already has this email anywhere in HQ (with their organisation's name), else null */
    public static function emailTaken(?string $email, ?string $exceptId = null): ?object
    {
        $email = mb_strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        return DB::table('client_users')->join('organizations', 'organizations.id', '=', 'client_users.organization_id')
            ->whereRaw('LOWER(client_users.email) = ?', [$email])
            ->when($exceptId, fn ($q, $id) => $q->where('client_users.id', '!=', $id))
            ->first(['client_users.id', 'client_users.organization_id', 'organizations.name as organization_name', 'client_users.deleted_at']);
    }

    /**
     * Add a contact to the lead ($data: name, job_title, email, phone, deal_role, main). The first contact of a lead,
     * or one marked main, becomes the main contact. Timeline "Contact added" unless $quiet (a lead being created
     * records one line for everything).
     *
     * @throws LeadRuleBroken when the email is someone else's already
     */
    public static function add(Organization $lead, array $data, ?User $by, string $source = 'hq', bool $quiet = false): ClientUser
    {
        $data = self::clean($data);
        if ($data['email'] && ($taken = self::emailTaken($data['email']))) {
            throw new LeadRuleBroken(self::takenMessage($taken, $lead->id), 'email');
        }
        [$first, $last] = self::splitName($data['name']);
        $hasMain = ClientUser::where('organization_id', $lead->id)->where('is_main_contact', 1)->exists();
        $main = ! $hasMain || ! empty($data['main']);

        return DB::transaction(function () use ($lead, $data, $by, $source, $quiet, $first, $last, $main) {
            if ($main) {
                ClientUser::where('organization_id', $lead->id)->where('is_main_contact', 1)->update(['is_main_contact' => 0]);
            }
            $contact = ClientUser::create([
                'organization_id' => $lead->id, 'first_name' => $first, 'last_name' => $last,
                'email' => $data['email'], 'phone' => $data['phone'], 'job_title' => $data['job_title'],
                'status' => 'Active', 'lifecycle_stage' => 'Lead', 'source' => $source, 'created_by' => $by?->id,
            ]);
            $contact->forceFill(['deal_role' => $data['deal_role'], 'is_main_contact' => $main])->save();
            if (! $quiet) {
                LeadTimeline::record($lead->id, 'contact_added', "Contact added: {$contact->full_name}" . ($data['job_title'] ? ", {$data['job_title']}" : '') . '.', [
                    'actor' => $by, 'client_user_id' => $contact->id,
                ]);
            }

            return $contact;
        });
    }

    /**
     * Change a contact's details. A portal account (it has a password) keeps its name, email and phone: only its job
     * title, deal role and main flag change here.
     *
     * @throws LeadRuleBroken
     */
    public static function update(ClientUser $contact, array $data, User $by): bool
    {
        $data = self::clean($data);
        $portal = self::isPortalAccount($contact);
        if (! $portal && $data['email'] && ($taken = self::emailTaken($data['email'], $contact->id))) {
            throw new LeadRuleBroken(self::takenMessage($taken, $contact->organization_id), 'email');
        }
        [$first, $last] = self::splitName($data['name']);
        $values = ['job_title' => $data['job_title'], 'deal_role' => $data['deal_role']]
            + ($portal ? [] : ['first_name' => $first, 'last_name' => $last, 'email' => $data['email'], 'phone' => $data['phone']]);
        $old = $contact->only(array_keys($values));
        $contact->forceFill($values);
        $changed = array_keys($contact->getDirty());
        if (! $changed && empty($data['main'])) {
            return false;
        }
        DB::transaction(function () use ($contact, $changed, $data, $by, $old) {
            if ($changed) {
                $contact->save();
                $what = collect($changed)->map(fn ($c) => match ($c) {
                    'first_name', 'last_name' => 'name', 'job_title' => 'job title', 'deal_role' => 'role', default => $c,
                })->unique()->implode(', ');
                LeadTimeline::record($contact->organization_id, 'contact_updated', "Contact changed: {$contact->full_name} ({$what}).", [
                    'actor' => $by, 'client_user_id' => $contact->id, 'payload' => ['old' => array_intersect_key($old, array_flip($changed))],
                ]);
            }
            if (! empty($data['main']) && ! $contact->is_main_contact) {
                self::makeMain($contact, $by);
            }
        });

        return true;
    }

    public static function makeMain(ClientUser $contact, User $by): void
    {
        DB::transaction(function () use ($contact, $by) {
            ClientUser::where('organization_id', $contact->organization_id)->where('id', '!=', $contact->id)->where('is_main_contact', 1)->update(['is_main_contact' => 0]);
            $contact->forceFill(['is_main_contact' => true])->save();
            LeadTimeline::record($contact->organization_id, 'contact_updated', "{$contact->full_name} is now the main contact.", ['actor' => $by, 'client_user_id' => $contact->id]);
        });
    }

    /**
     * Remove a contact who has no portal account (soft delete; the timeline keeps their name in its lines). The next
     * contact becomes the main one when it was them.
     *
     * @throws LeadRuleBroken for a portal account
     */
    public static function remove(ClientUser $contact, User $by): void
    {
        if (self::isPortalAccount($contact)) {
            throw new LeadRuleBroken('This person has a Client Portal account. Remove them from the portal’s team instead.', 'contact');
        }
        DB::transaction(function () use ($contact, $by) {
            $wasMain = (bool) $contact->is_main_contact;
            $contact->forceFill(['is_main_contact' => false])->save();
            $contact->delete();
            if ($wasMain && ($next = ClientUser::where('organization_id', $contact->organization_id)->orderBy('created_at')->first())) {
                $next->forceFill(['is_main_contact' => true])->save();
            }
            LeadTimeline::record($contact->organization_id, 'contact_removed', "Contact removed: {$contact->full_name}.", ['actor' => $by, 'payload' => ['client_user_id' => $contact->id]]);
        });
    }

    /** Has a portal account (a password or a pending invitation), so the portal owns its identity */
    public static function isPortalAccount(ClientUser $contact): bool
    {
        return $contact->password !== null || $contact->invitation_token !== null;
    }

    /** "Ada Obi" → ['Ada', 'Obi']; "Ada" → ['Ada', '']; each cut to the columns' 50 characters */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2);

        return [mb_substr($parts[0] ?? '', 0, 50), mb_substr(trim($parts[1] ?? ''), 0, 50)];
    }

    /** Whether a contact block was left empty (the form's spare block): nothing typed in any field */
    public static function isBlank(array $data): bool
    {
        return collect(['name', 'job_title', 'email', 'phone'])->every(fn ($k) => trim((string) ($data[$k] ?? '')) === '');
    }

    private static function clean(array $data): array
    {
        $value = fn ($k) => ($v = trim((string) ($data[$k] ?? ''))) === '' ? null : $v;

        return [
            'name' => (string) $value('name'),
            'job_title' => $value('job_title'),
            'email' => ($e = $value('email')) ? mb_strtolower($e) : null,
            'phone' => $value('phone'),
            'deal_role' => isset(self::DEAL_ROLES[$data['deal_role'] ?? '']) ? $data['deal_role'] : null,
            'main' => ! empty($data['main']),
        ];
    }

    private static function takenMessage(object $taken, string $organizationId): string
    {
        if ($taken->organization_id === $organizationId) {
            return $taken->deleted_at ? 'This email belonged to a contact removed from this lead. Use another address.' : 'This person is already a contact of this lead.';
        }

        return "This email is already a contact of {$taken->organization_name}. Open that lead, or use another address.";
    }
}
