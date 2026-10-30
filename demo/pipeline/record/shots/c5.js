// 3-min cut, chapter 5 · Cash and receivables · 87.0–110.0 (local 0–23).
module.exports = {
  account: "coo", story_now: "2026-11-03T08:29:00+01:00", route: "/",
  async setup(rec) {
    await rec.askQuietly("How did each depot sell last week, and where is stock low?", "x1-sales-and-stock");
    await rec.page.locator('[data-demo="composer"]').first().click();
  },
  async run(rec) {
    rec.hideCursor(); rec.frameAt({ cx: 560, cy: 140, s: 1.5 });
    await rec.to(5.0); rec.zoom("composer");
    await rec.to(5.8); rec.mark("typing");
    await rec.type("Who owes us more than 60 days?", { duration: 2.5 });
    await rec.to(8.5); await rec.press("Enter");
    await rec.to(9.3); rec.zoom("paneTop");
    await rec.to(18.8); rec.zoom("full");
    await rec.to(19.9);
    rec.showCursor({ x: 1180, y: 900 });
    await rec.moveTo('[data-demo="chip-top"]', 650);
    await rec.to(20.6); rec.mark("record");
    await rec.click(null, { after: 0 });
    await rec.to(20.8); rec.zoom("pane");
    await rec.to(23.0);
  },
};
