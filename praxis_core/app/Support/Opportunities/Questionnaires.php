<?php

namespace App\Support\Opportunities;

use App\Events\QuestionnaireCompleted;
use App\Models\AiRun;
use App\Models\Opportunity;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\User;
use App\Support\Ai\Ai;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An opportunity's questionnaire (docs/OPPORTUNITIES.md section 4, row 3; owner decision 6). Only the opportunity's
 * lead answers it. They choose how many questions (3–25) and, optionally, focus areas; the AI drafts the questions
 * in the background (purpose questionnaire, Claude Sonnet) from OpportunityContext. Every AI question keeps at least
 * 3 suggested answers and the recommended one with why. The lead picks a suggestion (with optional detail) or writes
 * their own answer, redrafts one question (only that question is sent: purpose questionnaire_question), adds their
 * own questions or removes one; answers save as they go. complete() locks it for the proposal (PR 4).
 *
 * The AI's answers are applied by sync(), which the tab calls on every load and while it polls, with the row
 * locked, so each is applied once whoever is looking. A whole draft is also filed as soon as its job finishes
 * (fileAiOutput, on AiRunFinished), and the lead gets a bell, so they needn't wait on the page. Callers check the
 * lead (canAnswer) first.
 */
final class Questionnaires
{
    public const MIN_QUESTIONS = 3;

    public const MAX_QUESTIONS = 25;

    public const DEFAULT_QUESTIONS = 10;

    public const MIN_OPTIONS = 3;

    /** Suggestions kept per question; the task asks for 3 to 5 */
    public const MAX_OPTIONS = 6;

    /** Characters in an answer, a question or a note */
    public const MAX_TEXT = 5000;

    /** Focus areas the lead can tick: key => what the AI reads (English, like the rest of the prompt) */
    public const FOCUS = [
        'scope' => 'Scope and priorities',
        'processes' => 'Processes and controls',
        'systems' => 'Systems and data',
        'people' => 'People and change',
        'timeline' => 'Timeline and milestones',
        'commercial' => 'Budget and commercial terms',
        'risks' => 'Risks and compliance',
        'governance' => 'Governance and decision-makers',
    ];

    public static function current(Opportunity $opportunity): ?Questionnaire
    {
        return $opportunity->questionnaires()->first();
    }

    /** Only the opportunity's lead answers, with the right to manage opportunities, while it is open */
    public static function canAnswer(?User $user, Opportunity $opportunity): bool
    {
        return OpportunityAccess::isLead($user, $opportunity) && OpportunityAccess::canManage($user, $opportunity) && ! $opportunity->isClosed();
    }

    /**
     * Asks the AI for the questions, in the background. Throws AiUnavailable (no key, over the budget) before
     * anything is saved or spent. A questionnaire whose draft failed is drafted again in place; one already being
     * drafted, or drafted, is returned as it is (a second click asks for nothing).
     *
     * @param  list<string>  $areas  keys of FOCUS
     */
    public static function draft(Opportunity $opportunity, int $count, array $areas, string $note, User $by): Questionnaire
    {
        $existing = self::current($opportunity);
        if ($existing) {
            self::sync($existing);
            if ($existing->status !== 'drafting' || self::running($existing->ai_run_id)) {
                return $existing;
            }
        }

        $count = max(self::MIN_QUESTIONS, min(self::MAX_QUESTIONS, $count));
        $areas = array_values(array_intersect(array_keys(self::FOCUS), $areas));
        $note = Str::limit(trim($note), self::MAX_TEXT, '');
        $id = $existing?->id ?? (string) Str::uuid();

        $run = Ai::queue('questionnaire', ['type' => 'questionnaire', 'id' => $id], [
            'context' => OpportunityContext::for($opportunity),
            'instructions' => self::instructions($count, $areas, $note),
            'language' => $opportunity->language,
        ]);

        $questionnaire = $existing ?? (new Questionnaire)->forceFill(['id' => $id, 'created_by' => $by->id]);
        $questionnaire->fill([
            'opportunity_id' => $opportunity->id,
            'question_count' => $count,
            'focus' => ['areas' => $areas, 'note' => $note],
            'status' => 'drafting',
            'ai_run_id' => $run->id,
            'updated_by' => $by->id,
        ])->save();

        OpportunityAccess::log('QUESTIONNAIRE_REQUESTED', "Asked the AI to draft {$count} questions for “{$opportunity->title}”.", 'opportunities', $opportunity->id,
            [], ['questionnaire_id' => $id, 'question_count' => $count, 'focus' => $areas]);
        if ($opportunity->stage === 'discovery') {
            self::moveStage($opportunity, 'questionnaire', $by);
        }
        self::sync($questionnaire);

        return $questionnaire;
    }

