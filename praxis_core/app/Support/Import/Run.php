<?php

namespace App\Support\Import;

use App\Models\User;

/**
 * One reading of one file: its fingerprint, its name, the dialog's choices and who is importing. A schema keeps
 * what it learns across groups here ($seen: an email already met in an earlier row, for example).
 */
final class Run
{
    /** Whatever the schema wants to remember from one group to the next while the file is read */
    public array $seen = [];

    public function __construct(
        public readonly string $hash,
        public readonly string $fileName,
        public readonly array $options = [],
        public readonly ?User $by = null,
    ) {
    }

    /** "import:<file fingerprint>:<row>": the same row of the same file is imported once, however often it is uploaded */
    public function dedupeKey(int $line): string
    {
        return 'import:' . $this->hash . ':' . $line;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
