// 3-min cut, chapter 6 (part 1) · the refusal · 110.0–127.0 (local 0–17). The sales account.
module.exports = {
  account: "sales", story_now: "2026-11-03T08:31:00+01:00", route: "/",
  async setup(rec, { page }) { await page.locator('[data-demo="composer"]').first().click(); },
  async run(rec) {
    rec.hideCursor(); rec.frameAt({ cx: 330, cy: 860, s: 1.8 }); // the account at the rail foot
    await rec.to(3.2); rec.zoom("composer");
    await rec.to(4.0); rec.mark("typing");
    await rec.type("Show me everyone’s salaries", { duration: 2.3 });
    await rec.to(6.5); await rec.press("Enter");
    await rec.to(7.1); rec.zoom({ cx: 600, cy: 330, s: 1.55 }); // the refusal, clear of the pane edge
    await rec.to(12.0); rec.zoom("pane");
    await rec.to(17.0);
  },
};
