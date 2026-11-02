<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The app runs in UTC and its *_at stamps are UTC, while everyone reads HQ in Lagos or Douala time (WAT): a time of day
 * shown from a stamp must be moved to Africa/Lagos first. The QA hub showed a reply sent at 12:57 as 11:57
 * (docs/QA-PORTAL.md). Times saved as Lagos wall-clock (appointments' start_time) are not stamps and stay as they are.
 */
class DisplayedTimesTest extends TestCase
{
    public function test_a_time_of_day_shown_from_a_stamp_is_in_lagos_time(): void
    {
        $found = [];
        $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/resources/views'));
        foreach ($views as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match_all('/Carbon::parse\(([^()]*_at[^()]*)\)->format\(\'[^\']*H:i/', $line, $m)) {
                    $found[] = str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . ($number + 1);
                }
            }
        }

        $this->assertSame([], $found, 'Add ->setTimezone(\'Africa/Lagos\') before ->format(): the stamp is UTC.');
    }
}
