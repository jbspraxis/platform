<?php

namespace App\Support\Ai;

use App\Jobs\RunAiJob;
use App\Models\AiRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * HQ's AI service (docs/OPPORTUNITIES.md 8.3). Every job is an ai_runs row: the screen that asked polls its status
 * and progress, then reads output, which has been checked against the purpose's schema (config/ai.php).
 *
 *   Ai::queue('proposal', $opportunity, $input)   in the background (the 'ai' queue, run every minute)
 *   Ai::now('client_minutes', $transcript, $input) at once, for purposes marked sync
 *
 * $input: context (the opportunity's text, cached after the company brief), material (what this job is about,
 * e.g. one transcript), instructions (the lead's), language (en | fr). Both throw AiUnavailable before anything
 * is spent when no key is set or the job could take the month over the AI budget. A run that fails keeps the
 * tokens it used (they are paid for) and an error key: $run->errorMessage() says what happened. Jobs are never
 * retried: one draft per click.
 */
final class Ai
{
    public static function purpose(string $purpose): array
    {
        $config = config("ai.purposes.{$purpose}");
        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown AI purpose '{$purpose}' (config/ai.php)");
        }

        return $config;
    }

    public static function queue(string $purpose, Model|array $subject, array $input): AiRun
    {
        $run = self::start($purpose, $subject, $input);
        RunAiJob::dispatch($run->id)->onConnection(config('ai.queue.connection'))->onQueue(config('ai.queue.name'));

        return $run->fresh();
    }

    public static function now(string $purpose, Model|array $subject, array $input): AiRun
    {
        if (empty(self::purpose($purpose)['sync'])) {
            throw new LogicException("The AI purpose '{$purpose}' is too long to run while the page waits: use Ai::queue()");
        }

        return self::run(self::start($purpose, $subject, $input));
    }

    /** Runs a queued job (RunAiJob calls this). Never throws: the outcome is on the run. */
    public static function run(AiRun $run): AiRun
    {
        if ($run->isFinished()) {
            return $run;
        }
        $run->forceFill(['status' => 'running', 'started_at' => now(), 'progress' => 5])->save();

        try {
            $config = self::purpose($run->purpose);
            $request = self::request($run->purpose, $run->input ?? []);
            if (! Budget::allows($request)) {
                throw new AiUnavailable('budget');
            }

            $last = 0.0;
            $result = app(Providers::class)->for($config['provider'])->generate($request, function (int $progress) use ($run, &$last) {
                if (microtime(true) - $last >= 2) {
                    $last = microtime(true);
                    AiRun::whereKey($run->id)->update(['progress' => max(5, min(95, $progress))]);
                }
            });

            // Paid for whatever happens next
            $run->forceFill([
                'model' => $result->model,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'cache_read_tokens' => $result->cacheReadTokens,
                'cache_write_tokens' => $result->cacheWriteTokens,
                'cost_usd' => Pricing::cost($result->usage()),
                'stop_reason' => $result->stopReason,
            ]);

            if ($result->stopReason === 'refusal') {
                throw new AiUnavailable('refused');
            }
            if ($result->stopReason === 'max_tokens') {
                throw new AiUnavailable('too_long');
            }
            $data = json_decode((string) $result->text, true);
            $problems = is_array($data) ? JsonShape::errors($data, $config['schema']) : ['$: not JSON'];
            if ($problems) {
                Log::warning('AI: the answer did not follow the schema', ['purpose' => $run->purpose, 'model' => $result->model, 'problems' => array_slice($problems, 0, 5)]);
                throw new AiUnavailable('invalid');
            }

            $run->forceFill(['status' => 'done', 'progress' => 100, 'output' => $data, 'error' => null, 'finished_at' => now()])->save();
        } catch (AiUnavailable $e) {
            $run->forceFill(['status' => 'failed', 'error' => $e->key(), 'finished_at' => now(), 'stop_reason' => $run->stop_reason ?? $e->reason])->save();
        } catch (Throwable $e) {
            report($e);
            $run->forceFill(['status' => 'failed', 'error' => 'ai.errors.failed', 'finished_at' => now()])->save();
        }

        return $run;
    }

    /** The request a purpose makes from its input; also what the budget estimates */
    public static function request(string $purpose, array $input): AiRequest
    {
        $config = self::purpose($purpose);
        $system = [];
        if (($config['brief'] ?? true) !== false) {
            $system[] = CompanyBrief::text();
        }
        if (trim((string) ($input['context'] ?? '')) !== '') {
            $system[] = "THE OPPORTUNITY (everything HQ holds about this piece of work):\n\n" . trim($input['context']);
        }

        // Everything that changes from one request to the next comes after the cached blocks
        $prompt = 'TASK: ' . $config['task'] . "\n\nLANGUAGE: " . self::languageRule((string) ($input['language'] ?? 'en'));
        if (trim((string) ($input['instructions'] ?? '')) !== '') {
            $prompt .= "\n\nINSTRUCTIONS FROM THE LEAD:\n" . trim($input['instructions']);
        }
        if (trim((string) ($input['material'] ?? '')) !== '') {
            $prompt .= "\n\nMATERIAL:\n" . trim($input['material']);
        }

        return new AiRequest(
            purpose: $purpose,
            model: $config['model'],
            effort: $config['effort'],
            maxTokens: (int) $config['max_tokens'],
            system: $system,
            prompt: $prompt,
            schema: $config['schema'],
            stream: (bool) ($config['stream'] ?? false),
            expectedChars: (int) ($config['expected_chars'] ?? 4000),
        );
    }

    public static function languageRule(string $language): string
    {
        return $language === 'fr'
            ? 'Write in French for a client in Cameroon or Nigeria: the register of a Big Four letter (courant soigné), always vouvoiement. French typography: a narrow no-break space (U+202F) before ; ? ! and %, a no-break space (U+00A0) before :, « » for quotes, typographic apostrophes (’). Keep proper names and JBS Praxis as they are.'
            : 'Write in British English (organisation, programme, licence), with typographic apostrophes (’).';
    }

    /** Runs left queued or running by a worker that stopped (after 30 minutes) fail, so screens stop waiting */
    public static function expireStuck(): int
    {
        return AiRun::whereIn('status', ['queued', 'running'])->where('created_at', '<', now()->subMinutes(30))
            ->update(['status' => 'failed', 'error' => 'ai.errors.stuck', 'finished_at' => now()]);
    }

    private static function start(string $purpose, Model|array $subject, array $input): AiRun
    {
        $config = self::purpose($purpose);
        $request = self::request($purpose, $input);
        if (! app(Providers::class)->for($config['provider'])->configured()) {
            throw new AiUnavailable('not_configured');
        }
        if (! Budget::allows($request)) {
            throw new AiUnavailable('budget');
        }

        [$type, $id] = $subject instanceof Model ? [$subject->getMorphClass(), $subject->getKey()] : [$subject['type'] ?? null, $subject['id'] ?? null];

        return AiRun::create([
            'purpose' => $purpose,
            'provider' => $config['provider'],
            'model' => $config['model'],
            'subject_type' => $type,
            'subject_id' => $id,
            'status' => 'queued',
            'progress' => 0,
            'input' => $input,
            'requested_by' => Auth::guard('web')->id(),
        ]);
    }
}
