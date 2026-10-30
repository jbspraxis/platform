// 3-min cut, chapter 7 (part 1) · the booking · 140.0–157.5 (local 0–17.5).
module.exports = {
  account: "coo", story_now: "2026-11-03T08:38:00+01:00", route: "/",
  async setup(rec, { page }) { await page.locator('[data-demo="composer"]').first().click(); },
  async run(rec) {
    rec.hideCursor(); rec.frameAt("composer");
    await rec.to(3.0); rec.mark("typing");
    await rec.type("Book a stock-count review at Apapa tomorrow at 10", { duration: 4.1 });
    await rec.to(7.3); await rec.press("Enter");
    await rec.to(8.0); rec.zoom({ cx: 724, cy: 620, s: 1.4 });
    await rec.to(13.6);
    rec.showCursor({ x: 1880, y: 900 });
    await rec.moveTo('[data-demo="save-event"]', 650);
    await rec.to(14.4); rec.mark("save");
    await rec.click(null, { after: 0 });
    await rec.to(14.6);
    await rec.moveTo('[data-demo="open-calendar"]', 350);
    await rec.to(15.0); await rec.click(null, { after: 0 });
    await rec.to(15.2); rec.frameAt({ cx: 896, cy: 430, s: 1.4 }); rec.hideCursor();
    await rec.to(17.5);
  },
};
