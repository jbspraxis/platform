<?php

namespace App\Exceptions\Hq;

use Illuminate\Validation\ValidationException;

/**
 * 422 from HQ. The API's field names are the form's input names (snake_case), so the field
 * messages land next to the right inputs; the summary (already in the visitor's language) is
 * shown above the form.
 */
class HqValidationException extends ValidationException
{
    public ?string $summary = null;

    /**
     * @param  array<string, string|array<int, string>>  $fields
     */
    public static function fromApi(array $fields, ?string $summary = null): static
    {
        $messages = [];
        foreach ($fields as $field => $list) {
            $list = array_values(array_filter(array_map('strval', (array) $list), 'strlen'));
            if (is_string($field) && $field !== '' && $list) {
                $messages[$field] = $list;
            }
        }
        if (! $messages) {
            $messages['form'] = [$summary ?: __('site.errors.check_fields')];
        }

        $exception = static::withMessages($messages);
        $exception->summary = $summary ?: null;

        return $exception;
    }
}
