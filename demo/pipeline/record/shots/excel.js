// The downloaded workbook open in real Excel (storyboard shot 3, 26.2–29.0): 1.3 on the title
// rows, easing to 1.4. Run excel/capture-excel.ps1 on captures/downloads first.
module.exports = {
  offline: true,
  file: "../excel/view.html",
  story_now: "2026-11-03T08:23:00+01:00",
  warmup_ms: 0,
  async run(rec) {
    rec.hideCursor();
    rec.frameAt({ cx: 900, cy: 420, s: 1.07 });
    rec.zoom({ cx: 900, cy: 400, s: 1.12 }, 2900);
    await rec.to(3.0);
  },
};
