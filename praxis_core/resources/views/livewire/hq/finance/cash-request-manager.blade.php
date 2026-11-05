<div>
    <style>
        .signature-pad { display: block; width: 100%; height: 150px; border: 1px dashed var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); cursor: crosshair; touch-action: none; }
        .signature-pad.expanded { height: 400px; }
    </style>

    @php
        $statusLabels = ['Pending Auth' => 'Awaiting approval', 'Authorized' => 'Approved', 'Rejected' => 'Rejected', 'Cleared' => 'Closed'];
        $statusPills = ['Pending Auth' => 'pill-warning', 'Authorized' => 'pill-info', 'Rejected' => 'pill-danger', 'Cleared' => 'pill-success'];
        $methodLabels = ['Bank Transfer' => 'Bank transfer', 'Mobile Money' => 'Mobile money', 'Crypto Wallet' => 'Crypto wallet', 'Cash' => 'Cash'];
        $emptyStates = [
            'pending_auth' => ['No requests awaiting approval', 'Cash requests that staff members send appear here for an approver to review.'],
            'pending_clearance' => ['No approved requests to close', 'Once a request is approved, check its receipt here and close it.'],
            'history' => ['No closed or rejected requests yet', 'Requests you close or reject are kept here as a record.'],
        ];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Cash request approvals</h1>
            <p class="page-subtitle">Approve or reject cash requests, then check each receipt and close the request.</p>
        </div>
    </div>

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="setTab('pending_auth')" class="tab {{ $activeTab === 'pending_auth' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'pending_auth' ? 'true' : 'false' }}">Awaiting approval</button>
        <button type="button" role="tab" wire:click="setTab('pending_clearance')" class="tab {{ $activeTab === 'pending_clearance' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'pending_clearance' ? 'true' : 'false' }}">Receipts to check</button>
        <button type="button" role="tab" wire:click="setTab('history')" class="tab {{ $activeTab === 'history' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'history' ? 'true' : 'false' }}">Closed and rejected</button>
    </div>

    @if ($errors->any() && ! $showAuthModal && ! $showClearanceModal)
        <div class="alert alert-danger mb-6" role="alert">
            <i data-lucide="circle-alert"></i>
            <ul class="list-none">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="card p-0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Requested by and purpose</th>
                        <th class="num text-right">Amount</th>
                        <th>{{ $activeTab === 'history' ? 'Status' : 'Action' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr>
                        <td>
                            <div class="num text-left font-semibold text-ink">{{ $req->reference }}</div>
                            <div class="help num text-left">{{ \Carbon\Carbon::parse($req->created_at)->setTimezone('Africa/Lagos')->format('j M Y, H:i') }}</div>
                        </td>
                        <td>
                            <div class="font-medium text-ink">{{ $req->first_name }} {{ $req->last_name }}</div>
                            <div class="help">{{ \Illuminate\Support\Str::limit($req->purpose, 50) }}</div>
                            <span class="tag mt-2"><span class="num">{{ $req->item_code }}</span> · {{ $req->category }}</span>
                        </td>
                        <td class="num">
                            <div class="font-semibold text-ink">{{ \App\Livewire\Hq\HqMoney::format($req->currency, $req->amount_requested) }}</div>
                            <div class="help">{{ $methodLabels[$req->disbursement_method] ?? explode(' |', $req->disbursement_details)[0] }}</div>
                        </td>
                        <td>
                            @if($activeTab === 'pending_auth')
                                @can('expenses.approve')
                                    @if((string) $req->requester_id === (string) auth()->id())
                                        <span class="help">Your request. Another approver decides.</span>
                                    @else
                                        <button type="button" wire:click="openAuthModal('{{ $req->id }}')" class="btn btn-secondary btn-sm">Review request</button>
                                    @endif
                                @endcan
                            @elseif($activeTab === 'pending_clearance')
                                @can('expenses.approve')
                                    @if((string) $req->requester_id === (string) auth()->id())
                                        <span class="help">Your request. Another approver closes it.</span>
                                    @else
                                        <button type="button" wire:click="openClearanceModal('{{ $req->id }}')" class="btn btn-secondary btn-sm">Check receipt</button>
                                    @endif
                                @endcan
                            @else
                                <span class="pill {{ $statusPills[$req->status] ?? 'pill-neutral' }}">{{ $statusLabels[$req->status] ?? $req->status }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <strong>{{ $emptyStates[$activeTab][0] ?? 'No requests here' }}</strong>
                                {{ $emptyStates[$activeTab][1] ?? '' }}
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showAuthModal && $selectedRequest)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="approve-title">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="approve-title" class="modal-title">Review cash request {{ $selectedRequest->reference }}</h2>
                    <button type="button" wire:click="$set('showAuthModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger mb-4" role="alert">
                        <i data-lucide="circle-alert"></i>
                        <ul class="list-none">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                <dl class="callout mb-5 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt class="muted">Requested by</dt><dd>{{ $selectedRequest->first_name }} {{ $selectedRequest->last_name }}</dd>
                    <dt class="muted">Purpose</dt><dd>{{ $selectedRequest->purpose }}</dd>
                    <dt class="muted">Amount</dt><dd class="font-semibold" style="font-variant-numeric: tabular-nums;">{{ \App\Livewire\Hq\HqMoney::format($selectedRequest->currency, $selectedRequest->amount_requested) }}</dd>
                    <dt class="muted">Pay by</dt><dd>{{ $methodLabels[$selectedRequest->disbursement_method] ?? $selectedRequest->disbursement_method }}</dd>
                    <dt class="muted">Payment details</dt><dd class="break-words" style="font-variant-numeric: tabular-nums;">{{ $selectedRequest->disbursement_details }}</dd>
                </dl>

                <div x-data="signaturePad()">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="label">Your signature</span>
                        <button @click="expandCanvas = true; setTimeout(() => initCanvas($refs.largeCanvas), 100)" type="button" class="btn btn-ghost btn-sm">Larger pad</button>
                    </div>

                    <canvas x-ref="canvas" class="signature-pad" aria-label="Signature pad" @mousedown="startDrawing" @mousemove="draw" @mouseup="stopDrawing" @mouseleave="stopDrawing" @touchstart.prevent="startDrawing" @touchmove.prevent="draw" @touchend.prevent="stopDrawing"></canvas>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <span class="help">Your signature, IP address and the time are recorded with the approval.</span>
                        <button type="button" @click="clearCanvas" class="btn btn-ghost btn-sm">Clear signature</button>
                    </div>

                    <div x-show="expandCanvas" style="display: none; z-index: 60;" class="modal-backdrop">
                        <div class="modal" style="max-width: 800px;" role="dialog" aria-modal="true" aria-label="Larger signature pad">
                            <h3 class="modal-title">Sign here</h3>
                            <canvas x-ref="largeCanvas" class="signature-pad expanded" aria-label="Large signature pad" @mousedown="startDrawing" @mousemove="draw" @mouseup="stopDrawing" @mouseleave="stopDrawing" @touchstart.prevent="startDrawing" @touchmove.prevent="draw" @touchend.prevent="stopDrawing"></canvas>
                            <div class="modal-actions">
                                <button type="button" @click="clearCanvas" class="btn btn-secondary">Clear signature</button>
                                <button type="button" @click="expandCanvas = false; syncToSmallCanvas()" class="btn btn-primary">Use this signature</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" wire:click="rejectRequest" wire:loading.attr="disabled" class="btn btn-danger">
                        <span wire:loading.remove wire:target="rejectRequest">Reject request</span>
                        <span wire:loading wire:target="rejectRequest">Rejecting…</span>
                    </button>
                    <button type="button" wire:click="authorizeRequest" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="authorizeRequest">Approve request</span>
                        <span wire:loading wire:target="authorizeRequest">Approving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showClearanceModal && $selectedRequest)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="close-title">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="close-title" class="modal-title">Close cash request {{ $selectedRequest->reference }}</h2>
                    <button type="button" wire:click="$set('showClearanceModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                @if ($errors->has('decision'))
                    <div class="alert alert-danger mb-4" role="alert"><i data-lucide="circle-alert"></i><span>{{ $errors->first('decision') }}</span></div>
                @endif

                <p class="mb-4 text-sm text-slate">Amount paid out: <strong class="text-ink" style="font-variant-numeric: tabular-nums;">{{ \App\Livewire\Hq\HqMoney::format($selectedRequest->currency, $selectedRequest->amount_requested) }}</strong></p>

                <div class="mb-5 rounded-lg border border-line bg-mist p-4">
                    @if($selectedRequest->receipt_url)
                        <div class="flex items-center justify-between gap-3">
                            <span class="pill pill-success">Receipt attached</span>
                            <a href="{{ route('hq.file', ['path' => $selectedRequest->receipt_url]) }}" target="_blank" class="btn btn-ghost btn-sm"><i data-lucide="file-text"></i> View receipt</a>
                        </div>
                    @else
                        <div class="field">
                            <label class="label" for="clear-receipt">Receipt or invoice</label>
                            <input id="clear-receipt" type="file" wire:model="receipt_file" class="input">
                            <span class="help">A JPG or PNG image, up to 5 MB.</span>
                            @error('receipt_file') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <button type="button" wire:click="uploadReceipt" wire:loading.attr="disabled" class="btn btn-secondary btn-sm mt-3"><i data-lucide="upload"></i> Upload receipt</button>
                    @endif
                </div>

                <div class="alert alert-warning" role="note">
                    <i data-lucide="triangle-alert"></i>
                    <span>Closing the request adds the amount to the project budget’s spending and can’t be undone. Check that the receipt matches the amount paid out.</span>
                </div>

                <div class="modal-actions">
                    <button type="button" wire:click="$set('showClearanceModal', false)" class="btn btn-secondary">Keep open</button>
                    <button type="button" wire:click="clearLedger" wire:loading.attr="disabled" {{ !$selectedRequest->receipt_url ? 'disabled' : '' }} class="btn btn-primary" @if(!$selectedRequest->receipt_url) style="opacity: 0.5; cursor: not-allowed;" @endif>
                        {{ $selectedRequest->receipt_url ? 'Close request' : 'Upload the receipt first' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        Alpine.data('signaturePad', () => ({
            isDrawing: false,
            ctx: null,
            activeCanvas: null,
            expandCanvas: false,

            init() {
                setTimeout(() => { this.initCanvas(this.$refs.canvas); }, 150);
            },
            initCanvas(canvasEl) {
                if(!canvasEl) return;
                this.activeCanvas = canvasEl;
                this.ctx = canvasEl.getContext('2d');
                const rect = canvasEl.getBoundingClientRect();
                canvasEl.width = rect.width;
                canvasEl.height = rect.height;
                this.ctx.lineWidth = 2;
                this.ctx.lineCap = 'round';
                this.ctx.strokeStyle = '#0C142B';
            },
            getCoordinates(e) {
                const rect = this.activeCanvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return { x: clientX - rect.left, y: clientY - rect.top };
            },
            startDrawing(e) {
                this.isDrawing = true;
                const { x, y } = this.getCoordinates(e);
                this.ctx.beginPath();
                this.ctx.moveTo(x, y);
            },
            draw(e) {
                if (!this.isDrawing) return;
                const { x, y } = this.getCoordinates(e);
                this.ctx.lineTo(x, y);
                this.ctx.stroke();
            },
            stopDrawing() {
                if (this.isDrawing) {
                    this.isDrawing = false;
                    this.ctx.closePath();
                    this.$wire.set('authorizer_signature', this.activeCanvas.toDataURL('image/png'));
                }
            },
            clearCanvas() {
                this.ctx.clearRect(0, 0, this.activeCanvas.width, this.activeCanvas.height);
                this.$wire.set('authorizer_signature', null);
            },
            syncToSmallCanvas() {
                const smallCtx = this.$refs.canvas.getContext('2d');
                smallCtx.drawImage(this.$refs.largeCanvas, 0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                this.initCanvas(this.$refs.canvas);
            }
        }))
    </script>
    @endscript
</div>
