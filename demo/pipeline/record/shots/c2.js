// 3-min cut, chapter 2 · People and approvals · 16.0–41.0 (local 0–25).
module.exports = {
  account: "coo", story_now: "2026-11-03T08:15:00+01:00", route: "/",
  async setup(rec, { page }) { await page.locator('[data-demo="composer"]').first().click(); },
  async run(rec) {
    rec.hideCursor(); rec.frameAt("composer");
    await rec.to(3.6); rec.mark("typing");
    await rec.type("Who is late at Apapa today, and what’s waiting on me?", { duration: 4.4 });
    await rec.to(8.2); await rec.press("Enter");
    await rec.to(9.4); rec.zoom("pane");
    await rec.to(12.3);
    rec.showCursor({ x: 1880, y: 640 });
    await rec.moveTo('[data-demo="tab-overview"]', 600);
    await rec.to(13.0); await rec.click(null, { after: 0 });
    await rec.to(16.0); rec.zoom("thread"); await rec.storyClock("2026-11-03T08:16:00+01:00");
    await rec.to(21.4);
    await rec.moveTo('[data-demo="approve-LV-0412"]', 650);
    await rec.to(22.1); rec.mark("approve");
    await rec.click(null, { after: 0 });
    await rec.to(25.0);
  },
};
