{{-- An (i) beside a title or a line: the support text a person may want, closed by default. It opens on hover on a
     computer and on a tap on a phone (and with Enter or Space); Esc or a tap elsewhere closes it (docs/HQ_PHONE.md).
     The text stays on screen: when it would run past an edge it moves back in (fit()).
     $label names the button for screen readers ("About versions"); the slot is the text, one or two short sentences. --}}
@props(['label' => 'More information'])
<span {{ $attributes->merge(['class' => 'hq-info']) }}
      x-data="{ open: false, fit() { const tip = this.$refs.tip; tip.style.translate = '0px'; const r = tip.getBoundingClientRect(); if (!r.width) return; const over = r.right - (window.innerWidth - 12), under = 12 - r.left; tip.style.translate = (over > 0 ? -over : (under > 0 ? under : 0)) + 'px'; } }"
      :class="{ 'is-open': open }" @mouseenter="$nextTick(() => fit())" @focusin="$nextTick(() => fit())"
      @click.outside="open = false" @keydown.escape="open = false">
    <button type="button" class="hq-info-btn" aria-label="{{ $label }}" aria-expanded="false" :aria-expanded="open.toString()" @click="open = !open; $nextTick(() => fit())">
        <i data-lucide="info"></i>
    </button>
    <span class="hq-info-tip" role="note" x-ref="tip">{{ $slot }}</span>
</span>
