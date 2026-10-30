/**
 * The end card (runbook §4.2, BRAND.md §11): the page is complete from the first frame except
 * the line, which fades up at 400 ms (after the edit's dissolve), and the Praxis rule, which
 * draws once from 550 ms to 1750 ms. Then stillness. 8 s, trimmed per cut in the edit.
 */
module.exports = {
  offline: true,
  file: "../endcard/endcard.html",
  viewport: { width: 1440, height: 810 },
  story_now: "2026-11-03T08:50:00+01:00",
  warmup_ms: 0,
  async run(rec) {
    rec.hideCursor();
    rec.mark("start");
    await rec.hold(400);
    rec.mark("line fades up");
    await rec.hold(150);
    rec.mark("rule draws");
    await rec.hold(1200);
    rec.mark("still");
    await rec.hold(10250); // 12 s in all: the 3-minute cut holds the card for 11.5 s
  },
};
