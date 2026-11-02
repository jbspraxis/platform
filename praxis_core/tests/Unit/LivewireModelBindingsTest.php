<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Livewire 4 changed wire:model's modifiers: .blur, .change and .enter on their own only sync the value in the
 * browser and send nothing, so a field "saved when it is left" was never saved unless another click followed
 * (docs/QA-PORTAL.md). They must come after .live (wire:model.live.blur), which sends the value on that event.
 */
class LivewireModelBindingsTest extends TestCase
{
    public function test_no_view_waits_for_blur_change_or_enter_without_live(): void
    {
        $found = [];
        $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/resources/views'));
        foreach ($views as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match('/wire:model\.(blur|change|enter)\b/', $line)) {
                    $found[] = str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . ($number + 1);
                }
            }
        }

        $this->assertSame([], $found, 'Use wire:model.live.blur (or .live.change, .live.enter): without .live Livewire 4 sends nothing.');
    }
}
