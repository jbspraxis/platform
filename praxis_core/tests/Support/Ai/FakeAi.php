<?php

namespace Tests\Support\Ai;

use App\Support\Ai\Ai;
use App\Support\Ai\AiProvider;
use App\Support\Ai\AiRequest;
use App\Support\Ai\AiResult;
use App\Support\Ai\AiUnavailable;
use App\Support\Ai\JsonShape;
use App\Support\Ai\Providers;
use LogicException;
use PHPUnit\Framework\Assert;

/**
 * The AI for tests (docs/OPPORTUNITIES.md section 10): no request reaches Anthropic or Google. Answers are scripted
 * per purpose and checked against the purpose's schema when scripted, so a test can't pass with an answer the
 * real model could never give. Jobs run at once (the 'sync' queue).
 *
 *   $ai = FakeAi::install()->respond('proposal', ['title' => …, 'sections' => […], 'suggested_line_items' => []]);
 *   $ai->refuse('meeting_notes');      stop_reason refusal        $ai->truncate('sow');   stop_reason max_tokens
 *   $ai->garbage('questionnaire');     an answer off the schema   $ai->fail('proposal', 'credit');  the provider throws
 *   $ai->unconfigured();               no key set
 *   $ai->assertRan('proposal', fn (AiRequest $r) => str_contains($r->prompt, 'French'));
 */
class FakeAi implements AiProvider
{
    /** @var list<AiRequest> */
    public array $requests = [];

    /** purpose => list of scripted answers (the last one repeats) */
    private array $scripts = [];

    private bool $configured = true;

    public static function install(): self
    {
        $fake = new self;
        app()->instance(Providers::class, new class($fake) extends Providers
        {
            public function __construct(private readonly FakeAi $fake) {}

            public function for(string $provider): AiProvider
            {
                return $this->fake;
            }
        });
        config(['ai.queue.connection' => 'sync']);

        return $fake;
    }

    public function respond(string $purpose, array $output, int $inputTokens = 1200, int $outputTokens = 800, int $cacheRead = 0, int $cacheWrite = 0): self
    {
        $errors = JsonShape::errors($output, Ai::purpose($purpose)['schema']);
        Assert::assertSame([], $errors, "FakeAi: the scripted '{$purpose}' answer doesn't follow its schema in config/ai.php");

        return $this->script($purpose, ['text' => json_encode($output), 'stop' => 'end_turn', 'tokens' => [$inputTokens, $outputTokens, $cacheRead, $cacheWrite]]);
    }

    public function refuse(string $purpose, ?string $category = 'cyber'): self
    {
        return $this->script($purpose, ['text' => null, 'stop' => 'refusal', 'category' => $category, 'tokens' => [900, 0, 0, 0]]);
    }

    public function truncate(string $purpose): self
    {
        return $this->script($purpose, ['text' => '{"title": "Cut', 'stop' => 'max_tokens', 'tokens' => [1500, (int) Ai::purpose($purpose)['max_tokens'], 0, 0]]);
    }

    public function garbage(string $purpose, string $text = '{"unexpected": true}'): self
    {
        return $this->script($purpose, ['text' => $text, 'stop' => 'end_turn', 'tokens' => [1000, 50, 0, 0]]);
    }

    public function fail(string $purpose, string $reason = 'busy'): self
    {
        return $this->script($purpose, ['throw' => $reason]);
    }

    public function unconfigured(): self
    {
        $this->configured = false;

        return $this;
    }

    public function configured(): bool
    {
        return $this->configured;
    }

    public function generate(AiRequest $request, ?callable $progress = null): AiResult
    {
        $this->requests[] = $request;
        $queue = $this->scripts[$request->purpose] ?? throw new LogicException("FakeAi: no answer scripted for '{$request->purpose}'");
        $answer = count($queue) > 1 ? array_shift($this->scripts[$request->purpose]) : $queue[0];

        if (isset($answer['throw'])) {
            throw new AiUnavailable($answer['throw']);
        }
        if ($progress) {
            $progress(50);
        }
        [$in, $out, $read, $write] = $answer['tokens'];

        return new AiResult($answer['text'], $answer['stop'], $request->model, $in, $out, $read, $write, [], $answer['category'] ?? null);
    }

    /** @return list<AiRequest> */
    public function requestsFor(string $purpose): array
    {
        return array_values(array_filter($this->requests, fn (AiRequest $r) => $r->purpose === $purpose));
    }

    public function assertRan(string $purpose, ?callable $check = null): void
    {
        $runs = $this->requestsFor($purpose);
        Assert::assertNotEmpty($runs, "FakeAi: no '{$purpose}' request was made");
        if ($check) {
            Assert::assertTrue(collect($runs)->contains($check), "FakeAi: no '{$purpose}' request matched");
        }
    }

    public function assertNothingRan(): void
    {
        Assert::assertSame([], $this->requests, 'FakeAi: an AI request was made');
    }

    private function script(string $purpose, array $answer): self
    {
        Ai::purpose($purpose); // unknown purposes fail here, not later
        $this->scripts[$purpose][] = $answer;

        return $this;
    }
}
