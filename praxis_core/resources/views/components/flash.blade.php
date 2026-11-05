{{--
    The toast host: every confirmation and error in HQ and the Client Portal arrives here, in the bottom-right
    corner, over whatever the person is looking at (docs/BRAND.md section 12). It sits in the layouts, so no screen
    has to ask for it and none can forget it.

    Messages waiting on a page load are drawn below and need no JavaScript. Messages flashed by a Livewire action
    arrive as the praxis-toast browser event (App\Support\Flash\ToastBridge) and are drawn by resources/js/toasts.js.
    Success and information close themselves after four seconds; warnings and errors stay until they are closed.
--}}
@once
    @php
        $toasts = \App\Support\Flash\Toasts::pull();
        $config = [
            'autoCloseMs' => \App\Support\Flash\Toasts::AUTO_CLOSE_MS,
            'max' => \App\Support\Flash\Toasts::MAX_VISIBLE,
            'close' => __('flash.close'),
            'tones' => \App\Support\Flash\Toasts::TONES,
        ];
    @endphp
    <div class="toast-host" data-toast-host data-toast-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}">
        @foreach ($toasts as $toast)
            @php [$class, $icon, $role, $closesItself] = \App\Support\Flash\Toasts::look($toast['tone']); @endphp
            {{-- is-in from the start: a message on a page load is readable before the script runs --}}
            <div class="alert {{ $class }} alert-toast is-in" role="{{ $role }}" data-toast @if ($closesItself) data-toast-auto @endif>
                <i data-lucide="{{ $icon }}"></i>
                <span>{{ $toast['message'] }}</span>
                <button type="button" class="alert-close" data-toast-close aria-label="{{ __('flash.close') }}">
                    <i data-lucide="x"></i>
                </button>
            </div>
        @endforeach
    </div>
@endonce
