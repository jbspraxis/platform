// 60 s cut, shot 2 · People and approvals · storyboard 6.0–17.0 (local 0–11).
module.exports = {
  account: "coo",
  story_now: "2026-11-03T08:15:00+01:00",
  route: "/",
  async setup(rec, { page }) {
    await page.locator('[data-demo="composer"]').first().click();
  },
  async run(rec) {
    rec.hideCursor();
    rec.frameAt("composer");
    await rec.to(0.3);
    rec.mark("typing");
    await rec.type("Who is late at Apapa today, and what’s waiting on me?", { duration: 4.4 });
    await rec.to(4.9);
    rec.mark("send");
    await rec.press("Enter");
    await rec.to(5.9);
    rec.zoom("pane");
    await rec.to(8.2);
    rec.zoom("thread");
    await rec.storyClock("2026-11-03T08:16:00+01:00");
    await rec.to(9.0);
    rec.showCursor({ x: 1880, y: 880 });
    await rec.moveTo('[data-demo="approve-LV-0412"]', 700);
    await rec.to(9.3);
    rec.mark("approve");
    await rec.click(null, { after: 0 });
    await rec.to(11.0);
  },
};
