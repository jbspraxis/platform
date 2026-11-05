<div>
    @php
        // Display labels for stored values (docs/BRAND.md section 12); the stored values stay as they are.
        $platformNames = [
            'META' => 'Meta', 'META (FACEBOOK)' => 'Meta (Facebook)', 'GOOGLE' => 'Google', 'LINKEDIN' => 'LinkedIn',
            'INSTAGRAM' => 'Instagram', 'FACEBOOK' => 'Facebook', 'TWITTER' => 'X (Twitter)', 'X' => 'X',
            'YOUTUBE' => 'YouTube', 'TIKTOK' => 'TikTok', 'WHATSAPP' => 'WhatsApp',
        ];
        $platformName = fn ($p) => $platformNames[strtoupper((string) $p)] ?? \Illuminate\Support\Str::ucfirst(strtolower((string) $p));
        $campaignStatus = fn ($s) => match ($s) {
            'PENDING_APPROVAL' => ['Awaiting approval', 'pill-warning'],
            'REVISION_REQUIRED' => ['Changes requested', 'pill-danger'],
            'PLANNED' => ['Approved', 'pill-info'],
            'ACTIVE' => ['Live', 'pill-success'],
            default => [\Illuminate\Support\Str::ucfirst(strtolower(str_replace('_', ' ', (string) $s))), 'pill-neutral'],
        };
        $assetStatus = fn ($s) => strtolower((string) $s) === 'active'
            ? ['Active', 'pill-success']
            : [\Illuminate\Support\Str::ucfirst(strtolower(str_replace('_', ' ', (string) $s))), 'pill-warning'];
        $usd = fn ($amount) => '$' . number_format((float) $amount, 2);
        $tabs = ['campaigns' => 'Campaigns', 'assets' => 'Accounts and logins', 'social' => 'Social reach'];
    @endphp

    <style>
        .mk-backdrop { position: fixed; inset: 0; z-index: 40; background: rgba(12, 20, 43, 0.4); }
        .mk-panel { position: fixed; top: 0; right: 0; bottom: 0; z-index: 41; width: 100%; max-width: 600px; display: flex; flex-direction: column; background: var(--color-paper); border-left: 1px solid var(--color-line); box-shadow: var(--shadow-float); }
        .mk-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 20px 24px; border-bottom: 1px solid var(--color-line); }
        .mk-panel-body { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
        .mk-panel-foot { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--color-line); }
        .mk-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin: 0; }
        .mk-facts dt { font-size: 0.8125rem; color: var(--color-muted); }
        .mk-facts dd { margin: 2px 0 0; color: var(--color-ink); word-break: break-word; }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Marketing</h1>
            <p class="page-subtitle">Plan campaign budgets, follow ad spend and results, and keep the logins for our social accounts.</p>
        </div>
    </div>

    <div class="tabs" role="tablist" aria-label="Marketing">
        @foreach($tabs as $key => $label)
            <button type="button" role="tab" wire:click="switchTab('{{ $key }}')" class="tab {{ $activeTab === $key ? 'is-active' : '' }}" aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert" style="margin-bottom: 16px;">
            <i data-lucide="circle-alert"></i>
            <div>@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
        </div>
    @endif

    @if($activeTab === 'campaigns')
        @php $all = collect($campaigns); @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" style="margin-bottom: 24px;">
            <div class="card">
                <div class="stat-label">Ad spend (from the ad platforms)</div>
                <div class="stat-value">{{ $usd($all->sum('actual_spend')) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Average cost per acquisition</div>
                <div class="stat-value">{{ $all->avg('calculated_cac') ? $usd($all->avg('calculated_cac')) : '$0.00' }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Opportunities</div>
                <div class="stat-value">{{ number_format($all->sum('opportunities')) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Return on ad spend (cash received)</div>
                <div class="stat-value">{{ $usd($all->sum('liquid_roas')) }}</div>
            </div>
        </div>

        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">Campaigns</h2>
                @can('marketing.create')
                <button type="button" wire:click="$set('showCreateModal', true)" class="btn btn-primary">
                    <i data-lucide="plus"></i> Request a campaign budget
                </button>
                @endcan
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Campaign and tracking tag</th>
                            <th>Platform</th>
                            <th>Status</th>
                            <th class="num" style="text-align: right;">Approved budget</th>
                            <th class="num" style="text-align: right;">Spend</th>
                            <th class="num" style="text-align: right;">Return (cash received)</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campaigns as $camp)
                            @php [$statusText, $statusPill] = $campaignStatus($camp['status']); @endphp
                            <tr>
                                <td>
                                    <div class="font-semibold text-ink">{{ $camp['name'] }}</div>
                                    <div class="text-caption text-muted" style="word-break: break-all;">{{ $camp['utm_campaign_tag'] ?? 'Not created yet' }}</div>
                                </td>
                                <td>{{ $platformName($camp['platform']) }}</td>
                                <td><span class="pill {{ $statusPill }}">{{ $statusText }}</span></td>
                                <td class="num">{{ $usd($camp['budget_amount']) }}</td>
                                <td class="num">{{ $usd($camp['actual_spend']) }}</td>
                                <td class="num">{{ $usd($camp['liquid_roas']) }}</td>
                                <td style="text-align: right; white-space: nowrap;">
                                    @if($canApprove && $camp['status'] === 'PENDING_APPROVAL')
                                        <button type="button" wire:click="openReviewModal('{{ $camp['id'] }}')" class="btn btn-secondary btn-sm">Review</button>
                                    @endif

                                    @if($camp['status'] === 'PLANNED')
                                        <button type="button" wire:click="openActivateModal('{{ $camp['id'] }}')" class="btn btn-secondary btn-sm">Link campaign ID</button>
                                    @endif

                                    @if($camp['status'] === 'REVISION_REQUIRED')
                                        <button type="button" wire:click="openReviewModal('{{ $camp['id'] }}')" class="btn btn-secondary btn-sm">See requested changes</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <strong>No campaigns yet</strong>
                                        Request a budget for a campaign. Once it is approved, link it to the ad platform to track its spend.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($activeTab === 'assets')
        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <div>
                    <h2 class="card-title">Accounts and logins</h2>
                    <p class="help" style="margin: 4px 0 0;">Our social and advertising accounts. Passwords are stored encrypted.</p>
                </div>
                @can('marketing.create')
                <button type="button" wire:click="$set('showAssetModal', true)" class="btn btn-primary"><i data-lucide="plus"></i> Add account</button>
                @endcan
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Account name</th>
                            <th>Login</th>
                            <th>Status</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($socialAccounts as $asset)
                            @php [$assetText, $assetPill] = $assetStatus($asset['status']); @endphp
                            <tr>
                                <td class="font-semibold text-ink">{{ $platformName($asset['platform']) }}</td>
                                <td>{{ $asset['account_name'] }}</td>
                                <td class="text-small">{{ $asset['login_identifier'] ?? 'Not recorded' }}</td>
                                <td><span class="pill {{ $assetPill }}">{{ $assetText }}</span></td>
                                <td style="text-align: right;">
                                    <button type="button" wire:click="openCredentialModal('{{ $asset['id'] }}')" class="btn btn-secondary btn-sm">
                                        <i data-lucide="key"></i> Manage login
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <strong>No accounts yet</strong>
                                        Add our social and advertising accounts here so their logins are kept in one place.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($activeTab === 'social')
        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">Social reach</h2>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Account name</th>
                            <th class="num" style="text-align: right;">Followers</th>
                            <th class="num" style="text-align: right;">Reach this week</th>
                            <th class="num" style="text-align: right;">Engagement rate</th>
                            <th>Last updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($socialAccounts as $social)
                            <tr>
                                <td class="font-semibold text-ink">{{ $platformName($social['platform']) }}</td>
                                <td>{{ $social['account_name'] }}</td>
                                <td class="num">{{ number_format($social['total_followers']) }}</td>
                                <td class="num">{{ number_format($social['weekly_reach']) }}</td>
                                <td class="num">{{ $social['engagement_rate'] }}%</td>
                                <td class="text-small" style="font-variant-numeric: tabular-nums;">{{ !empty($social['last_synced_at']) ? \Carbon\Carbon::parse($social['last_synced_at'])->setTimezone('Africa/Lagos')->format('j M Y, H:i') : 'Never' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <strong>No accounts to show</strong>
                                        Add an account in Accounts and logins. Its followers and reach appear here once they are updated from the platform.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Request a campaign budget --}}
    @if($showCreateModal)
        <div class="mk-backdrop" wire:click="$set('showCreateModal', false)"></div>
        <aside class="mk-panel" role="dialog" aria-modal="true" aria-labelledby="mk-create-title">
            <div class="mk-panel-head">
                <h2 id="mk-create-title" class="section-title" style="margin: 0;">Request a campaign budget</h2>
                <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
            </div>

            <form wire:submit.prevent="submitDraft" class="flex flex-col" style="flex: 1; min-height: 0;">
                <div class="mk-panel-body">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="field">
                            <label class="label" for="mk-name">Campaign name</label>
                            <input id="mk-name" type="text" wire:model="campName" class="input" placeholder="e.g. Q2 logistics ERP campaign">
                        </div>
                        <div class="field">
                            <label class="label" for="mk-platform">Ad platform</label>
                            <select id="mk-platform" wire:model="platform" class="select">
                                @foreach(['META' => 'Meta (Facebook and Instagram)', 'GOOGLE' => 'Google (search and display)', 'LINKEDIN' => 'LinkedIn'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="mk-url">Landing page</label>
                            <select id="mk-url" wire:model="targetUrl" class="select">
                                <option value="/">Home page (cost of poor quality calculator)</option>
                                <option value="/framework">The Praxis Method</option>
                                <option value="/kaizen">JBS Praxis Insights</option>
                                <option value="/contact">Contact page (free audit request)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="mk-budget">Budget (USD)</label>
                            <input id="mk-budget" type="number" step="0.01" wire:model="budgetAmount" class="input" placeholder="e.g. 1500.00">
                        </div>
                        <div class="field">
                            <label class="label" for="mk-start">Start date</label>
                            <input id="mk-start" type="date" wire:model="startDate" class="input">
                        </div>
                        <div class="field">
                            <label class="label" for="mk-end">End date (optional)</label>
                            <input id="mk-end" type="date" wire:model="endDate" class="input">
                        </div>
                    </div>

                    <fieldset class="flex flex-col gap-4" style="border: 0; border-top: 1px solid var(--color-line); padding: 16px 0 0; margin: 0;">
                        <legend class="card-title" style="float: left; width: 100%; margin-bottom: 4px;">Creative</legend>
                        <p class="help" style="margin: 0;">Upload the file, or link to it if it is larger than 10 MB.</p>
                        <div class="field">
                            <label class="label" for="mk-file">Upload a file (optional, under 10 MB)</label>
                            <input id="mk-file" type="file" wire:model="creativeFile" class="input" style="padding: 7px;">
                            <span wire:loading wire:target="creativeFile" class="help">Uploading…</span>
                        </div>
                        <div class="field">
                            <label class="label" for="mk-link">Link to the file (optional)</label>
                            <input id="mk-link" type="text" wire:model="creativeLink" class="input" placeholder="e.g. https://drive.google.com/…">
                        </div>
                    </fieldset>

                    <div class="field">
                        <label class="label" for="mk-remarks">Notes for the approver (optional)</label>
                        <textarea id="mk-remarks" wire:model="remarks" class="textarea" placeholder="e.g. Who the ads are for and why" style="min-height: 120px;"></textarea>
                    </div>
                </div>

                <div class="mk-panel-foot">
                    <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="submitDraft">
                        <span wire:loading.remove wire:target="submitDraft">Send for approval</span>
                        <span wire:loading wire:target="submitDraft">Sending…</span>
                    </button>
                </div>
            </form>
        </aside>
    @endif

    {{-- Add an account --}}
    @if($showAssetModal)
        <div class="mk-backdrop" wire:click="$set('showAssetModal', false)"></div>
        <aside class="mk-panel" role="dialog" aria-modal="true" aria-labelledby="mk-asset-title">
            <div class="mk-panel-head">
                <h2 id="mk-asset-title" class="section-title" style="margin: 0;">Add account</h2>
                <button type="button" wire:click="$set('showAssetModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
            </div>
            <form wire:submit.prevent="registerDigitalAsset" class="flex flex-col" style="flex: 1; min-height: 0;">
                <div class="mk-panel-body">
                    <div class="field">
                        <label class="label" for="mk-asset-platform">Platform</label>
                        <input id="mk-asset-platform" type="text" wire:model="assetPlatform" class="input" placeholder="e.g. Meta Business Manager">
                    </div>
                    <div class="field">
                        <label class="label" for="mk-asset-name">Account name</label>
                        <input id="mk-asset-name" type="text" wire:model="assetName" class="input">
                    </div>
                    <div class="field">
                        <label class="label" for="mk-asset-login">Login email or username</label>
                        <input id="mk-asset-login" type="text" wire:model="loginIdentifier" class="input" placeholder="e.g. name@jbspraxis.com" autocomplete="off">
                    </div>
                    <div class="field">
                        <label class="label" for="mk-asset-password">Password</label>
                        <input id="mk-asset-password" type="password" wire:model="assetPassword" class="input" autocomplete="new-password">
                        <span class="help">Stored encrypted.</span>
                    </div>
                    <div class="field">
                        <label class="label" for="mk-asset-admin">Personal admin profile link (optional)</label>
                        <input id="mk-asset-admin" type="url" wire:model="adminProfileLink" class="input" placeholder="e.g. https://facebook.com/…">
                        <span class="help">Meta and LinkedIn need a personal account to manage a business page. Link that personal profile here.</span>
                    </div>
                </div>
                <div class="mk-panel-foot">
                    <button type="button" wire:click="$set('showAssetModal', false)" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="registerDigitalAsset">Add account</button>
                </div>
            </form>
        </aside>
    @endif

    {{-- Manage an account’s login --}}
    @if($showCredentialModal && $selectedAsset)
        <div class="mk-backdrop" wire:click="$set('showCredentialModal', false)"></div>
        <aside class="mk-panel" role="dialog" aria-modal="true" aria-labelledby="mk-cred-title">
            <div class="mk-panel-head">
                <h2 id="mk-cred-title" class="section-title" style="margin: 0;">Login for {{ $selectedAsset->account_name }}</h2>
                <button type="button" wire:click="$set('showCredentialModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
            </div>

            <div class="mk-panel-body">
                <dl class="mk-facts">
                    <div><dt>Account</dt><dd>{{ $selectedAsset->account_name }} ({{ $platformName($selectedAsset->platform) }})</dd></div>
                    <div><dt>Login</dt><dd>{{ $selectedAsset->login_identifier }}</dd></div>
                    <div style="grid-column: 1 / -1;"><dt>Personal admin profile</dt><dd>{{ ($selectedAsset->admin_profile_link ?? null) ?: 'Not recorded' }}</dd></div>
                    <div style="grid-column: 1 / -1;">
                        <dt>Current password</dt>
                        <dd class="callout" style="margin-top: 6px; font-size: 1rem; font-variant-numeric: tabular-nums; word-break: break-all;">{{ $decryptedPassword }}</dd>
                    </div>
                </dl>

                @can('marketing.edit')
                <div class="flex flex-col gap-3" style="padding-top: 20px; border-top: 1px solid var(--color-line);">
                    <div class="field">
                        <label class="label" for="mk-new-password">New password</label>
                        <input id="mk-new-password" type="password" wire:model="newPassword" class="input" autocomplete="new-password">
                        <span class="help">The change is recorded in the audit trail, and staff who can see account passwords are notified.</span>
                    </div>
                    <div>
                        <button type="button" wire:click="updateAssetPassword" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updateAssetPassword">Change password</button>
                    </div>
                </div>
                @endcan

                @if($canSeeCredentials && count($credentialHistory) > 0)
                    <div style="padding-top: 20px; border-top: 1px solid var(--color-line);">
                        <h3 class="card-title" style="margin-bottom: 8px;">Password changes</h3>
                        <ul class="flex flex-col gap-2 text-small" style="list-style: none; margin: 0; padding: 0;">
                            @foreach($credentialHistory as $log)
                                <li><span class="text-ink" style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($log['created_at'])->setTimezone('Africa/Lagos')->format('j M Y, H:i') }}</span> · changed by {{ $log['user_name'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </aside>
    @endif

    {{-- Review a campaign --}}
    @if($showReviewModal && $selectedCampaign)
        @php [$reviewText, $reviewPill] = $campaignStatus($selectedCampaign->status); @endphp
        <div class="mk-backdrop" wire:click="$set('showReviewModal', false)"></div>
        <aside class="mk-panel" role="dialog" aria-modal="true" aria-labelledby="mk-review-title">
            <div class="mk-panel-head">
                <div class="flex items-center gap-3">
                    <h2 id="mk-review-title" class="section-title" style="margin: 0;">Campaign review</h2>
                    <span class="pill {{ $reviewPill }}">{{ $reviewText }}</span>
                </div>
                <button type="button" wire:click="$set('showReviewModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
            </div>

            <div class="mk-panel-body">
                <dl class="mk-facts">
                    <div><dt>Name</dt><dd>{{ $selectedCampaign->name }}</dd></div>
                    <div><dt>Platform</dt><dd>{{ $platformName($selectedCampaign->platform) }}</dd></div>
                    <div><dt>Landing page</dt><dd>jbspraxis.com{{ $selectedCampaign->target_url }}</dd></div>
                    <div><dt>Budget requested</dt><dd class="font-semibold" style="font-variant-numeric: tabular-nums;">{{ $usd($selectedCampaign->budget_amount) }}</dd></div>
                </dl>

                <div>
                    <div class="label" style="margin-bottom: 6px;">Notes from the requester</div>
                    <p class="text-small" style="margin: 0; padding: 12px 16px; background: var(--color-mist); border-radius: var(--radius-card); white-space: pre-wrap;">{{ $selectedCampaign->remarks ?? 'No notes.' }}</p>
                </div>

                @if($selectedCampaign->status === 'REVISION_REQUIRED')
                    <div class="alert alert-danger">
                        <i data-lucide="circle-alert"></i>
                        <div><strong>Changes requested</strong><div>{{ $selectedCampaign->rejection_reason }}</div></div>
                    </div>
                @endif

                @if($canApprove && $selectedCampaign->status === 'PENDING_APPROVAL')
                    <div class="field" style="padding-top: 20px; border-top: 1px solid var(--color-line);">
                        <label class="label" for="mk-reason">Reason for returning it</label>
                        <textarea id="mk-reason" wire:model="rejectionReason" class="textarea" placeholder="e.g. Narrow the audience to finance directors" style="min-height: 100px;"></textarea>
                        <span class="help">Needed only if you return the campaign for changes. The requester sees it.</span>
                    </div>
                @endif
            </div>

            <div class="mk-panel-foot" style="flex-wrap: wrap; align-items: center;">
                @if($canApprove && $selectedCampaign->status === 'PENDING_APPROVAL')
                    <span class="help" style="margin-right: auto;">Approving creates a cash request for the budget in Finance.</span>
                    <button type="button" wire:click="rejectCampaign" class="btn btn-secondary">Return for changes</button>
                    <button type="button" wire:click="approveCampaign" class="btn btn-primary">Approve budget</button>
                @else
                    <button type="button" wire:click="$set('showReviewModal', false)" class="btn btn-secondary">Close</button>
                @endif
            </div>
        </aside>
    @endif

    {{-- Link the approved campaign to the ad platform --}}
    @if($showActivateModal && $selectedCampaign)
        <div class="mk-backdrop" wire:click="$set('showActivateModal', false)"></div>
        <aside class="mk-panel" role="dialog" aria-modal="true" aria-labelledby="mk-link-title">
            <div class="mk-panel-head">
                <h2 id="mk-link-title" class="section-title" style="margin: 0;">Link the campaign to {{ $platformName($selectedCampaign->platform) }}</h2>
                <button type="button" wire:click="$set('showActivateModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
            </div>

            <div class="mk-panel-body">
                <p class="text-small" style="margin: 0;">The budget is approved. Create the campaign in {{ $platformName($selectedCampaign->platform) }}, then paste its campaign ID here so its spend can be tracked.</p>
                <div class="field">
                    <label class="label" for="mk-ext-id">Campaign ID on {{ $platformName($selectedCampaign->platform) }}</label>
                    <input id="mk-ext-id" type="text" wire:model="externalCampaignId" class="input" placeholder="e.g. 238491029384" style="font-variant-numeric: tabular-nums;">
                </div>
            </div>

            <div class="mk-panel-foot">
                <button type="button" wire:click="$set('showActivateModal', false)" class="btn btn-secondary">Cancel</button>
                <button type="button" wire:click="activateCampaign" class="btn btn-primary">Link campaign</button>
            </div>
        </aside>
    @endif
</div>
