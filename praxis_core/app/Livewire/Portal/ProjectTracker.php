<?php

namespace App\Livewire\Portal;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use App\Models\Tracker;
use App\Models\TrackerMessage;
use App\Models\TrackerTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

#[Layout('layouts.client-light')]
class ProjectTracker extends Component
{
    use WithFileUploads;
    use WithGatewayLanguage;

    // ============================================================================
    // CORE STATE (Primitive Types Only)
    // ============================================================================
    
    // Locked: set on the server only, so the browser cannot unlock the tracker
    #[Locked]
    public $token;
    #[Locked]
    public $trackerId;
    #[Locked]
    public $isAuthorized = false;
    #[Locked]
    public $isArchived = false;

    // PIN AUTHENTICATION (5 wrong PINs per tracker and IP lock it for 15 minutes)
    public $pinInput = '';
    public $pinError = false;
    #[Locked]
    public $authAttempts = 0;
    #[Locked]
    public $maxAuthAttempts = 5;

    // CHAT STATE
    public $chatMessage = '';
    public $replyingToTask = null;
    public $mediaUpload;

    // The latest messages are shown, older ones on "Show earlier messages" (a long project would send hundreds)
    public const MESSAGE_PAGE = 100;
    #[Locked]
    public $messageLimit = self::MESSAGE_PAGE;

    // RATE LIMITING
    protected $lastMessageTime = null;
    protected const MESSAGE_COOLDOWN = 1; // seconds

    // Validation messages in the page language (lang/<locale>/gateway.php)
    protected function messages(): array
    {
        return (array) __('gateway.validation.tracker');
    }

    // ============================================================================
    // MOUNT & INITIALIZATION
    // ============================================================================

