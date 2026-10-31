<?php

namespace Tests\Feature\Opportunities;

use Illuminate\Support\Facades\Process;
use Tests\Support\PhpWithout;
use Tests\TestCase;

/**
 * The Claude SDK (anthropic-ai/sdk) on a PHP like the live web PHP, without intl, fileinfo, exif, sodium or gmp
 * (docs/OPPORTUNITIES.md section 5): the client builds, a request is shaped and a stream accumulates.
 */
class ClaudeSdkWithoutExtensionsTest extends TestCase
{
    use PhpWithout;

    private const MISSING_ON_LIVE = ['intl', 'fileinfo', 'exif', 'sodium', 'gmp'];

    public function test_the_sdk_works_without_the_extensions_live_lacks(): void
    {
        $base = $this->phpWithout('intl');
        if ($base === null) {
            $this->markTestSkipped('intl is built into this PHP: it cannot be left out');
        }
        // phpWithout leaves out one extension; drop the '-d extension=…' pairs of the other four as well
        $php = [];
        for ($i = 0; $i < count($base); $i++) {
            $next = $base[$i + 1] ?? '';
            if ($base[$i] === '-d' && str_starts_with($next, 'extension=') && in_array(substr($next, 10), self::MISSING_ON_LIVE, true)) {
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
        $this->assertSame([], $out['loaded'], 'The second PHP still has one of the extensions live lacks');
        $this->assertSame(['{"ok":true}', 'end_turn', 5], [$out['text'], $out['stop'], $out['out']]);
    }
}
