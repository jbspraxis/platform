@props(['model', 'id', 'label', 'help' => null, 'clear', 'error' => null])
{{--
    A signature drawn with a finger, a pen or the mouse (PR 5: HQ's countersignature on the SOW, and the client's in the
    portal). The drawing stays in the browser (wire:ignore); each stroke puts the PNG as a data URL into the Livewire
    property named by $model, through a hidden input, so the form's own action sends it. Clear empties it.
--}}
<div class="field sigpad" x-data="{
        ctx: null, drawing: false,
        start(e) {
            const c = this.$refs.canvas;
            if (! this.ctx) {
                const r = c.getBoundingClientRect(), ratio = window.devicePixelRatio || 1;
                c.width = r.width * ratio; c.height = r.height * ratio;
                this.ctx = c.getContext('2d'); this.ctx.scale(ratio, ratio);
                this.ctx.lineWidth = 2; this.ctx.lineCap = 'round'; this.ctx.lineJoin = 'round'; this.ctx.strokeStyle = '#0C142B';
            }
            c.setPointerCapture(e.pointerId);
            this.drawing = true;
            const p = this.point(e); this.ctx.beginPath(); this.ctx.moveTo(p.x, p.y);
        },
        move(e) { if (! this.drawing) return; const p = this.point(e); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); },
        end() {
            if (! this.drawing) return;
            this.drawing = false;
            this.$refs.out.value = this.$refs.canvas.toDataURL('image/png');
            this.$refs.out.dispatchEvent(new Event('input', { bubbles: true }));
        },
        point(e) { const r = this.$refs.canvas.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; },
        clear() {
            const c = this.$refs.canvas;
            if (this.ctx) { this.ctx.save(); this.ctx.setTransform(1, 0, 0, 1, 0, 0); this.ctx.clearRect(0, 0, c.width, c.height); this.ctx.restore(); }
            this.$refs.out.value = ''; this.$refs.out.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }">
    <span class="label" id="{{ $id }}-label">{{ $label }}</span>
    <div class="sigpad-box" wire:ignore>
        <canvas x-ref="canvas" role="img" aria-labelledby="{{ $id }}-label" @if ($help) aria-describedby="{{ $id }}-help" @endif
                x-on:pointerdown.prevent="start($event)" x-on:pointermove.prevent="move($event)" x-on:pointerup="end()" x-on:pointercancel="end()" x-on:pointerleave="end()"></canvas>
    </div>
    <input type="hidden" x-ref="out" wire:model="{{ $model }}">
    <div class="sigpad-foot">
        @if ($help)
            <span class="help" id="{{ $id }}-help">{{ $help }}</span>
        @endif
        <button type="button" class="btn btn-ghost btn-sm" x-on:click="clear()"><i data-lucide="eraser"></i> {{ $clear }}</button>
    </div>
    @if ($error)
        <span class="field-error" role="alert">{{ $error }}</span>
    @endif
    @once
        <style>
            .sigpad-box { position: relative; height: 160px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); }
            .sigpad-box::after { content: ""; position: absolute; left: 16px; right: 16px; bottom: 36px; border-bottom: 1px solid var(--color-line); pointer-events: none; }
            .sigpad-box canvas { display: block; width: 100%; height: 100%; cursor: crosshair; touch-action: none; }
            .sigpad-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px 12px; }
            .sigpad-foot svg { width: 16px; height: 16px; }
        </style>
    @endonce
</div>
