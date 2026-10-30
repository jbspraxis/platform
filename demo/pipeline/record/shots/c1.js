// 3-min cut, chapter 1 · Morning brief · 0.0–16.0.
module.exports = {
  account: "coo", story_now: "2026-11-03T07:45:00+01:00", route: "/", warmup_ms: 0,
  async run(rec) {
    rec.frameAt("full");
    await rec.to(2.0); rec.zoom({ cx: 560, cy: 290, s: 1.4 });
    await rec.to(7.6); rec.zoom({ cx: 890, cy: 290, s: 1.4 });
    await rec.to(13.0); rec.zoom("full");
    await rec.to(13.9);
    rec.showCursor({ x: 1900, y: 760 });
    await rec.moveTo('[data-demo="composer"]', 650);
    await rec.to(14.6); rec.mark("click composer");
    await rec.click(null, { after: 0 });
    await rec.to(16.0);
  },
};
