<?php

namespace App\Livewire\Hq\Opportunities;

use App\Models\AiRun;
use App\Models\Opportunity;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Support\Ai\AiUnavailable;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\Questionnaires;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The opportunity page's Questionnaire tab (docs/OPPORTUNITIES.md section 4, row 3). The lead chooses how many
 * questions and the focus, the AI drafts them in the background, and the lead answers as they go (each choice and
 * each pause in typing is saved), redrafts or adds questions, then marks it complete. Everyone who can open
 * opportunities can read it; only the lead changes it (Questionnaires::canAnswer). While the AI works, the tab
 * checks every 3 seconds.
 */
class QuestionnaireTab extends Component
{
    #[Locked]
    public string $opportunityId;

    #[Locked]
    public bool $canAnswer = false;

    // Drafting
    public $questionCount = Questionnaires::DEFAULT_QUESTIONS;

    public array $focusAreas = [];

    public string $focusNote = '';

    /** The lead's answers as they go: question id => ['choice' => '' | 'own' | '0', '1', …, 'text' => detail or own answer] */
    public array $answers = [];

    // Redraft one question (or suggest answers to the lead's own)
    #[Locked]
    public ?string $redraftingId = null;

    public string $redraftNote = '';

    // Add a question
    public bool $adding = false;

    public string $newQuestion = '';

    public string $newWhy = '';

    /** What was just done, announced politely */
    #[Locked]
    public string $notice = '';

    /** This screen showed the draft in progress, so it says when the questions are in (whoever filed them) */
    #[Locked]
    public bool $awaitingDraft = false;

    /** Where the AI's work stood when the tab was last drawn: the poll draws it again only once that moves on (check()) */
    #[Locked]
    public string $polled = '';

    public function mount(string $opportunityId): void
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        abort_unless(OpportunityAccess::canView(Auth::user(), $opportunity), 403);
        $this->opportunityId = $opportunity->id;
        $this->canAnswer = Questionnaires::canAnswer(Auth::user(), $opportunity);

