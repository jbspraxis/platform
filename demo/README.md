# Demo videos: "Ask your company anything"

The JBS Praxis demo videos ("A morning with the COO"), made 30 October 2026 from
`docs/DEMO_RUNBOOK.md`. The company on screen, **Ndali Group, is fictional**; every name and figure is
demo data from `story-figures.json`.

## Deliverables

| File | What it is |
|---|---|
| `hero-60s-loop.mp4` | Homepage hero: 61 s, silent, captions burned in, 1080p30, 6.2 MB |
| `hero-60s-poster.jpg` | Its poster, 85 KB |
| `demo-60s-en.mp4`, `demo-60s-fr.mp4` | The 60-second cut, narrated, website loudness (−16 LUFS) |
| `demo-3min-en.mp4`, `demo-3min-fr.mp4` | The 3-minute walkthrough (3:08), narrated, website loudness |
| `youtube/upload/*.mp4` | The same four videos at YouTube loudness (−14 LUFS): these are the ones to upload |
| `captions/*.vtt` | Subtitles, English and French, both cuts |
| `transcripts/*.md` | One transcript per video |
| `youtube/` | Titles, descriptions, chapters, tags, two thumbnails (1280×720) and the two-minute `upload-checklist.md` |
| `licences/` | The voices (open models, with the one required credit line) and the music (an original composition owned by JBS Praxis) |
| `story-figures.json` | The single source of every number |
| `storyboard.md`, `voiceover.json`, `script-*.md` | Storyboard, timed voice lines, scripts in English and French |
| `HOSTING_CHECK.md` | Why the demo runs locally for now |
| `../docs/MYCITY_DEMO_GAPS.md` | What the video shows that MyCity does not have yet, and how to build it |

Large media is git-ignored (`.gitignore`); scripts, texts, the poster and the thumbnails are small and
can be committed.

## Voices

English: YarnGPT2b, voice "idera" (Apache-2.0). French: Kokoro-82M, voice "ff_siwis", temporary (France
accent) until an African French voice is chosen. To swap the French voice, change `pipeline/voice/voices.json`,
regenerate the French lines and re-run the edit (below).

## Rebuild (everything is a script)

The demo app runs from `C:\Users\Tom-Blake\ndali-demo` (`node mock/server.js`, port 4100). Then, from
`demo/pipeline`:

```
node record/record.js <shot>            # shots in record/shots: s1–s6 (60 s), c1–c7b (3 min), excel, excel3, endcard
..\work\venv\Scripts\python -I voice\yarn.py --lines ..\work\voice\60s-en\lines.json --out-dir ..\work\voice\60s-en
..\work\venv\Scripts\python -I voice\kokoro_fr.py --lines ..\work\voice\60s-fr\lines.json --out-dir ..\work\voice\60s-fr
node edit/edit.js 60s                   # and 3min; set EN_TEMPO=0.85 for the English pace used
node thumbs/thumbs.js
```

The recorder drives the app on a virtual clock and captures every frame at 60 fps in 4K, so the
recordings are identical take after take and do not need the screen.
