// 60 s cut, shot 6 · Work done, not just answered · storyboard 46.0–54.0 (local 0–8). Mail.
module.exports = {
  account: "coo",
  story_now: "2026-11-03T08:44:00+01:00",
  route: "/mail/th-harbourline",
  async run(rec) {
    rec.frameAt({ cx: 914, cy: 480, s: 1.35 });
    rec.showCursor({ x: 1060, y: 640 });
    await rec.moveTo('[data-demo="summarise-thread"]', 450);
    await rec.to(0.6);
    rec.mark("summarise");
    await rec.click(null, { after: 0 });
    await rec.to(1.7);
    rec.zoom({ cx: 1560, cy: 480, s: 1.45 });
    await rec.to(4.0);
    rec.zoom({ cx: 914, cy: 800, s: 1.45 });
    await rec.to(6.2);
    const send = await rec.center('[data-demo="reply-send"]');
    await rec.moveTo({ x: send.x - 90, y: send.y + 4 }, 700);
    rec.mark("rest by send");
    await rec.to(8.0);
  },
};