    public function mount($token)
    {
        try {
            $this->token = $token;
            $tracker = Tracker::where('unique_token', $token)->firstOrFail();
            
            $this->trackerId = $tracker->id;
            $this->isArchived = $tracker->status === 'archived';

            // A tracker stores the client's name, not a link to the organisation: use that organisation's
            // preferred language when the name matches one, unless the person already chose a language
            $clientName = $tracker->client_company ?: $tracker->client_name;
            $this->useGatewayLanguage($clientName
                ? DB::table('organizations')->where('name', $clientName)->value('preferred_language')
                : null);

            // Check if already authenticated in session
            $this->isAuthorized = session()->has('tracker_auth_' . $this->trackerId);
            
        } catch (\Exception $e) {
            Log::warning('Tracker Mount Error: ' . $e->getMessage(), [
                'token' => substr($token, 0, 8) . '...',
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // PIN CHECK
    // ============================================================================

    public function verifyPin($pinInput = null)
{
    // Rate limiting: Prevent brute force (server-side, survives page reloads)
    $limiterKey = 'tracker-pin:' . $this->trackerId . ':' . request()->ip();
    if (RateLimiter::tooManyAttempts($limiterKey, $this->maxAuthAttempts)) {
        $this->pinError = true;
        Log::warning('Auth Attempts Exceeded', [
            'trackerId' => $this->trackerId,
            'ip' => request()->ip()
        ]);
        return;
    }

    try {
        // Use parameter if passed, otherwise use property
        $pin = $pinInput ?? $this->pinInput;
        
        if (!$pin || strlen(trim($pin)) !== 4) {
            $this->pinError = true;
            $this->authAttempts++;
            RateLimiter::hit($limiterKey, 900);
            return;
        }

        $pinCode = Tracker::whereKey($this->trackerId)->value('pin_code');

        // Trim both sides to handle whitespace; compared in constant time, so the timing gives nothing away
        if (hash_equals(trim((string) $pinCode), trim((string) $pin))) {
            session()->put('tracker_auth_' . $this->trackerId, true);
            $this->isAuthorized = true;
            $this->pinError = false;
            $this->authAttempts = 0;
            RateLimiter::clear($limiterKey);

            Log::info('Tracker Access Granted', [
                'trackerId' => $this->trackerId,
                'ip' => request()->ip()
            ]);
        } else {
            usleep(500000); // A wrong PIN still waits 0.5 s (on top of the 5-per-15-minutes limit); a right one does not
            $this->pinError = true;
            $this->authAttempts++;
            $this->pinInput = '';
            RateLimiter::hit($limiterKey, 900);

            Log::warning('Invalid PIN Attempt', [
                'trackerId' => $this->trackerId,
                'attempts' => $this->authAttempts,
                'ip' => request()->ip()
            ]);
        }
    } catch (\Exception $e) {
        $this->pinError = true;
        Log::error('PIN Verification Error: ' . $e->getMessage());
    }
}

    // ============================================================================
    // SENDING MESSAGES & VALIDATION
    // ============================================================================

    public function transmitMessage()
    {
        // Security: Check authorization
        if (!$this->isAuthorized) {
            $this->dispatch('system-alert', ['message' => __('gateway.tracker.pin_needed')]);
            return;
        }

        // Security: Check if archived
        if ($this->isArchived) {
            $this->dispatch('system-alert', ['message' => __('gateway.tracker.archived_send')]);
            return;
        }

        // Rate limiting
        $now = now();
        if ($this->lastMessageTime && $now->diffInSeconds($this->lastMessageTime) < self::MESSAGE_COOLDOWN) {
            $this->dispatch('system-alert', ['message' => __('gateway.tracker.wait')]);
            return;
        }

        // Validation
        $this->validate([
            'chatMessage' => 'required_without:mediaUpload|string|max:1000|nullable',
            'mediaUpload' => 'nullable|file|mimes:jpg,jpeg,png,pdf,mp3,wav,m4a,webm|max:10240'
        ]);

        // Cannot send empty message without media
        if (empty(trim($this->chatMessage ?? '')) && !$this->mediaUpload) {
            $this->dispatch('system-alert', ['message' => __('gateway.tracker.empty_message')]);
            return;
        }

        try {
            $mediaPath = null;
            $mediaType = null;

            // Process media upload
            if ($this->mediaUpload) {
                $mediaPath = $this->processMediaUpload();
                $mediaType = $this->detectMediaType($this->mediaUpload);
            }

            // Create message
            TrackerMessage::create([
                'tracker_id' => $this->trackerId,
                'task_id' => $this->replyingToTask,
                'sender_type' => 'client',
                'sender_user_id' => null,
                'body' => $this->chatMessage ?: null,
                'media_path' => $mediaPath,
                'media_type' => $mediaType,
            ]);

            // Reset state
            $this->resetMessageState();
            $this->lastMessageTime = $now;
            
            Log::info('Message Transmitted', [
                'trackerId' => $this->trackerId,
                'hasMedia' => $mediaPath ? true : false,
                'mediaType' => $mediaType
            ]);

            $this->dispatch('message-transmitted');
            
        } catch (\Exception $e) {
            Log::error('Message Transmission Failed: ' . $e->getMessage(), [
                'trackerId' => $this->trackerId,
                'error' => $e
            ]);
            $this->dispatch('system-alert', ['message' => __('gateway.tracker.send_failed')]);
        }
    }

    protected function processMediaUpload()
    {
        try {
            if (!$this->mediaUpload) {
                return null;
            }

            // Validate file exists and is readable
            if (!$this->mediaUpload->isValid()) {
                throw new \Exception('File upload failed validation');
            }

            // Store to public disk with unique name
            $path = $this->mediaUpload->storePublicly(
                'tracker_media/' . $this->trackerId,
                'public'
            );

            if (!$path) {
                throw new \Exception('Failed to store file');
            }

            return $path;
            
        } catch (\Exception $e) {
            Log::error('Media Upload Processing Error: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function detectMediaType($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        $typeMap = [
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image',
            'pdf' => 'document',
            'mp3' => 'voice', 'wav' => 'voice', 'm4a' => 'voice', 'webm' => 'voice'
        ];

        return $typeMap[$extension] ?? 'document';
    }

    protected function resetMessageState()
    {
        $this->chatMessage = '';
        $this->mediaUpload = null;
        $this->replyingToTask = null;
    }

    // ============================================================================
    // REPLY CONTEXT MANAGEMENT
    // ============================================================================

    public function setReplyContext($taskId)
    {
        try {
            // Validate task exists and belongs to tracker (one query, not the whole milestone tree)
            $task = TrackerTask::whereKey($taskId)
                ->whereHas('milestone', fn ($q) => $q->where('tracker_id', $this->trackerId))
                ->exists();

            if (!$task) {
                throw new \Exception('Task not found');
            }

            $this->replyingToTask = $taskId;
            $this->dispatch('focus-chat-input');
            
        } catch (\Exception $e) {
            Log::warning('Set Reply Context Error: ' . $e->getMessage());
        }
    }

    public function clearReplyContext()
    {
        $this->replyingToTask = null;
    }

    // ============================================================================
    // OLDER MESSAGES
    // ============================================================================

    public function showEarlierMessages()
    {
        if ($this->isAuthorized) {
            $this->messageLimit += self::MESSAGE_PAGE;
        }
    }

    // ============================================================================
    // OFFLINE SYNC ENGINE
    // ============================================================================

    public function syncOfflinePayload($payload)
    {
        // Security: Check authorization
        if (!$this->isAuthorized) {
            Log::warning('Offline Sync: Unauthorized attempt');
            return;
        }

        // Security: Check if archived
        if ($this->isArchived) {
            return;
        }

        try {
            // The browser sends a list of messages written while offline
            if (!is_array($payload) || empty($payload)) {
                throw new \Exception('Offline sync: no list of messages received');
            }

            $synced = 0;
            $errors = 0;

            foreach ($payload as $offlineMessage) {
                try {
                    // Validate required fields
                    if (!isset($offlineMessage['body']) || !isset($offlineMessage['timestamp'])) {
                        $errors++;
                        continue;
                    }

                    // Create message with validated data
                    TrackerMessage::create([
                        'tracker_id' => $this->trackerId,
                        'task_id' => $offlineMessage['taskId'] ?? null,
                        'sender_type' => 'client',
                        'sender_user_id' => null,
                        'body' => $offlineMessage['body'],
                        'created_at' => Carbon::parse($offlineMessage['timestamp']),
                        'updated_at' => now(),
                    ]);

                    $synced++;
                    
                } catch (\Exception $e) {
                    $errors++;
                    Log::warning('Offline Message Sync Error: ' . $e->getMessage());
                    continue;
                }
            }

            Log::info('Offline Sync Complete', [
                'trackerId' => $this->trackerId,
                'synced' => $synced,
                'errors' => $errors
            ]);

            $this->dispatch('offline-sync-complete');
            
        } catch (\Exception $e) {
            Log::error('Offline Sync Failed: ' . $e->getMessage());
            $this->dispatch('system-alert', ['message' => __('gateway.tracker.sync_failed')]);
        }
    }

    // ============================================================================
    // DATA HYDRATION & RENDER
    // ============================================================================

    public function render()
    {
        try {
            // The PIN screen shows only the project's name: nothing else is read until the PIN is right
            if (!$this->isAuthorized) {
                return view('livewire.portal.project-tracker', [
                    'tracker' => Tracker::select('id', 'project_name')->findOrFail($this->trackerId),
                    'milestones' => collect(),
                    'messages' => [],
                    'hasEarlierMessages' => false,
                    'projectDeadline' => null,
                    'isAuthorized' => false,
                    'isArchived' => $this->isArchived,
                ])->title(__('gateway.tracker.page_title'));
            }

            // Eager load milestones, tasks, and the newly linked assignee
            $tracker = Tracker::with([
                'milestones' => function($q) { $q->orderBy('sort_order'); },
                'milestones.tasks' => function($q) { $q->orderBy('sort_order'); },
                'milestones.tasks.assignee:id,name' // Now successfully fetches from TrackerTask model
            ])->findOrFail($this->trackerId);
            
            $messages = [];
            $hasEarlierMessages = false;
            $projectDeadline = null;
            
            // Only load sensitive data if authorized
            if ($this->isAuthorized) {
                // The latest messages (one more than shown, to know whether earlier ones exist), oldest first,
                // with only what a bubble shows
                $messages = TrackerMessage::with(['sender:id,name', 'task:id,title'])
                    ->select('id', 'task_id', 'sender_type', 'sender_user_id', 'body', 'media_path', 'media_type', 'created_at')
                    ->where('tracker_id', $this->trackerId)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit((int) $this->messageLimit + 1)
                    ->get();
                $hasEarlierMessages = $messages->count() > $this->messageLimit;
                $messages = $messages->take((int) $this->messageLimit)->reverse()->values();

                // Project deadline falls back to max task due_date if target_end_date is null
                $projectDeadline = $tracker->target_end_date 
                    ? Carbon::parse($tracker->target_end_date)
                    : TrackerTask::whereHas('milestone', function($q) {
                        $q->where('tracker_id', $this->trackerId);
                    })->max('due_date');
                
                if ($projectDeadline && !$projectDeadline instanceof Carbon) {
                    $projectDeadline = Carbon::parse($projectDeadline);
                }
            }

            return view('livewire.portal.project-tracker', [
                'tracker' => $tracker,
                'milestones' => $tracker->milestones,
                'messages' => $messages,
                'hasEarlierMessages' => $hasEarlierMessages,
                'projectDeadline' => $projectDeadline,
                'isAuthorized' => $this->isAuthorized,
                'isArchived' => $this->isArchived,
            ])->title(__('gateway.tracker.page_title'));
            
        } catch (\Exception $e) {
            Log::error('Render Error: ' . $e->getMessage());
            // Fixed: Safely abort instead of returning a raw string which crashes Livewire
            abort(500, __('gateway.tracker.unavailable'));
        }
    }
}