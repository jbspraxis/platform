<?php

namespace App\Support\CompanyProfile;

use Illuminate\Support\Str;

/**
 * The logo wall, read straight off disk: every PNG and SVG in resources/company-profile/logos/, in filename
 * order, named after the file. An empty or missing folder gives an empty wall, never an error.
 *
 * A filename may carry its own order and its own name: "01 Fidson Healthcare.png" is shown as
 * "Fidson Healthcare" and sorts first.
 */
class DirectoryClientLogos implements ClientLogos
{
    public function __construct(private readonly ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?? resource_path('company-profile/logos');
    }

    public function all(): array
    {
        $directory = $this->directory();

        if (! is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/\\').'/*.{png,PNG,svg,SVG}', GLOB_BRACE) ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        $logos = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            // "01 Fidson Healthcare" and "01-Fidson Healthcare" both show as "Fidson Healthcare"
            $name = (string) preg_replace('/^\d+\s*[-_. ]\s*/', '', $name);
            $logos[] = [
                'name' => Str::of($name)->replace(['-', '_'], ' ')->squish()->value(),
                'path' => str_replace('\\', '/', $file),
            ];
        }

        return $logos;
    }
}
