<?php

namespace App\Support\Leads;

use RuntimeException;

/**
 * A change the lead rules refuse (docs/LEADS.md section 6), with a message fit for the screen and the field it is
 * about, so a Livewire form can show it under that field: catch it and addError($e->field, $e->getMessage()).
 */
final class LeadRuleBroken extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'lead')
    {
        parent::__construct($message);
    }
}
