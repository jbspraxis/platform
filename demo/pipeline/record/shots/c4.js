// 3-min cut, chapter 4 · Sales and stock by depot · 64.0–87.0 (local 0–23).
module.exports = {
  account: "coo", story_now: "2026-11-03T08:26:00+01:00", route: "/",
  async setup(rec) {
    await rec.askQuietly("Which projects are over budget?", "s3-projects-over-budget");
    await rec.page.locator('[data-demo="composer"]').first().click();
  },
  async run(rec) {
    rec.hideCursor(); rec.frameAt("composer");
    await rec.to(3.0); rec.mark("typing");
    await rec.type("How did each depot sell last week, and where is stock low?", { duration: 4.8 });
    await rec.to(8.0); await rec.press("Enter");
    await rec.to(9.0); rec.zoom("paneTop");
    await rec.to(14.6); rec.zoom({ cx: 1560, cy: 640, s: 1.45 });
    await rec.to(19.0); rec.zoom("pane"); // finish the move before the cursor aims at anything
    await rec.to(19.9);
    rec.showCursor({ x: 1880, y: 700 });
    await rec.moveTo('[data-demo="tab-table"]', 450);
    await rec.to(20.4); await rec.click(null, { after: 0 });
    await rec.to(20.7);
    await rec.moveTo('[data-demo="answer-pane"] th:has-text("Stock cover")', 450);
    await rec.to(21.4); rec.mark("sort");
    await rec.click(null, { after: 0 });
    await rec.to(23.0);
  },
};
