{{-- The project page and its drawers (docs/PROJECTS.md): only what the shared component classes don't cover. Brand tokens only. --}}
{{-- Static, so Livewire sends it once per page (in the head), not again with every update of the page or a drawer --}}
@assets
<style>
    .pj { max-width: 1240px; }
    .pj-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 0.875rem; text-decoration: none; }
    .pj-back svg { width: 16px; height: 16px; }
    .pj-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-top: 8px; font-size: 0.875rem; color: var(--color-muted); }
    .pj-meta > span { display: inline-flex; align-items: center; gap: 6px; }
    .pj-meta svg { width: 16px; height: 16px; flex-shrink: 0; }
    .pj-stats { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(150px, 100%), 1fr)); margin-bottom: 24px; }
    .pj-stat { background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 14px 16px; min-width: 0; }
    .pj-stat .stat-value { font-size: 1.25rem; line-height: 1.75rem; overflow-wrap: anywhere; }
    .pj-stat a { text-decoration: none; }
    .pj-stat-note { margin-top: 2px; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
    .pj-bar { height: 6px; border-radius: 999px; background: var(--color-mist); overflow: hidden; }
    .pj-bar > span { display: block; height: 100%; background: var(--color-teal); border-radius: 999px; }
    .pj-bar.is-done > span { background: var(--color-success); }
    .pj .tabs { overflow-y: hidden; }
    .pj-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
    .pj-toolbar-note { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-slate); }
    .pj-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(340px, 100%), 1fr)); align-items: start; }
    .pj-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .pj-list li { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
    .pj-list time, .pj-list .pj-sub { display: block; font-size: 0.75rem; line-height: 1rem; color: var(--color-muted); }
    .pj-link { padding: 0; border: 0; background: none; color: var(--color-ink); font: inherit; font-weight: 600; text-align: left; cursor: pointer; overflow-wrap: anywhere; }
    .pj-link:hover { color: var(--color-deep-teal); text-decoration: underline; }
    .pj-pills { display: flex; flex-wrap: wrap; gap: 6px; }

    /* Milestones: the strip (Overview) and the kanban */
    .pj-strip { position: relative; display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; scroll-snap-type: x proximity; }
    .pj-strip > * { flex: 0 0 200px; scroll-snap-align: start; }
    .pj-mini { display: flex; flex-direction: column; gap: 8px; padding: 12px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-paper); text-align: left; cursor: pointer; font: inherit; color: inherit; text-decoration: none; }
    .pj-mini:hover { border-color: var(--color-deep-teal); }
    .pj-mini-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.8125rem; color: var(--color-muted); }
    .pj-mini-title { font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    /* position: the visually hidden Move labels stay inside the scroll box instead of widening the page */
    .pj-kanban { position: relative; display: flex; gap: 12px; overflow-x: auto; padding-bottom: 12px; align-items: stretch; }
    .pj-col { flex: 1 0 236px; min-width: 236px; display: flex; flex-direction: column; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-paper); }
    .pj-col.is-over { background: var(--color-teal-tint); border-color: var(--color-deep-teal); }
    .pj-col-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 14px; border-bottom: 1px solid var(--color-line); font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
    .pj-col-head small { font-size: 0.75rem; font-weight: 500; color: var(--color-muted); }
    .pj-col-body { flex: 1; display: flex; flex-direction: column; gap: 10px; padding: 10px; min-height: 160px; }
    .pj-card { display: flex; flex-direction: column; gap: 8px; padding: 12px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-paper); }
    .pj-card[draggable="true"] { cursor: grab; }
    .pj-card:hover { border-color: var(--color-deep-teal); }
    .pj-card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .pj-card-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.75rem; color: var(--color-muted); }
    .pj-empty-col { padding: 14px; border: 1px dashed var(--color-line); border-radius: var(--radius-card); text-align: center; font-size: 0.8125rem; color: var(--color-muted); }
    .pj-move { padding: 4px 8px; font-size: 0.8125rem; line-height: 1.125rem; }

    /* Backlog */
    .pj-filters { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr)); align-items: end; margin-bottom: 16px; }
    .pj-check { display: flex; align-items: center; gap: 8px; min-height: 40px; font-size: 0.875rem; color: var(--color-ink); }
    .pj-group { margin-bottom: 20px; }
    .pj-group-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; padding: 10px 0; border-bottom: 1px solid var(--color-line-strong); }
    .pj-group-head h3 { margin: 0; font-size: 1rem; line-height: 1.5rem; }
    /* A row of columns when the list is wide, the title over its details when it is narrow (the list's width, not the screen's) */
    .pj-rows { list-style: none; margin: 0; padding: 0; container-type: inline-size; }
    .pj-row { display: grid; grid-template-columns: minmax(0, 1fr) 150px 170px 140px; gap: 12px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--color-line); font-size: 0.875rem; }
    .pj-row-cell { min-width: 0; color: var(--color-slate); overflow-wrap: anywhere; }
    .pj-row-label { display: none; }
    @container (max-width: 760px) {
        .pj-row { grid-template-columns: 140px minmax(0, 1fr) 140px; gap: 6px 16px; }
        .pj-row > :first-child { grid-column: 1 / -1; }
        .pj-row-label { display: block; font-size: 0.75rem; line-height: 1rem; color: var(--color-muted); }
    }
    @container (max-width: 460px) {
        .pj-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    /* Drawers */
    .pj-drawer-backdrop { position: fixed; inset: 0; z-index: 55; background: rgba(12, 20, 43, 0.4); }
    .pj-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 56; width: min(640px, 100vw); display: flex; flex-direction: column; background: var(--color-paper); box-shadow: var(--shadow-float); border-left: 1px solid var(--color-line); }
    .pj-drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 18px 24px; border-bottom: 1px solid var(--color-line); }
    .pj-drawer-head h2 { margin: 0; font-size: 1.125rem; line-height: 1.625rem; overflow-wrap: anywhere; }
    .pj-kicker { margin: 0 0 2px; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
    .pj-drawer-body { flex: 1; overflow-y: auto; padding: 20px 24px 32px; display: flex; flex-direction: column; gap: 24px; overscroll-behavior: contain; }
    .pj-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; flex-shrink: 0; padding: 0; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); color: var(--color-ink); cursor: pointer; }
    .pj-icon-btn:hover { background: var(--color-mist); }
    .pj-icon-btn svg { width: 18px; height: 18px; }
    .pj-icon-btn.is-small { width: 32px; height: 32px; border-color: transparent; }
    .pj-section h3 { margin: 0 0 10px; font-size: 0.9375rem; line-height: 1.375rem; font-weight: 600; color: var(--color-ink); }
    .pj-form { display: flex; flex-direction: column; gap: 14px; }
    .pj-two { display: grid; gap: 14px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pj-dl { display: grid; grid-template-columns: minmax(120px, auto) 1fr; gap: 8px 16px; margin: 0; font-size: 0.875rem; line-height: 1.375rem; }
    .pj-dl dt { color: var(--color-muted); }
    .pj-dl dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; white-space: pre-line; }
    .pj-people { max-height: 200px; overflow-y: auto; border: 1px solid var(--color-line); border-radius: var(--radius-control); padding: 8px 12px; display: flex; flex-direction: column; gap: 4px; }
    .pj-people label { display: flex; align-items: center; gap: 8px; min-height: 32px; font-size: 0.875rem; color: var(--color-ink); }
    .pj-inline { display: flex; gap: 8px; align-items: flex-start; }
    .pj-inline > .input, .pj-inline > .select { flex: 1; min-width: 0; }
    .pj-steps { list-style: none; margin: 0 0 10px; padding: 0; display: flex; flex-direction: column; gap: 4px; }
    .pj-steps li { display: flex; align-items: center; gap: 10px; min-height: 36px; font-size: 0.875rem; color: var(--color-ink); }
    .pj-steps li.is-done span { color: var(--color-muted); text-decoration: line-through; }
    .pj-steps li span { flex: 1; min-width: 0; overflow-wrap: anywhere; }
    .pj-waits { list-style: none; margin: 0 0 10px; padding: 0; display: flex; flex-direction: column; gap: 6px; }
    .pj-waits li { display: flex; align-items: center; gap: 10px; font-size: 0.875rem; }
    .pj-waits li > :first-child { flex: 1; min-width: 0; }
    .pj-comment { padding: 10px 12px; border-radius: var(--radius-card); background: var(--color-mist); font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); white-space: pre-line; overflow-wrap: anywhere; }
    .pj-comment-meta { display: flex; flex-wrap: wrap; gap: 4px 10px; margin-bottom: 4px; font-size: 0.75rem; color: var(--color-muted); white-space: normal; }
    .pj-actions { display: flex; flex-wrap: wrap; gap: 10px; }

    @media (max-width: 600px) {
        .pj-stat .stat-value { font-size: 1.0625rem; line-height: 1.5rem; }
        .pj-two { grid-template-columns: 1fr; }
        .pj-dl { grid-template-columns: 1fr; gap: 2px; }
        .pj-dl dd { margin-bottom: 8px; }
        .pj-drawer { width: 100vw; border-left: 0; }
        .pj-drawer-head, .pj-drawer-body { padding-left: 16px; padding-right: 16px; }
        .pj-actions .btn { flex: 1 1 auto; }
    }
</style>
@endassets