    /**
     * Applies finished AI jobs: the draft (drafting → open) and redrafted questions. Returns the ids of the
     * questions it wrote, so a screen holding answers for them reloads those.
     *
     * @return list<string>
     */
    public static function sync(Questionnaire $questionnaire): array
    {
        $changed = [];
        if ($questionnaire->status === 'drafting' && $questionnaire->ai_run_id) {
            $run = AiRun::find($questionnaire->ai_run_id, AiRun::RESULT);
            if ($run?->succeeded()) {
                $changed = self::applyDraft($questionnaire, $run);
            }
        }
        if ($questionnaire->status !== 'open') {
            return $changed;
        }

        $pending = QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)->whereNotNull('ai_run_id')->pluck('ai_run_id', 'id');
        if ($pending->isNotEmpty()) {
            $done = AiRun::whereIn('id', $pending->values())->where('status', 'done')->pluck('id')->all();
            foreach ($pending as $questionId => $runId) {
                if (in_array($runId, $done, true) && self::applyRedraft($questionId, $runId)) {
                    $changed[] = $questionId;
                }
            }
        }

        return $changed;
    }

    /**
     * AiRunFinished: a finished draft goes in at once and the lead gets a bell saying the questions are ready, or why
     * there are none. Only the job the questionnaire is waiting for counts (not one it was set aside from, or one
     * that ran before its questionnaire was saved). A redraft of one question is left to the tab: the lead is there.
     */
    public static function fileAiOutput(AiRun $run): void
    {
        if ($run->purpose !== 'questionnaire' || $run->subject_type !== 'questionnaire' || ! $run->isFinished()) {
            return;
        }
        $questionnaire = Questionnaire::find($run->subject_id);
        if (! $questionnaire || $questionnaire->ai_run_id !== $run->id || $questionnaire->status !== 'drafting') {
            return;
        }

        self::sync($questionnaire);
        $opportunity = $questionnaire->opportunity;
        if ($questionnaire->status === 'open') {
            $count = $questionnaire->questions()->count();
            self::tellLead($opportunity, __('questionnaire.notify.ready_title', ['title' => $opportunity->title]),
                trans_choice('questionnaire.notify.ready', $count, ['count' => $count]));
        } elseif (($run = $run->fresh())->status === 'failed') { // failed in the job, or nothing usable came back
            self::tellLead($opportunity, __('questionnaire.notify.failed_title', ['title' => $opportunity->title]),
                __('questionnaire.notify.failed', ['message' => $run->errorMessage()]));
        }
    }

    /**
     * Redraft an AI question, or suggest answers to the lead's own question (its wording stays). Only that question
     * goes to the AI, with the organisation, the questionnaire's focus and the other questions' wording.
     */
    public static function redraft(QuestionnaireQuestion $question, string $note, User $by): void
    {
        $questionnaire = $question->questionnaire;
        if ($questionnaire->status !== 'open' || self::running($question->ai_run_id)) {
            return;
        }
        $opportunity = $questionnaire->opportunity;

        $run = Ai::queue('questionnaire_question', $questionnaire, [
            'context' => OpportunityContext::for($opportunity, include: ['profile']),
            'instructions' => Str::limit(trim($note), self::MAX_TEXT, ''),
            'material' => self::material($questionnaire, $question),
            'language' => $opportunity->language,
        ]);
        $question->forceFill(['ai_run_id' => $run->id, 'updated_by' => $by->id])->save();

        OpportunityAccess::log($question->origin === 'lead' ? 'QUESTIONNAIRE_ANSWERS_SUGGESTED' : 'QUESTIONNAIRE_QUESTION_REDRAFTED',
            ($question->origin === 'lead' ? 'Asked the AI to suggest answers to question ' : 'Asked the AI to redraft question ') . "{$question->position} of the questionnaire.",
            'opportunities', $opportunity->id, [], ['questionnaire_id' => $questionnaire->id, 'question_id' => $question->id]);
        self::sync($questionnaire);
    }

    /** Clears a failed redraft's message from the question */
    public static function dismissFailure(QuestionnaireQuestion $question): void
    {
        if ($question->ai_run_id && ! self::running($question->ai_run_id)) {
            $question->forceFill(['ai_run_id' => null])->save();
        }
    }

    public static function addQuestion(Questionnaire $questionnaire, string $text, string $why, User $by): ?QuestionnaireQuestion
    {
        if ($questionnaire->status !== 'open') {
            return null;
        }
        $question = QuestionnaireQuestion::create([
            'questionnaire_id' => $questionnaire->id,
            'position' => (int) QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)->max('position') + 1,
            'question' => Str::limit(trim($text), self::MAX_TEXT, ''),
            'why_it_matters' => trim($why) === '' ? null : Str::limit(trim($why), self::MAX_TEXT, ''),
            'options' => [],
            'origin' => 'lead',
            'created_by' => $by->id,
            'updated_by' => $by->id,
        ]);
        OpportunityAccess::log('QUESTIONNAIRE_QUESTION_ADDED', "Added question {$question->position} to the questionnaire: “{$question->question}”.",
            'opportunities', $questionnaire->opportunity_id, [], ['questionnaire_id' => $questionnaire->id, 'question_id' => $question->id]);

        return $question;
    }

    public static function removeQuestion(QuestionnaireQuestion $question, User $by): void
    {
        $questionnaire = $question->questionnaire;
        if ($questionnaire->status !== 'open') {
            return;
        }
        DB::transaction(function () use ($question, $questionnaire) {
            $question->delete();
            foreach (QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)->orderBy('position')->get() as $i => $q) {
                if ($q->position !== $i + 1) {
                    $q->forceFill(['position' => $i + 1])->save();
                }
            }
        });
        OpportunityAccess::log('QUESTIONNAIRE_QUESTION_REMOVED', "Removed question {$question->position} from the questionnaire: “{$question->question}”.",
            'opportunities', $questionnaire->opportunity_id, ['question_id' => $question->id, 'question' => $question->question], []);
    }

    /**
     * Saves the lead's answer as they go: $option is the suggestion they picked (null for their own words), $text
     * the detail or their own answer. Ignored once the questionnaire is complete or while the question is redrafted.
     */
    public static function answer(QuestionnaireQuestion $question, ?int $option, ?string $text, User $by): bool
    {
        if ($question->questionnaire->status !== 'open' || self::running($question->ai_run_id)) {
            return false;
        }
        if ($option !== null && ! array_key_exists($option, (array) $question->options)) {
            $option = null;
        }
        $text = trim((string) $text) === '' ? null : Str::limit(trim((string) $text), self::MAX_TEXT, '');
        $answered = $option !== null || $text !== null;

        $question->forceFill(['answer_option' => $option, 'answer_text' => $text]);
        if ($question->isDirty(['answer_option', 'answer_text'])) {
            $question->forceFill(['answered_by' => $answered ? $by->id : null, 'answered_at' => $answered ? now() : null, 'updated_by' => $by->id])->save();
        }

        return true;
    }

    /**
     * Locks the answers for the proposal and raises QuestionnaireCompleted. False while a question is unanswered
     * or still being redrafted. An opportunity still in Discovery or Questionnaire moves to Proposal in progress.
     */
    public static function complete(Questionnaire $questionnaire, User $by): bool
    {
        $done = DB::transaction(function () use ($questionnaire, $by) {
            $locked = Questionnaire::whereKey($questionnaire->id)->lockForUpdate()->first();
            $questions = QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)->get();
            if (! $locked || $locked->status !== 'open' || $questions->isEmpty()
                || $questions->contains(fn (QuestionnaireQuestion $q) => ! $q->isAnswered() || self::running($q->ai_run_id))) {
                return false;
            }
            QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)->whereNotNull('ai_run_id')->update(['ai_run_id' => null]);
            $locked->forceFill(['status' => 'complete', 'completed_by' => $by->id, 'completed_at' => now(), 'updated_by' => $by->id])->save();
            $questionnaire->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
        if (! $done) {
            return false;
        }

        $opportunity = $questionnaire->opportunity;
        OpportunityAccess::log('QUESTIONNAIRE_COMPLETED', "Marked the questionnaire of “{$opportunity->title}” complete.", 'opportunities', $opportunity->id,
            ['status' => 'open'], ['questionnaire_id' => $questionnaire->id, 'status' => 'complete']);
        if (in_array($opportunity->stage, ['discovery', 'questionnaire'], true)) {
            self::moveStage($opportunity, 'proposal', $by);
        }
        QuestionnaireCompleted::dispatch($questionnaire->id, $opportunity->id, $by->id);

        return true;
    }

    /** Sets an open questionnaire aside (soft delete, kept for the record) so the lead can draft again */
    public static function restart(Questionnaire $questionnaire, User $by): void
    {
        if ($questionnaire->status !== 'open') {
            return;
        }
        $questionnaire->forceFill(['deleted_by' => $by->id])->save();
        $questionnaire->delete();
        OpportunityAccess::log('QUESTIONNAIRE_RESTARTED', 'Set the questionnaire aside to draft it again.', 'opportunities', $questionnaire->opportunity_id,
            ['questionnaire_id' => $questionnaire->id], []);
    }

    /** True while an AI job is queued or running */
    public static function running(?string $runId): bool
    {
        return $runId !== null && AiRun::whereKey($runId)->whereIn('status', ['queued', 'running'])->exists();
    }

    /**
     * A question from the AI as HQ keeps it: trimmed, suggestions de-duplicated (at most MAX_OPTIONS), and the
     * recommendation pointing at one of them; null when fewer than MIN_OPTIONS remain or the recommendation
     * points nowhere.
     */
    public static function clean(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $line = fn ($text) => trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        $question = $line($raw['question'] ?? '');

        $options = [];
        $index = []; // index in the AI's list => index kept
        $seen = [];
        foreach (array_values((array) ($raw['options'] ?? [])) as $i => $text) {
            $text = $line($text);
            $key = mb_strtolower($text);
            if ($text === '') {
                continue;
            }
            if (isset($seen[$key])) {
                $index[$i] = $seen[$key];

                continue;
            }
            if (count($options) < self::MAX_OPTIONS) {
                $index[$i] = $seen[$key] = count($options);
                $options[] = Str::limit($text, 1000, '');
            }
        }

        $recommended = $raw['recommended_option'] ?? null;
        if ($question === '' || count($options) < self::MIN_OPTIONS || ! is_int($recommended) || ! isset($index[$recommended])) {
            return null;
        }

        return [
            'question' => Str::limit($question, self::MAX_TEXT, ''),
            'why_it_matters' => Str::limit(trim((string) ($raw['why_it_matters'] ?? '')), self::MAX_TEXT, '') ?: null,
            'options' => $options,
            'recommended_option' => $index[$recommended],
            'recommendation_reason' => Str::limit(trim((string) ($raw['recommendation_reason'] ?? '')), self::MAX_TEXT, '') ?: null,
        ];
    }

    /** What the lead asked for, after the cached context */
    private static function instructions(int $count, array $areas, string $note): string
    {
        $lines = ["Draft exactly {$count} questions."];
        if ($areas) {
            $lines[] = 'Focus on: ' . implode('; ', array_map(fn ($a) => self::FOCUS[$a], $areas)) . '.';
        }
        if ($note !== '') {
            $lines[] = 'The lead adds: ' . $note;
        }

        return implode("\n", $lines);
    }

    /** The one question a redraft is about, the questionnaire's focus and the other questions' wording */
    private static function material(Questionnaire $questionnaire, QuestionnaireQuestion $question): string
    {
        $lines = [];
        if ($question->origin === 'lead') {
            $lines[] = 'THE LEAD’S OWN QUESTION (keep its wording exactly and suggest the answers):';
            $lines[] = 'Question: ' . $question->question;
            if (filled($question->why_it_matters)) {
                $lines[] = 'Why the lead asks: ' . $question->why_it_matters;
            }
        } else {
            $lines[] = 'THE QUESTION TO REPLACE:';
            $lines[] = 'Question: ' . $question->question;
            if (filled($question->why_it_matters)) {
                $lines[] = 'Why it mattered: ' . $question->why_it_matters;
            }
            foreach ((array) $question->options as $option) {
                $lines[] = 'Suggested answer: ' . $option;
            }
        }

        $focus = (array) $questionnaire->focus;
        $areas = array_map(fn ($a) => self::FOCUS[$a] ?? null, (array) ($focus['areas'] ?? []));
        if (array_filter($areas) || filled($focus['note'] ?? null)) {
            $lines[] = '';
            $lines[] = 'THE QUESTIONNAIRE’S FOCUS: ' . implode('; ', array_filter([...array_filter($areas), $focus['note'] ?? null]));
        }

        $others = QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)->whereKeyNot($question->id)->orderBy('position')->pluck('question');
        if ($others->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'THE OTHER QUESTIONS (don’t ask these again):';
            foreach ($others as $other) {
                $lines[] = '- ' . $other;
            }
        }

        return implode("\n", $lines);
    }

    /** The AI's whole draft becomes the questions (the first question_count usable ones) */
    private static function applyDraft(Questionnaire $questionnaire, AiRun $run): array
    {
        $ids = DB::transaction(function () use ($questionnaire, $run) {
            $locked = Questionnaire::whereKey($questionnaire->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'drafting' || $locked->ai_run_id !== $run->id) {
                return [];
            }
            $questions = array_slice(array_values(array_filter(array_map(self::clean(...), (array) ($run->output['questions'] ?? [])))), 0, $locked->question_count);
            if (! $questions) {
                // Nothing usable came back: the job counts as failed, so the lead can ask again
                $run->forceFill(['status' => 'failed', 'error' => 'ai.errors.invalid'])->save();

                return [];
            }

            $ids = [];
            foreach ($questions as $i => $question) {
                $ids[] = QuestionnaireQuestion::create($question + [
                    'questionnaire_id' => $locked->id, 'position' => $i + 1, 'origin' => 'ai',
                    'created_by' => $run->requested_by, 'updated_by' => $run->requested_by,
                ])->id;
            }
            $locked->forceFill(['status' => 'open'])->save();

            return $ids;
        });
        $questionnaire->refresh();

        return $ids;
    }

    /** A redrafted question replaces the old one (and clears its answer); suggestions for the lead's own keep it */
    private static function applyRedraft(string $questionId, string $runId): bool
    {
        return DB::transaction(function () use ($questionId, $runId) {
            $question = QuestionnaireQuestion::whereKey($questionId)->lockForUpdate()->first();
            $run = AiRun::find($runId, AiRun::RESULT);
            if (! $question || ! $run || $question->ai_run_id !== $runId) {
                return false;
            }
            $clean = self::clean($run->output);
            if (! $clean) {
                $run->forceFill(['status' => 'failed', 'error' => 'ai.errors.invalid'])->save();

                return false;
            }

            if ($question->origin === 'lead') {
                $question->fill(Arr::only($clean, ['options', 'recommended_option', 'recommendation_reason']));
                $question->why_it_matters ??= $clean['why_it_matters'];
            } else {
                $question->fill($clean + ['answer_option' => null, 'answer_text' => null, 'answered_by' => null, 'answered_at' => null]);
            }
            $question->forceFill(['ai_run_id' => null, 'updated_by' => $run->requested_by])->save();

            return true;
        });
    }

    /** A bell for the opportunity's lead (and so a phone push), opening the Questionnaire tab */
    private static function tellLead(Opportunity $opportunity, string $title, string $message): void
    {
        $lead = $opportunity->lead_user_id ? User::where('id', $opportunity->lead_user_id)->where('status', 'Active')->first() : null;
        if (! $lead) {
            return;
        }
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $lead->id,
            'type' => 'System',
            'title' => Str::limit($title, 149, '…'), // the column holds 150
            'message' => $message,
            'link_url' => route('hq.crm.opportunity', ['opportunity' => $opportunity->id, 'tab' => 'questionnaire']),
            'is_read' => 0,
            'created_at' => now(),
        ]);
    }

    private static function moveStage(Opportunity $opportunity, string $stage, User $by): void
    {
        $old = $opportunity->stage;
        $opportunity->forceFill(['stage' => $stage, 'updated_by' => $by->id])->save();
        OpportunityAccess::log('OPPORTUNITY_STAGE_CHANGED', "Moved “{$opportunity->title}” from {$old} to {$stage}.", 'opportunities', $opportunity->id,
            ['stage' => $old], ['stage' => $stage]);
    }
}
