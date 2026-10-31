<style>
    /* Meetings and transcripts of an opportunity: only what the shared component classes don't cover. Brand tokens only. */
    .mtg-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 16px; }
    .mtg-toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-left: auto; }
    .mtg-section { margin: 24px 0 12px; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-muted); text-transform: none; }
    .mtg-list { display: flex; flex-direction: column; gap: 12px; }
    .mtg-item { padding: 16px 20px; }
    .mtg-head { display: grid; grid-template-columns: 150px minmax(0, 1fr) auto; gap: 8px 20px; align-items: start; }
    .mtg-when { display: flex; flex-direction: column; font-size: 0.875rem; line-height: 1.375rem; font-variant-numeric: tabular-nums; }
    .mtg-when strong { color: var(--color-ink); font-weight: 600; }
    .mtg-title { margin: 0; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
    .mtg-tags { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; margin-top: 6px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
    .mtg-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
    .mtg-actions .btn svg, .mtg-toggle svg { width: 16px; height: 16px; }
    .mtg-toggle[aria-expanded="true"] svg { transform: rotate(180deg); }
    .mtg-body { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-line); display: flex; flex-direction: column; gap: 20px; }
    .mtg-cols { display: grid; gap: 16px 32px; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); }
    .mtg-sub { margin: 0 0 8px; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); }
    .mtg-people { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; font-size: 0.875rem; line-height: 1.375rem; }
    .mtg-people li { display: flex; flex-wrap: wrap; align-items: baseline; gap: 2px 8px; }
    .mtg-people .muted { overflow-wrap: anywhere; }
    .mtg-pre { margin: 0; white-space: pre-line; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); overflow-wrap: anywhere; }
    .mtg-link { display: flex; align-items: center; gap: 8px; font-size: 0.875rem; overflow-wrap: anywhere; }
    .mtg-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
    .mtg-transcript { border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; display: flex; flex-direction: column; gap: 16px; }
    .mtg-source { display: flex; align-items: center; gap: 8px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
    .mtg-source svg { width: 16px; height: 16px; flex-shrink: 0; }
    .mtg-block h4 { margin: 0 0 8px; font-size: 0.9375rem; line-height: 1.375rem; font-weight: 600; color: var(--color-ink); }
    .mtg-notes { display: flex; flex-direction: column; gap: 12px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
    .mtg-notes-summary { margin: 0; color: var(--color-ink); }
    .mtg-notes-label { margin: 0 0 4px; font-size: 0.8125rem; line-height: 1.25rem; font-weight: 600; color: var(--color-muted); }
    .mtg-notes ul { margin: 0; padding-left: 18px; list-style: disc; display: flex; flex-direction: column; gap: 2px; }
    .mtg-progress { height: 6px; border-radius: 999px; background: var(--color-mist); overflow: hidden; margin: 8px 0 4px; max-width: 320px; }
    .mtg-progress > span { display: block; height: 100%; background: var(--color-deep-teal); transition: width 400ms ease-out; }
    .mtg-status { display: flex; align-items: flex-start; gap: 8px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
    .mtg-status svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 3px; }
    .mtg-text { max-height: 420px; overflow-y: auto; margin-top: 8px; padding: 12px; background: var(--color-mist); border-radius: var(--radius-control); white-space: pre-wrap; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-ink); overflow-wrap: anywhere; }
    .mtg-details summary { cursor: pointer; font-size: 0.875rem; font-weight: 500; color: var(--color-deep-teal); min-height: 32px; display: flex; align-items: center; }
    .mtg-form { display: flex; flex-direction: column; gap: 16px; }
    .mtg-row { display: grid; gap: 12px; grid-template-columns: 1.4fr 1fr 1fr; }
    .mtg-checks { max-height: 200px; overflow-y: auto; border: 1px solid var(--color-line); border-radius: var(--radius-control); padding: 8px 12px; display: flex; flex-direction: column; gap: 4px; }
    .mtg-checks label { display: flex; align-items: flex-start; gap: 10px; min-height: 36px; padding: 6px 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); }
    .mtg-checks input { margin-top: 3px; }
    .mtg-radios { display: flex; flex-wrap: wrap; gap: 8px 20px; }
    .mtg-radios label { display: flex; align-items: center; gap: 8px; min-height: 36px; font-size: 0.875rem; color: var(--color-ink); }
    .mtg-fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
    .mtg-fieldset legend { margin-bottom: 6px; padding: 0; }
    .mtg-modal { max-width: 640px; }
    .mtg-sent { display: flex; flex-direction: column; gap: 8px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
    .mtg-sent ul { margin: 0; padding-left: 18px; list-style: disc; }
    .mtg-sent p { margin: 0; }
    @media (max-width: 760px) {
        .mtg-head { grid-template-columns: 1fr; }
        .mtg-actions { justify-content: flex-start; }
        .mtg-toolbar-actions { margin-left: 0; width: 100%; }
        .mtg-toolbar-actions .btn { flex: 1 1 auto; }
    }
    @media (max-width: 600px) {
        .mtg-item { padding: 16px; }
        .mtg-row { grid-template-columns: 1fr 1fr; }
        .mtg-row > :first-child { grid-column: 1 / -1; }
        .mtg-modal .modal-actions { flex-direction: column-reverse; }
        .mtg-modal .modal-actions .btn { width: 100%; }
    }
    @media (prefers-reduced-motion: reduce) { .mtg-progress > span { transition: none; } }
</style>
