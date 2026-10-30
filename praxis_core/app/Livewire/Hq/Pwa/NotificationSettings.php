<?php

namespace App\Livewire\Hq\Pwa;

use App\Http\Middleware\HqStaff;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Pwa\PushCategories;
use App\Support\Pwa\PushPreferences;
use App\Support\Pwa\Pwa;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Notification settings (docs/PWA.md): a slide-over opened from the bell menu (browser event
 * open-notification-settings). "This device" is drawn by hq-pwa.js; the person's own devices and the groups
 * they want notifications for load when the panel opens, so it costs nothing on the pages where it stays shut.
 * Push addresses and keys never reach the page: devices go out as ids, labels and dates only.
 */
class NotificationSettings extends Component
{
    /** The lists are loaded (the panel has been opened) */
    #[Locked]
    public bool $loaded = false;

    /** sha256 of this browser's push address, sent by hq-pwa.js to mark "This device" */
    #[Locked]
    public ?string $thisDevice = null;

    /** group => true when its notifications are on, false when muted */
    public array $groups = [];

    public function open(?string $deviceHash = null): void
    {
        $this->thisDevice = is_string($deviceHash) && preg_match('/^[a-f0-9]{64}$/', $deviceHash) ? $deviceHash : null;

        $user = $this->staff();
        $muted = PushPreferences::muted($user->id);
        $this->groups = [];
        foreach (array_keys(PushCategories::for($user)) as $group) {
            $this->groups[$group] = ! in_array($group, $muted, true);
        }
        $this->loaded = true;
    }

    /** A switch changed: only the groups this person is offered (System health needs system.view). */
    public function updatedGroups($value, $group): void
    {
        $user = $this->staff();
        abort_unless(is_string($group) && array_key_exists($group, PushCategories::for($user)), 403);

        $muted = PushPreferences::set($user->id, $group, (bool) $value);
        $this->groups[$group] = ! in_array($group, $muted, true);
    }

    /** Forget one of the person's devices (another person's, or an unknown id, changes nothing). */
    public function removeDevice($id): void
    {
        PushSubscription::where('user_id', $this->staff()->id)->whereKey((int) $id)->delete();
    }

    /** The signed-in, active staff member (Livewire updates don't pass through the hq.staff middleware). */
    private function staff(): User
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User && HqStaff::allows($user), 403);

        return $user;
    }

    public function render()
    {
        $devices = collect();
        $labels = [];
        if ($this->loaded) {
            $user = $this->staff();
            $labels = PushCategories::for($user);
            $devices = PushSubscription::where('user_id', $user->id)
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id')
                ->get(['id', 'endpoint_hash', 'device', 'standalone', 'created_at', 'last_seen_at'])
                ->map(fn (PushSubscription $device) => [
                    'id' => $device->id,
                    'label' => $device->device ?: 'Unnamed device',
                    'phone' => (bool) preg_match('/iPhone|iPad|Android/i', (string) $device->device),
                    'this' => $this->thisDevice !== null && hash_equals((string) $device->endpoint_hash, $this->thisDevice),
                    // A date as the brand writes it (docs/BRAND.md section 4), in West Africa Time
                    'added' => $device->created_at?->copy()->setTimezone('Africa/Lagos')->locale('en')->isoFormat('D MMMM YYYY'),
                    'seen' => ($device->last_seen_at ?? $device->created_at)?->diffForHumans(),
                ]);
        }

        return view('livewire.hq.pwa.notification-settings', [
            'devices' => $devices,
            'labels' => $labels,
            'version' => Pwa::version(),
        ]);
    }
}
