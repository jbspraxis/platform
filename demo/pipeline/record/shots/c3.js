// 3-min cut, chapter 3 · Projects and budgets · 41.0–56.6 (local 0–15.6), then the Excel shot.
module.exports = {
  account: "coo", story_now: "2026-11-03T08:22:00+01:00", route: "/",
  async setup(rec) {
    await rec.askQuietly("Who is late at Apapa today, and what’s waiting on me?", "s2-late-and-approvals");
    await rec.page.locator('[data-demo="composer"]').first().click();
  },
  async run(rec) {
    rec.hideCursor(); rec.frameAt("composer");
    await rec.to(1.8); rec.mark("typing");
    await rec.type("Which projects are over budget?", { duration: 2.6 });
    await rec.to(4.6); await rec.press("Enter");
    await rec.to(5.4); rec.zoom("paneTop");
    await rec.to(11.8); rec.zoom({ cx: 1560, cy: 520, s: 1.35 });
    await rec.to(12.3);
    rec.showCursor({ x: 1880, y: 640 });
    await rec.moveTo('[data-demo="tab-table"]', 550);
    await rec.to(12.9); await rec.click(null, { after: 0 });
    await rec.storyClock("2026-11-03T08:23:00+01:00");
    await rec.to(13.9);
    await rec.moveTo('[data-demo="download-excel"]', 600);
    await rec.to(14.6); rec.mark("download");
    await rec.click(null, { after: 0 });
    await rec.to(15.9);
  },
};
