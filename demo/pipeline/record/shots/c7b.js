// 3-min cut, chapter 7 (part 2) · the mailbox · 157.5–176.5 (local 0–19).
module.exports = {
  account: "coo", story_now: "2026-11-03T08:44:00+01:00", route: "/mail/th-harbourline",
  async run(rec) {
    rec.frameAt({ cx: 914, cy: 480, s: 1.35 });
    rec.showCursor({ x: 1060, y: 700 });
    await rec.to(3.3);
    await rec.moveTo('[data-demo="summarise-thread"]', 600);
    await rec.to(4.0); rec.mark("summarise");
    await rec.click(null, { after: 0 });
    await rec.to(5.1); rec.zoom({ cx: 1560, cy: 480, s: 1.45 });
    await rec.to(12.1); rec.zoom({ cx: 914, cy: 800, s: 1.45 });
    await rec.to(17.6);
    const send = await rec.center('[data-demo="reply-send"]');
    await rec.moveTo({ x: send.x - 90, y: send.y + 4 }, 700);
    await rec.to(19.0);
  },
};