        // A draft that failed keeps what was asked, so trying again is one click
        $questionnaire = Questionnaires::current($opportunity);
        if ($questionnaire?->status === 'drafting') {
            $this->questionCount = $questionnaire->question_count;
            $this->focusAreas = (array) ($questionnaire->focus['areas'] ?? []);
            $this->focusNote = (string) ($questionnaire->focus['note'] ?? '');
        }
    }

    public function draft(): void
    {
        $opportunity = $this->authorizeAnswer();
        $this->focusNote = trim($this->focusNote);
        $this->validate([
            'questionCount' => ['required', 'integer', 'min:' . Questionnaires::MIN_QUESTIONS, 'max:' . Questionnaires::MAX_QUESTIONS],
            'focusAreas' => ['array'],
            'focusAreas.*' => [Rule::in(array_keys(Questionnaires::FOCUS))],
            'focusNote' => ['nullable', 'string', 'max:1000'],
        ], [
            'questionCount.*' => __('questionnaire.errors.count', ['min' => Questionnaires::MIN_QUESTIONS, 'max' => Questionnaires::MAX_QUESTIONS]),
            'focusAreas.*' => __('questionnaire.errors.focus'),
            'focusNote.max' => __('questionnaire.errors.note_long'),
        ]);

        try {
            $stage = $opportunity->stage;
            $questionnaire = Questionnaires::draft($opportunity, (int) $this->questionCount, $this->focusAreas, $this->focusNote, Auth::user());
            if ($opportunity->fresh()->stage !== $stage) {
                $this->dispatch('opportunity-stage-changed');
            }
            if ($questionnaire->status === 'open') {
                $this->draftedNotice($questionnaire); // the job already ran (a worker was free)
            }
        } catch (AiUnavailable $e) {
            $this->addError('draft', $e->getMessage());
        }
    }

    /** "10 questions drafted", or how many fewer than asked came back */
    private function draftedNotice(Questionnaire $questionnaire): void
    {
        $count = $questionnaire->questions()->count();
        $this->notice = $count < $questionnaire->question_count
            ? __('questionnaire.fewer', ['drafted' => $count, 'asked' => $questionnaire->question_count])
            : trans_choice('questionnaire.drafted', $count, ['count' => $count]);
    }

    /** Saves an answer as soon as a suggestion is picked or the lead pauses typing */
    public function updatedAnswers($value, string $key): void
    {
        $id = explode('.', $key, 2)[0];
        $this->authorizeAnswer();
        $question = $this->question($id);
        $entry = (array) ($this->answers[$id] ?? []);
        $text = (string) ($entry['text'] ?? '');
        if (mb_strlen($text) > Questionnaires::MAX_TEXT) {
            $this->addError("answers.{$id}.text", __('questionnaire.errors.answer_long'));

            return;
        }
        $choice = (string) ($entry['choice'] ?? '');
        if (! Questionnaires::answer($question, ctype_digit($choice) ? (int) $choice : null, $text, Auth::user())) {
            // Being redrafted, or the questionnaire is no longer open: say so, and show what is saved
            // (at the top of the tab: a question being redrafted shows no answer box to put it under)
            unset($this->answers[$id]);
            $this->addError('answer', __('questionnaire.errors.not_saved'));
        }
    }

    public function openRedraft(string $id): void
    {
        $this->authorizeAnswer();
        $this->redraftingId = $this->question($id)->id;
        $this->redraftNote = '';
        $this->adding = false;
    }

    public function cancelRedraft(): void
    {
        $this->redraftingId = null;
        $this->redraftNote = '';
        $this->resetErrorBag();
    }

    public function redraft(): void
    {
        $this->authorizeAnswer();
        $question = $this->question((string) $this->redraftingId);
        $this->redraftNote = trim($this->redraftNote);
        $this->validate(['redraftNote' => ['nullable', 'string', 'max:1000']], ['redraftNote.max' => __('questionnaire.errors.note_long')]);

        try {
            Questionnaires::redraft($question, $this->redraftNote, Auth::user());
            unset($this->answers[$question->id]); // show what is saved: the old answer while it runs, none once replaced
            $this->cancelRedraft();
        } catch (AiUnavailable $e) {
            $this->addError('redraft', $e->getMessage());
        }
    }

    public function dismissFailure(string $id): void
    {
        $this->authorizeAnswer();
        Questionnaires::dismissFailure($this->question($id));
    }

    public function openAdd(): void
    {
        $this->authorizeAnswer();
        $this->adding = true;
        $this->redraftingId = null;
    }

    public function cancelAdd(): void
    {
        $this->reset('adding', 'newQuestion', 'newWhy');
        $this->resetErrorBag();
    }

    public function addQuestion(): void
    {
        $this->authorizeAnswer();
        $this->newQuestion = trim($this->newQuestion);
        $this->newWhy = trim($this->newWhy);
        $this->validate([
            'newQuestion' => ['required', 'string', 'max:1000'],
            'newWhy' => ['nullable', 'string', 'max:1000'],
        ], [
            'newQuestion.required' => __('questionnaire.errors.question'),
            'newQuestion.max' => __('questionnaire.errors.question_long'),
            'newWhy.max' => __('questionnaire.errors.note_long'),
        ]);

        $questionnaire = $this->questionnaire();
        $question = $questionnaire ? Questionnaires::addQuestion($questionnaire, $this->newQuestion, $this->newWhy, Auth::user()) : null;
        if ($question) {
            $this->cancelAdd();
            $this->notice = __('questionnaire.add.done', ['n' => $question->position]);
        }
    }

    public function remove(string $id): void
    {
        $this->authorizeAnswer();
        $question = $this->question($id);
        Questionnaires::removeQuestion($question, Auth::user());
        unset($this->answers[$id]);
        if ($this->redraftingId === $id) {
            $this->cancelRedraft();
        }
        $this->notice = __('questionnaire.question.removed', ['n' => $question->position]);
    }

    public function complete(): void
    {
        $this->authorizeAnswer();
        $questionnaire = $this->questionnaire();
        if ($questionnaire && Questionnaires::complete($questionnaire, Auth::user())) {
            $this->reset('redraftingId', 'redraftNote', 'adding', 'newQuestion', 'newWhy');
            $this->notice = __('questionnaire.complete.done');
            $this->dispatch('opportunity-stage-changed'); // the page's header and Move to show the new stage
        } elseif ($questionnaire?->status === 'open') {
            $questions = $questionnaire->questions()->get();
            $left = $questions->reject(fn (QuestionnaireQuestion $q) => $q->isAnswered())->count();
            $this->addError('complete', match (true) {
                $questions->isEmpty() => __('questionnaire.errors.empty'),
                $left > 0 => trans_choice('questionnaire.errors.incomplete', $left, ['count' => $left]),
                default => __('questionnaire.errors.redrafting'),
            });
        }
    }

    public function restart(): void
    {
        $this->authorizeAnswer();
        $questionnaire = $this->questionnaire();
        if ($questionnaire?->status !== 'open') {
            return;
        }
        Questionnaires::restart($questionnaire, Auth::user());
        $this->reset('answers', 'redraftingId', 'redraftNote', 'adding', 'newQuestion', 'newWhy');
        // The form starts from what was asked last time
        $this->fill(['questionCount' => $questionnaire->question_count, 'focusAreas' => (array) ($questionnaire->focus['areas'] ?? []),
            'focusNote' => (string) ($questionnaire->focus['note'] ?? '')]);
        $this->notice = __('questionnaire.restart.done');
    }

    /**
     * wire:poll while the AI works: three small queries, and the tab is drawn again (render() applies what the AI
     * finished and says the questions are in) only when the draft or a redraft moved on
     */
    public function check(): void
    {
        $questionnaire = Questionnaire::where('opportunity_id', $this->opportunityId)->latest()->first(['id', 'status', 'ai_run_id']);
        $questions = $questionnaire ? QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)->whereNotNull('ai_run_id')->get(['id', 'ai_run_id']) : collect();
        $ids = array_filter([$questionnaire?->ai_run_id, ...$questions->pluck('ai_run_id')->all()]);
        $runs = $ids ? AiRun::whereKey($ids)->get(['id', 'status', 'progress'])->keyBy('id') : collect();
        $redrafts = $questions->map(fn ($q) => [$q, $runs->get($q->ai_run_id)]);
        if ($questionnaire && self::stamp($questionnaire, $runs->get((string) $questionnaire->ai_run_id), $redrafts) === $this->polled) {
            $this->skipRender();
        }
    }

    /** The questionnaire, its run and the questions being redrafted with theirs, as one short string (the same from check() and from render()) */
    private static function stamp(Questionnaire $questionnaire, ?AiRun $run, iterable $redrafts): string
    {
        $state = fn (?AiRun $r) => $r ? [$r->id, $r->status, (int) $r->progress] : null;
        $redrafts = collect($redrafts)->map(fn ($pair) => [$pair[0]->id, $pair[0]->ai_run_id, $state($pair[1])])->sortBy(0)->values()->all();

        return md5(json_encode([$questionnaire->id, $questionnaire->status, $questionnaire->ai_run_id, $state($run), $redrafts]));
    }

    private function authorizeAnswer(): Opportunity
    {
        $opportunity = Opportunity::findOrFail($this->opportunityId);
        abort_unless(Questionnaires::canAnswer(Auth::user(), $opportunity), 403);
        $this->notice = '';
        $this->resetErrorBag();

        return $opportunity;
    }

    private function questionnaire(): ?Questionnaire
    {
        return Questionnaires::current(Opportunity::findOrFail($this->opportunityId));
    }

    /** A question of this opportunity's questionnaire, or 404 */
    private function question(string $id): QuestionnaireQuestion
    {
        return QuestionnaireQuestion::where('questionnaire_id', $this->questionnaire()?->id)->whereKey($id)->firstOrFail();
    }

    public function render()
    {
        $opportunity = Opportunity::with('lead')->findOrFail($this->opportunityId);
        $questionnaire = Questionnaires::current($opportunity);

        if ($questionnaire) {
            $wasDrafting = $questionnaire->status === 'drafting';
            foreach (Questionnaires::sync($questionnaire) as $id) {
                unset($this->answers[$id]); // rewritten by the AI: show what is saved now
            }
            $questionnaire->load(['aiRun', 'completedBy', 'questions.aiRun']);
            // Filed here, or by FileQuestionnaireAi as the job finished
            if (($wasDrafting || $this->awaitingDraft) && $questionnaire->status === 'open') {
                $this->draftedNotice($questionnaire);
            }
            foreach ($questionnaire->questions as $q) {
                $this->answers[$q->id] ??= [
                    'choice' => $q->answer_option !== null ? (string) $q->answer_option : (filled($q->answer_text) ? 'own' : ''),
                    'text' => (string) $q->answer_text,
                ];
            }
        }

        $run = $questionnaire?->aiRun;
        $drafting = $questionnaire?->status === 'drafting' && $run && ! $run->isFinished();
        $this->awaitingDraft = $drafting;
        $questions = $questionnaire && $questionnaire->status !== 'drafting' ? $questionnaire->questions : collect();
        $redrafting = $questions->filter(fn (QuestionnaireQuestion $q) => $q->aiRun && ! $q->aiRun->isFinished());
        $polling = $drafting || $redrafting->isNotEmpty();
        $this->polled = $polling ? self::stamp($questionnaire, $run, $questionnaire->questions->whereNotNull('ai_run_id')->map(fn ($q) => [$q, $q->aiRun])) : '';
        $showForm = ! $questionnaire || ($questionnaire->status === 'drafting' && ! $drafting);

        return view('livewire.hq.opportunities.questionnaire-tab', [
            'o' => $opportunity,
            'questionnaire' => $questionnaire,
            'questions' => $questions,
            'run' => $run,
            'drafting' => $drafting,
            // A draft that failed (or whose job is gone) shows its message above the form
            'draftError' => $questionnaire?->status === 'drafting' && ! $drafting ? ($run?->errorMessage() ?? __('ai.errors.failed')) : null,
            'showForm' => $showForm,
            'polling' => $polling,
            'answered' => $questions->filter(fn (QuestionnaireQuestion $q) => $q->isAnswered())->count(),
            // The form says when no meeting has notes yet
            'hasNotes' => ! $showForm || ! $this->canAnswer || DB::table('meeting_transcripts')->join('meetings', 'meetings.id', '=', 'meeting_transcripts.meeting_id')
                ->where('meetings.opportunity_id', $opportunity->id)->whereNull('meetings.deleted_at')->whereNull('meeting_transcripts.deleted_at')->whereNotNull('meeting_transcripts.notes')->exists(),
            'lead' => $opportunity->lead ? trim($opportunity->lead->first_name . ' ' . $opportunity->lead->last_name) : null,
        ]);
    }
}
