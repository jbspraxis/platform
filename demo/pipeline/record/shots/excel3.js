// 3-min cut: the workbook in Excel, 56.6–64.0: 1.3 on the title rows, then to the total row.
module.exports = {
  offline: true, file: "../excel/view.html", story_now: "2026-11-03T08:23:00+01:00", warmup_ms: 0,
  async run(rec) {
    rec.hideCursor();
    rec.frameAt({ cx: 900, cy: 420, s: 1.07 });
    await rec.to(3.9); rec.zoom({ cx: 900, cy: 640, s: 1.12 });
    await rec.to(7.6);
  },
};
