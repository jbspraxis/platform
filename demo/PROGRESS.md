# Demo videos: state (30 October 2026, afternoon)

**All deliverables are produced** (see `README.md`): the silent hero loop and poster, the 60-second and
3-minute cuts in English and French (website loudness) plus YouTube copies, captions, transcripts,
YouTube texts and thumbnails, licences, the gaps document (`docs/MYCITY_DEMO_GAPS.md`).

## Decisions taken with the owner on 30 October 2026

- Voices: no Microsoft. English YarnGPT2b "idera"; French Kokoro "ff_siwis" (temporary). Credits only.
- To finish fast: the PostgreSQL seed and full backend were cut. The demo app (`C:\Users\Tom-Blake\ndali-demo`,
  React app in `app/`) runs on a small in-memory API, `mock/server.js`, whose data comes from
  `data/story-figures.json`. Story continuity across shots comes from the story clock the recorder sets.
- Hosting: cPanel has no PostgreSQL, so the demo is local. The hosting question (VPS or keep local) is
  the owner's, at handover.

## How it was made

- `pipeline/record`: frame-by-frame capture on a virtual clock, 60 fps, 4K, zooms rendered by the
  browser. Shots: `s1`–`s6` (60 s), `c1`–`c7b` (3 min), `excel`, `excel3`, `endcard`.
- `pipeline/voice`: YarnGPT (sentence by sentence, best of several takes) and Kokoro.
- `pipeline/music/compose.py`: the original music bed.
- `pipeline/edit/edit.js` with `cut-60s.json` and `cut-3min.json`: picture, captions, voice, music,
  loudness, VTT, poster.
- `pipeline/review/pack.ps1`: stills every 2 s and measurements for the self-review (runbook §9).

## Owner actions left

1. YouTube: one yes, then the upload (`youtube/upload-checklist.md`, about two minutes a video).

Hosting is decided: the owner chose to keep the demo local (30 October 2026), so there is no VPS
and no `demo.jbspraxis.com` for now.
