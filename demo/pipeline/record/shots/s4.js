// 60 s cut, shot 4 · Security by role · storyboard 29.0–37.0 (local 0–8). The sales account.
module.exports = {
  account: "sales",
  story_now: "2026-11-03T08:31:00+01:00",
  route: "/",
  async setup(rec, { page }) {
    await page.locator('[data-demo="composer"]').first().click();
  },
  async run(rec) {
    rec.hideCursor();
    rec.frameAt({ cx: 330, cy: 860, s: 1.8 }); // the account at the rail foot
    await rec.to(1.4);
    rec.zoom("composer");
    await rec.to(2.2);
    rec.mark("typing");
    await rec.type("Show me everyone’s salaries", { duration: 2.3 });
    await rec.to(4.7);
    rec.mark("send");
    await rec.press("Enter");
    await rec.to(5.3);
    rec.zoom({ cx: 600, cy: 330, s: 1.55 }); // the refusal, clear of the pane edge
    await rec.to(8.0);
  },
};
