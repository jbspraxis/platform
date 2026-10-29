<div>
    <style>
        .signature-pad { display: block; width: 100%; height: 150px; border: 1px dashed var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); cursor: crosshair; touch-action: none; }
    </style>

    @php
        $statusLabels = ['Pending Auth' => 'Awaiting approval', 'Authorized' => 'Approved', 'Rejected' => 'Rejected', 'Cleared' => 'Closed'];
        $statusPills = ['Pending Auth' => 'pill-warning', 'Authorized' => 'pill-info', 'Rejected' => 'pill-danger', 'Cleared' => 'pill-success'];
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cash-request-sent-title">
                <h2 id="cash-request-sent-title" class="modal-title">Cash request sent</h2>
                <p class="muted">Your reference is <strong class="num text-ink">{{ $generatedReference }}</strong>. Finance will review it, and you'll get a notification when it is approved or rejected.</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">View my requests</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Cash requests</h1>
            <p class="page-subtitle">Ask Finance for money for a work expense, then upload the receipt once you have spent it.</p>
        </div>
    </div>

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="setTab('request')" class="tab {{ $activeTab === 'request' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'request' ? 'true' : 'false' }}">New request</button>
        <button type="button" role="tab" wire:click="setTab('ledger')" class="tab {{ $activeTab === 'ledger' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'ledger' ? 'true' : 'false' }}">My requests</button>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-6" role="alert">
            <i data-lucide="circle-alert"></i>
            <div>
                <strong>We couldn't send the request.</strong> Fix the following and try again:
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($activeTab === 'request')
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="card">
                <h2 class="card-title mb-4">Request details</h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="field">
                        <label class="label" for="cr-project">Project (optional)</label>
                        <select id="cr-project" wire:model="project_id" class="select">
                            <option value="">No project (office costs)</option>
                            @foreach($projects as $proj) <option value="{{ $proj->id }}">{{ $proj->title }}</option> @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="cr-item">Expense item</label>
                        <select id="cr-item" wire:model="financial_dictionary_id" class="select">
                            <option value="">Choose an expense item</option>
                            @foreach($dictionaryItems as $item) <option value="{{ $item->id }}">{{ $item->item_code }}: {{ $item->description }}</option> @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="cr-amount">Amount</label>
                        <input id="cr-amount" type="number" step="0.01" wire:model="amount_requested" class="input num" style="text-align: left;">
                    </div>
                    <div class="field">
                        <label class="label" for="cr-currency">Currency</label>
                        <select id="cr-currency" wire:model="currency" class="select">
                            <option value="USD">USD</option><option value="XAF">XAF</option><option value="NGN">NGN</option><option value="USDT">USDT</option>
                        </select>
                    </div>

                    <div class="field md:col-span-2">
                        <label class="label" for="cr-purpose">Purpose</label>
                        <textarea id="cr-purpose" wire:model="purpose" class="textarea" rows="3"></textarea>
                        <span class="help">Say what the money is for and why it is needed now.</span>
                    </div>
                </div>

                <hr class="divider my-6">

                <h2 class="card-title mb-4">How should we pay you?</h2>
                <div class="field mb-4">
                    <label class="label" for="cr-method">Payment method</label>
                    <select id="cr-method" wire:model.live="disbursement_method" class="select">
                        <option value="Bank Transfer">Bank transfer</option><option value="Mobile Money">Mobile money</option><option value="Crypto Wallet">Crypto wallet</option><option value="Cash">Cash</option>
                    </select>
                </div>

                @if($disbursement_method === 'Bank Transfer')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="field">
                            <label class="label" for="cr-bank">Bank name</label>
                            <input id="cr-bank" type="text" wire:model="payee_bank" class="input">
                        </div>
                        <div class="field">
                            <label class="label" for="cr-acct-no">Account number or IBAN</label>
                            <input id="cr-acct-no" type="text" wire:model="payee_account_number" class="input">
                        </div>
                        <div class="field md:col-span-2">
                            <label class="label" for="cr-acct-name">Account name</label>
                            <input id="cr-acct-name" type="text" wire:model="payee_account_name" class="input">
                            <span class="help">Exactly as the bank shows it.</span>
                        </div>
                    </div>
                @elseif($disbursement_method === 'Mobile Money')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="field">
                            <label class="label" for="cr-momo-provider">Provider</label>
                            <select id="cr-momo-provider" wire:model="momo_provider" class="select"><option value="MTN">MTN</option><option value="Orange">Orange</option></select>
                        </div>
                        <div class="field">
                            <label class="label" for="cr-momo-phone">Phone number</label>
                            <input id="cr-momo-phone" type="text" wire:model="momo_phone" class="input" placeholder="e.g. +237 6 99 00 00 00">
                        </div>
                        <div class="field md:col-span-2">
                            <label class="label" for="cr-momo-name">Name on the account</label>
                            <input id="cr-momo-name" type="text" wire:model="momo_name" class="input">
                        </div>
                    </div>
                @elseif($disbursement_method === 'Crypto Wallet')
                    <div class="grid gap-4 md:grid-cols-[1fr_2fr]">
                        <div class="field">
                            <label class="label" for="cr-network">Network</label>
                            <select id="cr-network" wire:model="crypto_network" class="select"><option value="TRC20">TRC20</option><option value="ERC20">ERC20</option></select>
                        </div>
                        <div class="field">
                            <label class="label" for="cr-wallet">Wallet address</label>
                            <input id="cr-wallet" type="text" wire:model="crypto_address" class="input">
                        </div>
                    </div>
                @else
                    <p class="help">Collect the cash from the office once the request is approved.</p>
                @endif
            </div>

            <div class="card" x-data="signaturePad()">
                <h2 class="card-title">Your signature</h2>
                <p class="help mt-1 mb-4">Sign in the box with your mouse or finger. Your signature, IP address and the time are recorded with the request.</p>
                <canvas x-ref="canvas" class="signature-pad" aria-label="Signature pad" @mousedown="startDrawing" @mousemove="draw" @mouseup="stopDrawing" @mouseleave="stopDrawing" @touchstart.prevent="startDrawing" @touchmove.prevent="draw" @touchend.prevent="stopDrawing"></canvas>
                <div class="mt-2 flex justify-end">
                    <button type="button" @click="clearCanvas" class="btn btn-ghost btn-sm">Clear signature</button>
                </div>
                <button type="button" wire:click="submitRequest" wire:loading.attr="disabled" class="btn btn-primary mt-4 w-full">
                    <span wire:loading.remove wire:target="submitRequest">Send request</span>
                    <span wire:loading wire:target="submitRequest">Sending…</span>
                </button>
            </div>
        </div>

        @script
        <script>
            Alpine.data('signaturePad', () => ({
                isDrawing: false, ctx: null, activeCanvas: null,
                init() { setTimeout(() => { this.initCanvas(this.$refs.canvas); }, 100); },
                initCanvas(canvasEl) {
                    if(!canvasEl) return;
                    this.activeCanvas = canvasEl; this.ctx = canvasEl.getContext('2d');
                    const rect = canvasEl.getBoundingClientRect(); canvasEl.width = rect.width; canvasEl.height = rect.height;
                    this.ctx.lineWidth = 2; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#0C142B';
                },
                getCoordinates(e) { const rect = this.activeCanvas.getBoundingClientRect(); const clientX = e.touches ? e.touches[0].clientX : e.clientX; const clientY = e.touches ? e.touches[0].clientY : e.clientY; return { x: clientX - rect.left, y: clientY - rect.top }; },
                startDrawing(e) { this.isDrawing = true; const { x, y } = this.getCoordinates(e); this.ctx.beginPath(); this.ctx.moveTo(x, y); },
                draw(e) { if (!this.isDrawing) return; const { x, y } = this.getCoordinates(e); this.ctx.lineTo(x, y); this.ctx.stroke(); },
                stopDrawing() { if (this.isDrawing) { this.isDrawing = false; this.ctx.closePath(); this.$wire.set('requester_signature', this.activeCanvas.toDataURL('image/png')); } },
                clearCanvas() { this.ctx.clearRect(0, 0, this.activeCanvas.width, this.activeCanvas.height); this.$wire.set('requester_signature', null); },
            }))
        </script>
        @endscript

    @elseif($activeTab === 'ledger')
        <div class="card p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Category and purpose</th>
                            <th class="num">Amount</th>
                            <th>Status</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($myRequests as $req)
                        <tr>
                            <td>
                                <div class="num text-left font-semibold text-ink">{{ $req->reference }}</div>
                                <div class="help num text-left">{{ \Carbon\Carbon::parse($req->created_at)->format('j M Y, H:i') }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-ink">{{ $req->category }}</div>
                                <div class="help">{{ \Illuminate\Support\Str::limit($req->purpose, 50) }}</div>
                            </td>
                            <td class="num">{{ $req->currency }} {{ number_format($req->amount_requested, 2) }}</td>
                            <td><span class="pill {{ $statusPills[$req->status] ?? 'pill-neutral' }}">{{ $statusLabels[$req->status] ?? $req->status }}</span></td>
                            <td>
                                @if($req->status === 'Authorized' && !$req->receipt_url)
                                    <div class="flex flex-col items-start gap-2">
                                        <label class="sr-only" for="receipt-{{ $req->id }}">Receipt for {{ $req->reference }}</label>
                                        <input id="receipt-{{ $req->id }}" type="file" wire:model="receipt_file" class="text-sm" style="max-width: 220px;">
                                        <button type="button" wire:click="uploadReceipt('{{ $req->id }}')" wire:loading.attr="disabled" class="btn btn-secondary btn-sm"><i data-lucide="upload"></i> Upload receipt</button>
                                    </div>
                                    @if(session()->has('message-'.$req->id)) <div class="help mt-1" style="color: var(--color-success);">{{ session('message-'.$req->id) }}</div> @endif
                                @elseif($req->receipt_url)
                                    <a href="{{ route('hq.file', ['path' => $req->receipt_url]) }}" target="_blank" class="btn btn-ghost btn-sm"><i data-lucide="file-text"></i> View receipt</a>
                                @elseif($req->status === 'Pending Auth')
                                    <span class="help">Upload after approval</span>
                                @else
                                    <span class="help">Not needed</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <strong>No cash requests yet</strong>
                                    Requests you send appear here with their status and receipt.
                                    <div class="mt-4"><button type="button" wire:click="setTab('request')" class="btn btn-primary">New request</button></div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
