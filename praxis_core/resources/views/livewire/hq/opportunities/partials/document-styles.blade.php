{{-- The document tabs' styles (Proposals, SOW, Project draft: docs/OPPORTUNITIES.md section 4, rows 4 and 5): only what the shared classes don't cover. Brand tokens only. --}}
    <style>
        .pp { display: flex; flex-direction: column; gap: 16px; }
        .pp .alert, .pp .callout { margin: 0; }
        .pp .alert svg, .pp-callout svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; }
        .pp-callout { display: flex; gap: 10px; align-items: flex-start; font-size: 0.875rem; line-height: 1.375rem; }
        .pp-intro { margin: 4px 0 16px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .pp-form { display: flex; flex-direction: column; gap: 16px; }
        .pp-buttons { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .pp-bar { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px 16px; }
        .pp-bar-text { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
        .pp-title { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin: 0; overflow-wrap: anywhere; }
        .pp-title svg { width: 18px; height: 18px; }
        .pp-sub { margin: 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .pp-versions { min-width: 180px; }
        .pp-progress { height: 8px; border-radius: 999px; background: var(--color-mist); overflow: hidden; margin-top: 12px; }
        .pp-progress > span { display: block; height: 100%; border-radius: 999px; background: var(--color-deep-teal); transition: width 300ms ease; }
        .pp-spin { width: 18px; height: 18px; animation: pp-spin 1s linear infinite; }
        @keyframes pp-spin { to { transform: rotate(360deg); } }
        .pp-sections { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }
        .pp-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; font-size: 0.8125rem; line-height: 1.25rem; font-weight: 600; color: var(--color-muted); }
        .pp-tools { display: flex; gap: 4px; }
        .pp-tools .btn { padding: 6px; }
        .pp-tools svg { width: 16px; height: 16px; }
        .pp-h { margin: 0 0 8px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .pp-text { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .pp-text p { margin: 0 0 10px; }
        .pp-text ul { margin: 0 0 10px; padding-left: 20px; list-style: disc; }
        .pp-text strong { display: block; margin-bottom: 4px; color: var(--color-ink); }
        .pp-comments { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
        .pp-comment { padding: 10px 12px; border-radius: var(--radius-control); background: var(--color-mist); font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-ink); }
        .pp-comment.is-resolved { background: var(--color-paper); border: 1px solid var(--color-line); color: var(--color-slate); }
        .pp-comment-meta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px 8px; margin-bottom: 4px; color: var(--color-muted); }
        .pp-comment p { margin: 0; white-space: pre-line; overflow-wrap: anywhere; }
        .pp-lines { display: flex; flex-direction: column; gap: 12px; }
        .pp-line { display: grid; grid-template-columns: minmax(0, 1fr) 130px 140px 120px auto; gap: 8px 12px; align-items: end; padding-bottom: 12px; border-bottom: 1px solid var(--color-line); }
        .pp-line .field { margin: 0; }
        .pp-line .label { font-size: 0.75rem; }
        .pp-amount { font-size: 0.875rem; line-height: 2.5rem; text-align: right; color: var(--color-ink); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .pp-reason { grid-column: 1 / -1; display: flex; gap: 6px; font-size: 0.75rem; line-height: 1.125rem; color: var(--color-slate); }
        .pp-reason svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; color: var(--color-deep-teal); }
        .pp-add { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 8px 12px; align-items: end; }
        .pp-totals { margin: 0; display: grid; grid-template-columns: 1fr auto; gap: 6px 24px; max-width: 360px; margin-left: auto; font-size: 0.875rem; line-height: 1.375rem; font-variant-numeric: tabular-nums; }
        .pp-totals dt { color: var(--color-slate); }
        .pp-totals dd { margin: 0; text-align: right; color: var(--color-ink); white-space: nowrap; }
        .pp-totals .is-total { padding-top: 6px; border-top: 1px solid var(--color-ink); font-weight: 600; color: var(--color-ink); }
        .pp-terms { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr)); gap: 16px; }
        .pp-table td, .pp-table th { vertical-align: top; }
        .pp-people { display: flex; flex-direction: column; gap: 8px; }
        .pp-check { display: flex; align-items: flex-start; gap: 8px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); cursor: pointer; overflow-wrap: anywhere; }
        .pp-check input { margin-top: 4px; }
        .pp-check small { color: var(--color-muted); }
        .pp-dl { display: grid; grid-template-columns: minmax(120px, auto) 1fr; gap: 6px 16px; margin: 0; font-size: 0.875rem; line-height: 1.375rem; }
        .pp-dl dt { color: var(--color-muted); }
        .pp-dl dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; }
        .pp-saving { font-size: 0.8125rem; color: var(--color-muted); }
        /* SOW milestones, signatures and the project draft (PR 5) */
        .pp-ms { display: grid; grid-template-columns: minmax(0, 1fr) 100px 110px 150px auto; gap: 8px 12px; align-items: end; padding-bottom: 12px; border-bottom: 1px solid var(--color-line); }
        .pp-ms .field { margin: 0; }
        .pp-ms .label { font-size: 0.75rem; }
        .pp-ms .pp-wide { grid-column: 1 / -1; }
        .pp-sum { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 16px; margin-top: 12px; font-size: 0.875rem; line-height: 1.375rem; font-variant-numeric: tabular-nums; color: var(--color-ink); }
        .pp-sum .is-off { color: var(--color-danger, #B42318); font-weight: 600; }
        .pp-sigs { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr)); }
        .pp-sig { padding: 12px 14px; border: 1px solid var(--color-line); border-radius: var(--radius-control); font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .pp-sig strong { display: block; color: var(--color-ink); }
        .pp-sig img { display: block; max-width: 100%; height: 64px; object-fit: contain; margin: 6px 0; }
        .pp-hash { display: block; font-size: 0.6875rem; line-height: 1rem; color: var(--color-muted); overflow-wrap: anywhere; }
        .pd-task { display: grid; grid-template-columns: minmax(0, 1fr) 170px 90px 170px auto; gap: 8px 12px; align-items: end; padding: 10px 0; border-bottom: 1px solid var(--color-line); }
        .pd-task .field { margin: 0; }
        .pd-task .label { font-size: 0.75rem; }
        .pd-task .pp-wide { grid-column: 1 / -1; }
        .pd-dates { display: grid; gap: 12px 16px; grid-template-columns: repeat(auto-fit, minmax(min(170px, 100%), 1fr)); }
        @media (prefers-reduced-motion: reduce) { .pp-spin { animation: none; } .pp-progress > span { transition: none; } }
        @media (max-width: 760px) {
            .pp-line { grid-template-columns: 1fr 1fr; }
            .pp-line .pp-desc { grid-column: 1 / -1; }
            .pp-amount { text-align: left; line-height: 1.375rem; }
            .pp-add { grid-template-columns: 1fr; }
            .pp-ms, .pd-task { grid-template-columns: 1fr 1fr; }
            .pp-ms > :first-child, .pd-task > :first-child { grid-column: 1 / -1; }
        }
        @media (max-width: 600px) {
            .pp-buttons .btn, .pp-bar .btn { flex: 1 1 100%; }
            .pp-versions { min-width: 0; width: 100%; }
            .pp-dl { grid-template-columns: 1fr; gap: 2px; }
            .pp-dl dd { margin-bottom: 8px; }
            .pp-totals { max-width: none; }
        }
    </style>
