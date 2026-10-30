// 3-min cut, chapter 6 (part 2) · the audit log, then AI spend · 127.0–140.0 (local 0–13). The COO.
module.exports = {
  account: "coo", story_now: "2026-11-03T08:33:00+01:00", route: "/",
  async run(rec) {
    rec.frameAt("full");
    rec.showCursor({ x: 600, y: 700 });
    await rec.moveTo('nav a:has-text("Audit log")', 350);
    await rec.to(0.4); await rec.click(null, { after: 0 });
    await rec.to(1.0); rec.zoom({ cx: 1205, cy: 360, s: 1.37 });
    await rec.to(7.6); rec.zoom("full", 600);
    await rec.to(8.2);
    await rec.moveTo('nav a:has-text("Settings")', 500);
    await rec.to(8.8); await rec.click(null, { after: 0 });
    await rec.to(9.2); rec.zoom({ cx: 724, cy: 330, s: 1.45 });
    await rec.to(13.0);
  },
};
