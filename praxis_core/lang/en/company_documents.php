<?php

// Master data › Company documents (docs/COMPANY_DOCUMENTS.md): why Claude didn't read an upload.
// company_document_versions.read_error holds the key, so each person reads it in their own language. Same keys as lang/fr.

return [
    'read' => [
        'image_too_big' => 'This image is over 5 MB, more than Claude can read. Type the details from the document, or upload a smaller scan.',
        'no_text' => 'HQ couldn’t find any text in this Word file. Type the details from the document.',
    ],
];
