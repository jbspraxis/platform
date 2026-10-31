<?php

namespace App\Livewire\Hq\System;

use App\Support\Ai\Ai;
use App\Support\Ai\AiUnavailable;
use App\Support\Ai\Budget;
use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Integrations as Settings;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * HQ › System › Integrations (docs/OPPORTUNITIES.md 6): the Google connection to praxisjbs@gmail.com, the Claude
 * API key and the monthly AI budget, with this month's AI spend per purpose. integrations.manage (the owner).
 *
 * Secrets go one way: the client secret and the Claude key are typed, saved encrypted and cleared from the page;
 * the screen only ever says whether one is saved. Every change is in the audit log, without the value.
 */
#[Layout('components.layouts.hq')]
class Integrations extends Component
{
    public string $clientId = '';

    public string $clientSecret = '';

    public string $anthropicKey = '';

    public string $budget = '';

    #[Locked]
    public ?string $notice = null;

    #[Locked]
    public string $noticeTone = 'success';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('integrations.manage'), 403);
        $this->clientId = (string) Settings::get('google.client_id');
        $this->budget = number_format(Budget::cap(), 2, '.', '');
    }

    protected function rules(): array
    {
        return [
            'clientId' => ['required', 'string', 'max:255', 'regex:/^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$/i'],
            'clientSecret' => ['nullable', 'string', 'max:255'],
            'anthropicKey' => ['required', 'string', 'max:255', 'regex:/^sk-ant-[A-Za-z0-9_\-]{20,}$/'],
            'budget' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'clientId.required' => __('integrations.google.errors.client_id_required'),
            'clientId.regex' => __('integrations.google.errors.client_id_format'),
            'anthropicKey.required' => __('integrations.claude.errors.key_required'),
            'anthropicKey.regex' => __('integrations.claude.errors.key_format'),
            'budget.required' => __('integrations.budget.errors.amount'),
            'budget.numeric' => __('integrations.budget.errors.amount'),
            'budget.min' => __('integrations.budget.errors.amount'),
            'budget.max' => __('integrations.budget.errors.amount'),
        ];
    }

    public function saveGoogleClient(GoogleWorkspace $google): void
    {
        $this->authorizeManage();
        $this->validateOnly('clientId');
        $secretSaved = Settings::saved('google.client_secret');
        if (trim($this->clientSecret) === '' && ! $secretSaved) {
            $this->addError('clientSecret', __('integrations.google.errors.secret_required'));

            return;
        }

        $changedClient = Settings::get('google.client_id') !== trim($this->clientId) || trim($this->clientSecret) !== '';
        Settings::set('google.client_id', $this->clientId);
        if (trim($this->clientSecret) !== '') {
            Settings::set('google.client_secret', $this->clientSecret);
        }
        $this->clientSecret = '';
        // A new client can't use the old client's refresh token
        if ($changedClient && Settings::get('google.refresh_token') !== null) {
            $google->disconnect();
        }

        OpportunityAccess::log('INTEGRATION_GOOGLE_CLIENT_SAVED', 'Saved the Google OAuth client ID and secret.', 'integration_settings', 'google.client_id');
        $this->say(__('integrations.google.flash.client_saved'));
    }

    public function testGoogle(GoogleWorkspace $google): void
    {
        $this->authorizeManage();
        try {
            $email = $google->test();
            $this->say(__('integrations.google.flash.test_ok', ['email' => $email]));
        } catch (GoogleUnavailable $e) {
            $this->say($e->getMessage(), 'error');
        }
    }

    public function disconnectGoogle(GoogleWorkspace $google): void
    {
        $this->authorizeManage();
        $google->disconnect();
        OpportunityAccess::log('INTEGRATION_GOOGLE_DISCONNECTED', 'Disconnected Google.', 'integration_settings', 'google.refresh_token');
        $this->say(__('integrations.google.flash.disconnected'));
    }

    public function saveAnthropicKey(): void
    {
        $this->authorizeManage();
        $this->anthropicKey = trim($this->anthropicKey);
        $this->validateOnly('anthropicKey');
        Settings::set('anthropic.api_key', $this->anthropicKey);
        $this->anthropicKey = '';
        OpportunityAccess::log('INTEGRATION_CLAUDE_KEY_SAVED', 'Saved the Claude API key.', 'integration_settings', 'anthropic.api_key');
        $this->say(__('integrations.claude.flash.saved'));
    }

    public function removeAnthropicKey(): void
    {
        $this->authorizeManage();
        Settings::set('anthropic.api_key', null);
        OpportunityAccess::log('INTEGRATION_CLAUDE_KEY_REMOVED', 'Removed the Claude API key.', 'integration_settings', 'anthropic.api_key');
        $this->say(__('integrations.claude.flash.removed'));
    }

    /** One tiny Haiku request, logged in ai_runs like any other (a fraction of a cent) */
    public function testClaude(): void
    {
        $this->authorizeManage();
        try {
            $run = Ai::now('connection_test', ['type' => 'integration', 'id' => null], []);
        } catch (AiUnavailable $e) {
            $this->say($e->getMessage(), 'error');

            return;
        }
        $run->succeeded()
            ? $this->say(__('integrations.claude.flash.test_ok', ['model' => $run->model, 'cost' => number_format($run->cost_usd, 4)]))
            : $this->say((string) $run->errorMessage(), 'error');
    }

    public function saveBudget(): void
    {
        $this->authorizeManage();
        $this->validateOnly('budget');
        $old = Budget::cap();
        Settings::set('ai.monthly_budget_usd', number_format((float) $this->budget, 2, '.', ''));
        $this->budget = number_format(Budget::cap(), 2, '.', '');
        OpportunityAccess::log('INTEGRATION_AI_BUDGET_SAVED', "Set the monthly AI budget to \${$this->budget}.", 'integration_settings', 'ai.monthly_budget_usd',
            ['usd' => $old], ['usd' => (float) $this->budget]);
        $this->say(__('integrations.budget.flash.saved', ['amount' => $this->budget]));
    }

    private function authorizeManage(): void
    {
        abort_unless(Auth::user()?->can('integrations.manage'), 403);
        $this->resetErrorBag();
        $this->notice = null;
    }

    private function say(string $message, string $tone = 'success'): void
    {
        $this->notice = $message;
        $this->noticeTone = $tone;
    }

    public function render(GoogleWorkspace $google)
    {
        $cap = Budget::cap();
        $spent = Budget::spent();

        return view('livewire.hq.system.integrations', [
            'google' => [
                'hasClient' => $google->hasClient(),
                'secretSaved' => Settings::saved('google.client_secret') || config('services.google.client_secret'),
                'connected' => $google->isConnected(),
                'reconnect' => $google->needsReconnect(),
                'email' => $google->accountEmail(),
                'missing' => $google->isConnected() ? $google->missingScopes() : [],
                'redirectUri' => GoogleWorkspace::redirectUri(),
                'expected' => (string) config('services.google.account'),
            ],
            'claude' => [
                'saved' => Settings::get('anthropic.api_key') !== null,
                'fromEnv' => ! Settings::saved('anthropic.api_key') && Settings::get('anthropic.api_key') !== null,
                'savedAt' => Settings::updatedAt('anthropic.api_key'),
            ],
            'gemini' => (string) config('services.gemini.api_key') !== '',
            'spend' => [
                'cap' => $cap,
                'spent' => $spent,
                'percent' => $cap > 0 ? min(100, (int) round(100 * $spent / $cap)) : 100,
                'byPurpose' => Budget::spentByPurpose(),
            ],
        ])->title(__('integrations.title'));
    }
}
