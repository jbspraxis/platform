// 60 s cut, shot 5 · From question to action · storyboard 37.0–46.0 (local 0–9).
module.exports = {
  account: "coo",
  story_now: "2026-11-03T08:38:00+01:00",
  route: "/",
  async setup(rec, { page }) {
    await page.locator('[data-demo="composer"]').first().click();
  },
  async run(rec) {
    rec.hideCursor();
    rec.frameAt("composer");
    await rec.to(0.3);
    rec.mark("typing");
    await rec.type("Book a stock-count review at Apapa tomorrow at 10", { duration: 4.1 });
    await rec.to(4.6);
    rec.mark("send");
    await rec.press("Enter");
    await rec.to(5.2);
    rec.zoom({ cx: 724, cy: 620, s: 1.4 });
    await rec.to(6.2);
    rec.showCursor({ x: 1880, y: 900 });
    await rec.moveTo('[data-demo="save-event"]', 650);
    await rec.to(6.9);
    rec.mark("save");
    await rec.click(null, { after: 0 });
    await rec.to(7.0);
    await rec.moveTo('[data-demo="open-calendar"]', 350);
    await rec.to(7.4); rec.mark("open calendar");
    await rec.click(null, { after: 0 });
    await rec.to(7.6);
    rec.frameAt({ cx: 896, cy: 430, s: 1.4 }); // the Wednesday column, 10:00
    rec.hideCursor();
    await rec.to(9.0);
  },
};
