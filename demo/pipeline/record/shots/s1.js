// 60 s cut, shot 1 · Morning brief · storyboard 0.0–6.0 (shot-local times below).
module.exports = {
  account: "coo",
  story_now: "2026-11-03T07:45:00+01:00",
  route: "/",
  warmup_ms: 0, // the four KPI values settle on camera, from the first frame
  async run(rec) {
    rec.frameAt("full");
    rec.mark("brief");
    await rec.to(1.0);
    rec.zoom("brief");
    await rec.to(4.2);
    rec.zoom("composer");
    await rec.to(4.6);
    rec.showCursor({ x: 1900, y: 760 });
    await rec.moveTo('[data-demo="composer"]', 650);
    await rec.to(5.3);
    rec.mark("click composer");
    await rec.click(null, { after: 0 });
    await rec.to(6.0);
  },
};
