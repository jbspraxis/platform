<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select {
            width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(0, 229, 255, 0.2);
            color: #FFFFFF; padding: 12px 15px; font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem; border-radius: 2px; transition: 0.3s; outline: none;
        }
        .cyber-input:focus, .cyber-select:focus { 
            border-color: #00E5FF; background: rgba(0, 229, 255, 0.05); box-shadow: inset 2px 0 0 #00E5FF; 
        }
        .cyber-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px;
        }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        
        .btn-action {
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px;
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer;
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; gap: 8px; justify-content: center;
        }
        .btn-action:hover { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .btn-sweep { border-color: #FFBD2E; color: #FFBD2E; }
        .btn-sweep:hover { background: #FFBD2E; color: #0A1128; box-shadow: 0 0 15px rgba(255, 189, 46, 0.4); }

        /* --- METRICS BAR --- */
        .metrics-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;
            margin-bottom: 30px;
        }
        .metric-card {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05);
            border-top: 3px solid #00E5FF; padding: 20px; border-radius: 4px; box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        .metric-label { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; text-transform: uppercase; margin-bottom: 10px; display: block; }
        .metric-value { font-family: 'Montserrat', sans-serif; font-size: 1.6rem; font-weight: 900; color: #FFF; }
        .metric-currency { font-size: 0.8rem; color: #00E5FF; vertical-align: middle; }

        /* --- LEDGER TABLE --- */
        .ledger-panel {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 4px; 
            margin-bottom: 30px; overflow: hidden; box-shadow: inset 0 0 20px rgba(0,0,0,0.2);
        }
        .ledger-table { width: 100%; border-collapse: collapse; text-align: left; }
        .ledger-table th {
            font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;
            padding: 15px 20px; border-bottom: 1px dashed rgba(255, 255, 255, 0.1); text-transform: uppercase;
        }
        .ledger-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.02); vertical-align: middle; }
        .ledger-table tr:hover { background: rgba(0, 229, 255, 0.02); }

        /* --- BURN RATE BAR --- */
        .burn-track { width: 100%; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; margin-top: 8px; }
        .burn-fill { height: 100%; transition: width 1s ease; }
        .status-badge { padding: 3px 8px; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; font-weight: bold; border-radius: 2px; border: 1px solid; }

        /* --- MODAL OVERLAY --- */
        .glass-modal {
            position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px);
            z-index: 9999; display: flex; align-items: center; justify-content: center;
        }
        .modal-box {
            background: rgba(10, 17, 40, 0.95); border: 1px solid rgba(255, 189, 46, 0.3); border-top: 4px solid #FFBD2E;
            padding: 30px; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); width: 100%; max-width: 550px;
        }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; padding-bottom: 15px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.8rem; font-weight: 900; letter-spacing: 1px; color: #FFF; margin: 0;">PROJECT TREASURY MATRIX</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; margin: 5px 0 0 0;">> Live Capital Allocation & Burn Rate Telemetry</p>
        </div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <select wire:model="activeProjectId" wire:change="switchProject($event.target.value)" class="cyber-select" style="width: 250px; padding: 10px;">
                @foreach($allProjects as $proj)
                    <option value="{{ $proj->id }}">{{ strtoupper($proj->title) }}</option>
                @endforeach
            </select>
            @can('finance.edit')
            <button wire:click="openSweepModal" class="btn-action btn-sweep">
                <i data-lucide="refresh-cw" style="width: 16px; height: 16px;"></i> SWEEP CAPITAL
            </button>
            @endcan
        </div>
    </div>

    @if(session()->has('success'))
        <div style="background: rgba(0, 255, 102, 0.1); border: 1px solid #00FF66; color: #00FF66; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 20px; border-radius: 2px; text-align: center;">
            {{ session('success') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div style="background: rgba(255, 51, 102, 0.1); border: 1px solid #FF3366; color: #FF3366; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 20px; border-radius: 2px; text-align: center;">
            {{ session('error') }}
        </div>
    @endif

    @if($activeProject)
        <div class="metrics-grid">
            <div class="metric-card" style="border-top-color: #8A9BB2;">
                <span class="metric-label">Total Contract Value</span>
                <div class="metric-value"><span class="metric-currency">{{ $activeProject->currency }}</span> {{ number_format($metrics['total_budget'], 2) }}</div>
            </div>
            <div class="metric-card">
                <span class="metric-label">Capital Allocated</span>
                <div class="metric-value"><span class="metric-currency">{{ $activeProject->currency }}</span> {{ number_format($metrics['total_allocated'], 2) }}</div>
            </div>
            <div class="metric-card" style="border-top-color: #FFBD2E;">
                <span class="metric-label">Pending Accruals (Locked)</span>
                <div class="metric-value"><span class="metric-currency">{{ $activeProject->currency }}</span> {{ number_format($metrics['total_accrued'], 2) }}</div>
            </div>
            <div class="metric-card" style="border-top-color: #FF3366;">
                <span class="metric-label">Capital Spent (Cleared)</span>
                <div class="metric-value"><span class="metric-currency">{{ $activeProject->currency }}</span> {{ number_format($metrics['total_spent'], 2) }}</div>
            </div>
            <div class="metric-card" style="border-top-color: #00FF66;">
                <span class="metric-label">Unallocated Profit Pool</span>
                <div class="metric-value" style="color: {{ $metrics['remaining_pool'] < 0 ? '#FF3366' : '#00FF66' }};"><span class="metric-currency">{{ $activeProject->currency }}</span> {{ number_format($metrics['remaining_pool'], 2) }}</div>
            </div>
        </div>

        <div class="ledger-panel">
            <div style="background: rgba(0, 0, 0, 0.4); padding: 15px 25px; border-bottom: 1px solid rgba(0, 229, 255, 0.2); font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #00E5FF;">
                > OPERATIONAL BUDGET LINES
            </div>
            <div style="overflow-x: auto;">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th style="width: 300px;">Description / Nomenclature</th>
                            <th style="text-align: right;">Allocated</th>
                            <th style="text-align: right;">Cleared Spend</th>
                            <th style="text-align: right;">Accrued (Pending)</th>
                            <th style="text-align: right;">Available Liquidity</th>
                            <th style="width: 150px; text-align: center;">Burn Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($budgetLines as $line)
                            @php
                                $totalCommitted = $line['total_spent'] + $line['accrued_amount'];
                                $burnPercent = $line['total_allocated'] > 0 ? ($totalCommitted / $line['total_allocated']) * 100 : ($totalCommitted > 0 ? 100 : 0);
                                $burnColor = '#00E5FF';
                                if($burnPercent >= 75) $burnColor = '#FFBD2E';
                                if($burnPercent >= 95) $burnColor = '#FF3366';
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-family: 'Inter', sans-serif; font-weight: bold; color: #FFF; font-size: 0.9rem;">{{ $line['description'] }}</div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 4px;">
                                        [{{ $line['item_code'] ?? 'CUSTOM' }}] {{ strtoupper($line['category'] ?? 'Uncategorized') }}
                                    </div>
                                </td>
                                <td style="text-align: right; font-family: 'Roboto Mono', monospace; color: #FFF; font-weight: bold;">
                                    ${{ number_format($line['total_allocated'], 2) }}
                                </td>
                                <td style="text-align: right; font-family: 'Roboto Mono', monospace; color: #FF3366;">
                                    ${{ number_format($line['total_spent'], 2) }}
                                </td>
                                <td style="text-align: right; font-family: 'Roboto Mono', monospace; color: #FFBD2E;">
                                    ${{ number_format($line['accrued_amount'], 2) }}
                                </td>
                                <td style="text-align: right; font-family: 'Roboto Mono', monospace; color: #00FF66; font-weight: bold;">
                                    ${{ number_format($line['available_balance'], 2) }}
                                </td>
                                <td>
                                    <div style="display: flex; justify-content: space-between; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">
                                        <span>BURN</span>
                                        <span style="color: {{ $burnColor }};">{{ number_format($burnPercent, 1) }}%</span>
                                    </div>
                                    <div class="burn-track">
                                        <div class="burn-fill" style="width: {{ min($burnPercent, 100) }}%; background: {{ $burnColor }}; box-shadow: 0 0 10px {{ $burnColor }};"></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                    > NO BUDGET LINES PROVISIONED FOR THIS BATTLESPACE.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div style="text-align: center; padding: 60px; border: 1px dashed rgba(255,255,255,0.1); border-radius: 4px; color: #8A9BB2; font-family: 'Roboto Mono', monospace;">
            > SYSTEM IDLE: SELECT AN ACTIVE BATTLESPACE TO COMMENCE TELEMETRY.
        </div>
    @endif

    @if($showSweepModal)
        <div class="glass-modal">
            <div class="modal-box">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #FFBD2E; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <span>> INITIATE CAPITAL SWEEP</span>
                    <button wire:click="$set('showSweepModal', false)" style="background: transparent; border: none; color: #8A9BB2; cursor: pointer;"><i data-lucide="x" style="width: 18px; height: 18px;"></i></button>
                </div>

                <div style="background: rgba(255,189,46,0.1); border-left: 2px solid #FFBD2E; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #FFBD2E; margin-bottom: 20px; line-height: 1.5;">
                    <strong>WARNING:</strong> Sweeping capital mathematically reallocates funds between lines. You cannot sweep funds that are already spent or locked in pending Cash Requests.
                </div>

                <form wire:submit.prevent="executeSweep" style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">SOURCE (DEDUCT FROM)</label>
                        <select wire:model="sweepFromId" class="cyber-select" required>
                            <option value="">-- SELECT BUDGET LINE --</option>
                            @foreach($budgetLines as $line)
                                @if($line['available_balance'] > 0)
                                    <option value="{{ $line['id'] }}">{{ $line['description'] }} (Avail: ${{ number_format($line['available_balance'], 2) }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div style="text-align: center; color: #FFBD2E; opacity: 0.5;">
                        <i data-lucide="arrow-down-circle" style="width: 24px; height: 24px;"></i>
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">TARGET (CREDIT TO)</label>
                        <select wire:model="sweepToId" class="cyber-select" required>
                            <option value="">-- SELECT BUDGET LINE --</option>
                            @foreach($budgetLines as $line)
                                <option value="{{ $line['id'] }}">{{ $line['description'] }}</option>
                            @endforeach
                        </select>
                        @error('sweepToId') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; display: block; margin-top: 5px;">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFBD2E; margin-bottom: 8px;">SWEEP AMOUNT (USD)</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 15px; top: 12px; color: #8A9BB2; font-family: 'Roboto Mono', monospace;">$</span>
                            <input type="number" wire:model="sweepAmount" step="0.01" min="0.01" class="cyber-input" style="padding-left: 30px; border-color: rgba(255,189,46,0.3); color: #FFBD2E; font-weight: bold;" required>
                        </div>
                        @error('sweepAmount') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; display: block; margin-top: 5px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <button type="button" wire:click="$set('showSweepModal', false)" class="btn-action" style="flex: 1; border-color: #8A9BB2; color: #8A9BB2;">ABORT</button>
                        <button type="submit" class="btn-action btn-sweep" style="flex: 2; background: #FFBD2E; color: #0A1128;" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="executeSweep">EXECUTE TRANSFER</span>
                            <span wire:loading wire:target="executeSweep">REBALANCING...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        });
    </script>
</div>