<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The named fields as trimmed strings, leaving out empty ones and anything that is not a plain value.
     * HQ validates them (docs/SITE_API.md section 3); its 422 messages come back onto the same names.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    protected function fields(Request $request, array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            $value = $request->input($name);
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (! is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /** Post/Redirect/Get: back to the form's page, which shows its success state. */
    protected function sent(string $route, string $form, array $with = []): RedirectResponse
    {
        return redirect()->route($route)->with('sent', $form)->with($with);
    }
}
