{{-- Calculate the cost of poor quality from the lead page (App\Support\Leads\Copq: the website calculator's maths) --}}
<form wire:submit="saveCopq" class="ld-form" novalidate aria-label="Calculate the cost of poor quality">
    <p class="help" style="margin: 0">For {{ $lead->industry ?: 'their industry' }}. Every figure is needed; amounts are in the currency you choose.</p>
    @error('copq.industry') <div class="alert alert-warning" role="alert"><i data-lucide="triangle-alert"></i><span>{{ $message }}</span></div> @enderror
    <div class="ld-row">
        <div class="field">
            <label class="label" for="ld-q-tool">Systems in use</label>
            <select id="ld-q-tool" class="select" wire:model="copq.tool" @error('copq.tool') aria-invalid="true" @enderror>
                <option value="">Choose the main system</option>
                @foreach ($choices['systems'] as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('copq.tool') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label class="label" for="ld-q-bottleneck">Main bottleneck</label>
            <select id="ld-q-bottleneck" class="select" wire:model="copq.bottleneck" @error('copq.bottleneck') aria-invalid="true" @enderror>
                <option value="">Choose a bottleneck</option>
                @foreach ($choices['bottlenecks'] as $b)
                    <option value="{{ $b }}">{{ $b }}</option>
                @endforeach
            </select>
            @error('copq.bottleneck') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label class="label" for="ld-q-currency">Currency</label>
            <select id="ld-q-currency" class="select" wire:model="copq.currency">
                @foreach ($choices['currencies'] as $code => $label)
                    <option value="{{ $code }}">{{ $code }}: {{ $label }}</option>
                @endforeach
            </select>
            @error('copq.currency') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </div>
    <div class="ld-row">
        @foreach (\App\Support\Leads\Copq::FIGURES as $key => $label)
            <div class="field" wire:key="ld-fig-{{ $key }}">
                <label class="label" for="ld-q-{{ $key }}">{{ $label }}</label>
                <input id="ld-q-{{ $key }}" type="number" min="0" step="any" inputmode="decimal" class="input" wire:model="copq.{{ $key }}" @error("copq.$key") aria-invalid="true" @enderror>
                @error("copq.$key") <span class="field-error">{{ $message }}</span> @enderror
            </div>
        @endforeach
    </div>
    <div class="ld-actions">
        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveCopq"><i data-lucide="calculator"></i> Calculate</button>
        <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelCalculate">Cancel</button>
    </div>
</form>
