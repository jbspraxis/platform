<?php

namespace App\Livewire\Portal;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Models\Tracker;
use App\Models\TrackerMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

#[Layout('layouts.client-light')]
class ProjectTracker extends Component
{
    use WithFileUploads;

    // ============================================================================
    // CORE STATE (Primitive Types Only)
    // ============================================================================
    
    public $token;
    public $trackerId;
    public $isAuthorized = false;
    public $isArchived = false;

    // PIN AUTHENTICATION
    public $pinInput = '';
    public $pinError = false;
    public $authAttempts = 0;
    public $maxAuthAttempts = 5;

    // CHAT STATE
    public $chatMessage = '';
    public $replyingToTask = null;
    public $mediaUpload;

    // RATE LIMITING
    protected $lastMessageTime = null;
    protected const MESSAGE_COOLDOWN = 1; // seconds

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
    // PIN AUTHENTICATION ENGINE
    // ============================================================================

    public function verifyPin($pinInput = null)
{
    // Rate limiting: Prevent brute force
    if ($this->authAttempts >= $this->maxAuthAttempts) {
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
            return;
        }

        usleep(500000); // 0.5s delay to prevent timing attacks
        
        $tracker = Tracker::findOrFail($this->trackerId);
        
        // Trim both sides to handle whitespace
        if (trim((string) $pin) === trim((string) $tracker->pin_code)) {
            session()->put('tracker_auth_' . $this->trackerId, true);
            $this->isAuthorized = true;
            $this->pinError = false;
            $this->authAttempts = 0;
            
            Log::info('Tracker Access Granted', [
                'trackerId' => $this->trackerId,
                'ip' => request()->ip()
            ]);
        } else {
            $this->pinError = true;
            $this->authAttempts++;
            $this->pinInput = '';
            
            Log::warning('Invalid PIN Attempt', [
                'trackerId' => $this->trackerId,
                'entered' => $pin,
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
    // MESSAGE TRANSMISSION & VALIDATION
    // ============================================================================

    public function transmitMessage()
    {
        // Security: Check authorization
        if (!$this->isAuthorized) {
            $this->dispatch('system-alert', ['message' => 'Unauthorized']);
            return;
        }

        // Security: Check if archived
        if ($this->isArchived) {
            $this->dispatch('system-alert', ['message' => 'Project archived. Transmission locked.']);
            return;
        }

        // Rate limiting
        $now = now();
        if ($this->lastMessageTime && $now->diffInSeconds($this->lastMessageTime) < self::MESSAGE_COOLDOWN) {
            $this->dispatch('system-alert', ['message' => 'Please wait before sending another message.']);
            return;
        }

        // Validation
        $this->validate([
            'chatMessage' => 'required_without:mediaUpload|string|max:1000|nullable',
            'mediaUpload' => 'nullable|file|mimes:jpg,jpeg,png,pdf,mp3,wav,m4a,webm|max:10240'
        ]);

        // Cannot send empty message without media
        if (empty(trim($this->chatMessage ?? '')) && !$this->mediaUpload) {
            $this->dispatch('system-alert', ['message' => 'Message cannot be empty.']);
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
            $this->dispatch('system-alert', ['message' => 'Failed to send message. Please try again.']);
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
            // Validate task exists and belongs to tracker
            $task = Tracker::findOrFail($this->trackerId)
                ->milestones()
                ->with('tasks')
                ->get()
                ->pluck('tasks')
                ->flatten()
                ->firstWhere('id', $taskId);

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
            // Validate payload structure
            if (!is_array($payload) || empty($payload)) {
                throw new \Exception('Invalid payload structure');
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
            $this->dispatch('system-alert', ['message' => 'Sync failed. Try again soon.']);
        }
    }

    // ============================================================================
    // DATA HYDRATION & RENDER
    // ============================================================================

    public function render()
    {
        try {
            // Eager load milestones, tasks, and the newly linked assignee
            $tracker = Tracker::with([
                'milestones' => function($q) { $q->orderBy('sort_order'); },
                'milestones.tasks' => function($q) { $q->orderBy('sort_order'); },
                'milestones.tasks.assignee' // Now successfully fetches from TrackerTask model
            ])->findOrFail($this->trackerId);
            
            $messages = [];
            $projectDeadline = null;
            
            // Only load sensitive data if authorized
            if ($this->isAuthorized) {
                // Fixed: Reverted staffSender back to sender to match your model
                $messages = TrackerMessage::with(['sender', 'task']) 
                    ->where('tracker_id', $this->trackerId)
                    ->orderBy('created_at', 'asc')
                    ->get();

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
                'projectDeadline' => $projectDeadline,
                'isAuthorized' => $this->isAuthorized,
                'isArchived' => $this->isArchived,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Render Error: ' . $e->getMessage());
            // Fixed: Safely abort instead of returning a raw string which crashes Livewire
            abort(500, 'Secure portal temporarily unavailable.');
        }
    }
}