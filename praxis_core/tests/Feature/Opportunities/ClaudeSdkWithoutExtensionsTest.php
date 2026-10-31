<?php

namespace Tests\Feature\Opportunities;

use Illuminate\Support\Facades\Process;
use Tests\Support\PhpWithout;
use Tests\TestCase;

/**
 * The Claude SDK (anthropic-ai/sdk) on a PHP like the live web PHP, without intl, fileinfo, exif, sodium or gmp
 * (docs/OPPORTUNITIES.md section 5): the client builds, a request is shaped and a stream accumulates. An extension
 * compiled into the PHP running the tests (sodium on GitHub's runners) can't be left out, so the second test reads
 * the SDK and its dependencies instead: none of them calls any of the five, on any machine.
 */
class ClaudeSdkWithoutExtensionsTest extends TestCase
{
    use PhpWithout;

    private const MISSING_ON_LIVE = ['intl', 'fileinfo', 'exif', 'sodium', 'gmp'];

    /** The SDK and the packages it brought in (composer.lock, 31 Oct 2026) */
    private const SDK_PACKAGES = ['anthropic-ai/sdk', 'open-telemetry/api', 'open-telemetry/context', 'php-http/discovery', 'standard-webhooks/standard-webhooks', 'symfony/polyfill-php82'];

    public function test_the_sdk_works_without_the_extensions_live_lacks(): void
    {
        // phpWithout() is null for an extension compiled into this PHP: those stay, the rest are left out
        $builtIn = array_values(array_filter(self::MISSING_ON_LIVE, fn ($ext) => $this->phpWithout($ext) === null));
        $removable = array_values(array_diff(self::MISSING_ON_LIVE, $builtIn));
        if (! $removable) {
            $this->markTestSkipped('intl, fileinfo, exif, sodium and gmp are all built into this PHP: none can be left out');
        }
        $base = $this->phpWithout($removable[0]);
        // phpWithout leaves out one extension; drop the '-d extension=…' pairs of the other removable ones as well
        $php = [];
        for ($i = 0; $i < count($base); $i++) {
            $next = $base[$i + 1] ?? '';
            if ($base[$i] === '-d' && str_starts_with($next, 'extension=') && in_array(substr($next, 10), $removable, true)) {
                $i++;
                continue;
            }
            $php[] = $base[$i];
        }

        $code = 'require ' . var_export(base_path('vendor/autoload.php'), true) . ';'
            . '$sse = "event: message_start\ndata: " . json_encode(["type" => "message_start", "message" => ["id" => "m", "type" => "message", "role" => "assistant", "model" => "claude-haiku-5-5", "content" => [], "stop_reason" => null, "stop_sequence" => null, "usage" => ["input_tokens" => 3, "output_tokens" => 1]]]) . "\n\n"'
            . ' . "event: content_block_start\ndata: " . json_encode(["type" => "content_block_start", "index" => 0, "content_block" => ["type" => "text", "text" => ""]]) . "\n\n"'
            . ' . "event: content_block_delta\ndata: " . json_encode(["type" => "content_block_delta", "index" => 0, "delta" => ["type" => "text_delta", "text" => "{\"ok\":true}"]]) . "\n\n"'
            . ' . "event: content_block_stop\ndata: " . json_encode(["type" => "content_block_stop", "index" => 0]) . "\n\n"'
            . ' . "event: message_delta\ndata: " . json_encode(["type" => "message_delta", "delta" => ["stop_reason" => "end_turn", "stop_sequence" => null], "usage" => ["output_tokens" => 5]]) . "\n\n"'
            . ' . "event: message_stop\ndata: " . json_encode(["type" => "message_stop"]) . "\n\n";'
            . '$guzzle = new GuzzleHttp\Client(["handler" => GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([new GuzzleHttp\Psr7\Response(200, ["Content-Type" => "text/event-stream"], $sse)]))]);'
            . '$client = new Anthropic\Client(apiKey: "sk-ant-test", requestOptions: Anthropic\RequestOptions::with(transporter: $guzzle, streamingTransporter: $guzzle, maxRetries: 0), openTelemetry: false);'
            . '$acc = Anthropic\Lib\Streaming\MessageAccumulator::forBetaMessages();'
            . 'foreach ($client->beta->messages->createStream(model: "claude-haiku-5-5", maxTokens: 64, messages: [["role" => "user", "content" => "hi"]], thinking: ["type" => "adaptive"], outputConfig: ["effort" => "low", "format" => ["type" => "json_schema", "schema" => ["type" => "object", "properties" => ["ok" => ["type" => "boolean"]], "required" => ["ok"], "additionalProperties" => false]]]) as $e) { $acc->accumulate($e); }'
            . '$m = $acc->message();'
            . 'echo json_encode(["loaded" => array_values(array_filter(' . var_export(self::MISSING_ON_LIVE, true) . ', "extension_loaded")), "text" => $m->content[0]->text, "stop" => $m->stopReason, "out" => $m->usage->outputTokens]);';

        $run = Process::path(base_path())->run([...$php, '-r', $code]);

        $this->assertTrue($run->successful(), $run->errorOutput() . $run->output());
        $out = json_decode($run->output(), true);
        $this->assertIsArray($out, $run->output());
        $this->assertSame($builtIn, $out['loaded'], 'The second PHP still has an extension that could have been left out');
        $this->assertSame(['{"ok":true}', 'end_turn', 5], [$out['text'], $out['stop'], $out['out']]);
    }

    public function test_the_sdk_never_calls_those_extensions(): void
    {
        $uses = '/\b(?:sodium_\w+|gmp_\w+|exif_\w+|finfo_\w+|mime_content_type|grapheme_\w+|idn_to_\w+|intl\w*_\w+|numfmt_\w+|datefmt_\w+|collator_\w+|normalizer_\w+|transliterator_\w+)\s*\('
            . '|\bnew\s+\\\\?(?:finfo|Normalizer|NumberFormatter|IntlDateFormatter|Collator|Transliterator|IntlTimeZone|IntlCalendar|ResourceBundle|MessageFormatter|Locale|GMP)\b'
            . '|\b(?:Normalizer|NumberFormatter|IntlDateFormatter|Collator|Transliterator|Locale|IntlChar)::/';

        $files = 0;
        $found = [];
        foreach (self::SDK_PACKAGES as $package) {
            $dir = base_path('vendor/' . $package);
            $this->assertDirectoryExists($dir, "{$package} is not installed");
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php' || preg_match('~[\\\\/](tests?|examples|fixtures)[\\\\/]~i', $file->getPathname())) {
                    continue;
                }
                $files++;
                // Comments may name anything; code may not
                $code = preg_replace('~/\*.*?\*/|//[^\n]*|#[^\[\n][^\n]*~s', '', (string) file_get_contents($file->getPathname()));
                if (preg_match_all($uses, $code, $m)) {
                    $found[] = str_replace(base_path('vendor') . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ': ' . implode(', ', array_unique($m[0]));
                }
            }
        }

        $this->assertGreaterThan(100, $files, 'The SDK sources were not found');
        $this->assertSame([], $found, 'The SDK or one of its dependencies calls an extension the live web PHP lacks');
    }
}
