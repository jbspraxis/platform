{{-- The Praxis rule: Ink to the left of the split, Teal to the right of it. No whitespace between the
     two spans, or dompdf sets a word space between them and the rule breaks in the middle. --}}
<div class="rule" style="{{ $style ?? '' }}"><span class="left" style="width: {{ $left }}pt;"></span><span class="right" style="width: {{ $right }}pt;"></span></div>
