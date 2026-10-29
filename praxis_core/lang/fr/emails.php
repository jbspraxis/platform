<?php

// Email wording (docs/BRAND.md section 13). One file per email family in ./emails/, read as __('emails.<family>.<key>').
// Placeholders are identical in both languages, lower case and English (:name, :company, :date).
$families = [];
foreach (glob(__DIR__ . '/emails/*.php') as $file) {
    $families[basename($file, '.php')] = require $file;
}

return $families;
