// 60 s cut, shot 3 · Projects and budgets · storyboard 17.0–29.0 (local 0–12).
// The Excel window still (pipeline/excel) replaces the picture from 9.2 in the edit.
module.exports = {
  account: "coo",
  story_now: "2026-11-03T08:22:00+01:00",
  route: "/",
  async setup(rec) {
    await rec.askQuietly("Who is late at Apapa today, and what’s waiting on me?", "s2-late-and-approvals");
    await rec.page.locator('[data-demo="composer"]').first().click();
  },
  async run(rec) {
    rec.hideCursor();
    rec.frameAt("composer");
    await rec.to(0.3);
    rec.mark("typing");
    await rec.type("Which projects are over budget?", { duration: 2.6 });
    await rec.to(3.1);
    rec.mark("send");
    await rec.press("Enter");
    await rec.to(3.9);
    rec.zoom("paneTop");
    await rec.to(5.8);
    rec.zoom({ cx: 1560, cy: 520, s: 1.35 });
    await rec.to(6.6);
    rec.showCursor({ x: 1880, y: 640 });
    await rec.moveTo('[data-demo="tab-table"]', 650);
    await rec.to(7.3);
    rec.mark("table");
    await rec.click(null, { after: 0 });
    await rec.storyClock("2026-11-03T08:23:00+01:00");
    await rec.moveTo('[data-demo="download-excel"]', 600);
    await rec.to(8.4);
    rec.mark("download");
    await rec.click(null, { after: 0 });
    await rec.to(12.0);
  },
};
