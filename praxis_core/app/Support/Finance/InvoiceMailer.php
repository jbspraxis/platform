<?php

namespace App\Support\Finance;

use App\Mail\InvoiceMail;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\MailLocale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails an issued invoice with its PDF to the client's billing contacts (owner, 2 Nov 2026; docs/QA-PORTAL.md D11):
 * the organisation's active portal accounts that see invoices (portal.billing.view: Client Admin, Client Finance) and
 * may open the project. In the organisation's language. Called after the invoice is saved, never inside its
 * transaction; a failed email doesn't undo the invoice, and HQ can send it again from Finance › Invoices.
 */
final class InvoiceMailer
{
    /** @return Collection<int, ClientUser> */
    public static function recipients(object $invoice): Collection
    {
        $project = DB::table('projects')->where('id', $invoice->project_id)->first(['id', 'organization_id']);
        if (! $project) {
            return collect();
        }

        return ClientUser::where('organization_id', $project->organization_id)->where('status', 'Active')
            ->where(fn ($q) => $q->whereNull('restricted_project_id')->orWhere('restricted_project_id', $project->id))
            ->orderByDesc('is_primary_client')->orderBy('first_name')->get()
            ->filter(fn (ClientUser $c) => filled($c->email) && Access::allows($c, Access::CLIENT_PREFIX . 'billing.view'))->values();
    }

    /**
     * Sends it to every billing contact; returns the addresses reached. invoices.emailed_at records the last time it
     * reached at least one.
     *
     * @return list<string>
     */
    public static function send(string $invoiceId): array
    {
        $invoice = DB::table('invoices')->join('projects', 'projects.id', '=', 'invoices.project_id')
            ->join('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->leftJoin('mdm_legal_entities', 'mdm_legal_entities.id', '=', 'invoices.billing_entity_id')
            ->where('invoices.id', $invoiceId)->whereNull('invoices.deleted_at')->where('invoices.status', '!=', 'Cancelled')
            ->select('invoices.*', 'projects.title as project_title', 'organizations.name as organisation_name',
                'organizations.preferred_language', 'mdm_legal_entities.legal_name as issuer_name')->first();
        if (! $invoice) {
            return [];
        }
        $invoice->issuer_name ??= Vat::issuer(null)?->legal_name;
        $language = MailLocale::forRecipient($invoice->preferred_language);
        $pdf = InvoicePdf::render($invoice, $language);

        $reached = [];
        foreach (self::recipients($invoice) as $contact) {
            try {
                Mail::to($contact->email)->locale($language)
                    ->send(new InvoiceMail($invoice, $contact->first_name ?: null, $pdf, InvoicePdf::filename($invoice)));
                $reached[] = $contact->email;
            } catch (\Throwable $e) {
                Log::error('Invoice email failed', ['invoice_id' => $invoiceId, 'client_user_id' => $contact->id, 'message' => $e->getMessage()]);
            }
        }
        if ($reached) {
            DB::table('invoices')->where('id', $invoiceId)->update(['emailed_at' => now()]);
        }

        return $reached;
    }
}
