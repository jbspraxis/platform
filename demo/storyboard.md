# Storyboard: Ask your company anything

Working name "A morning with the COO". Two cuts from the same recorded morning at Ndali Group (fictional):
the **60-second cut** (silent captioned loop and narrated, runbook §6.1) and the **3-minute walkthrough**
(runbook §6.2). Written 30 October 2026. Every figure comes from
`C:\Users\Tom-Blake\ndali-demo\data\story-figures.json`; every question is typed exactly as in
`ndali-demo\docs\DEMO_SPEC.md` §4.5. The voice lines and their timings are in `voiceover.json`; this file
is the picture.

## Conventions (both cuts)

- **Frame.** 1920×1080 delivered (recorded at 3840×2160, so zooms stay sharp). Workspace layout: rail
  x 0–248; centre column x 248–1200 (top bar y 0–64, brief, thread, composer pinned at y ≈ 960–1040);
  answer pane x 1200–1920. Mail screen: rail, thread list x 248–628, thread view x 628–1200, pane.
- **Times** are seconds on the edit timeline, to 0.1 s. Straight cuts between shots; inside a shot, a change
  of screen is a 200 ms cross-fade at most.
- **Typing** at 12 characters a second, so a window lasts characters ÷ 12. The recorder's rhythm may vary
  per key, but the last character lands by the end of the window. Enter (or the send button) 0.2 s after
  the last character.
- **The voice never covers typing.** Voice comes before the typing window or after the question is sent.
  The French version uses the same picture and the same start times (`voiceover.json`).
- **Answer motion** (DEMO_SPEC §5.5): status lines about 0.5 s each, then the text fades in whole, chips,
  inline blocks, and the pane fills (KPIs settle over 600 ms, charts grow from the baseline over 600 ms).
  Things settle; nothing bounces, nothing counts up from zero.
- **Zoom** is given as a named framing: a centre point in 1920×1080 pixels and a scale between 1.0 and
  1.6. Framings clamp to the frame edges. A move takes 0.8 s with the settle easing
  `cubic-bezier(0.2, 0, 0, 1)`, one move at a time, no overshoot, and no move while a question is typed.
  Named framings, adjusted to the recorded layout if a panel sits a little differently:

  | Framing | Centre | Scale | Shows |
  |---|---|---|---|
  | Full | (960, 540) | 1.0 | the whole screen |
  | Brief | (724, 520) | 1.3 | greeting, the 4 KPI tiles, the composer |
  | Composer | (724, 860) | 1.6 | the composer and the end of the thread (the question reads on a phone) |
  | Thread | (724, 620) | 1.45 | the latest answer, its chips and inline blocks |
  | Pane | (1560, 500) | 1.45 | the whole answer pane |
  | Pane top | (1560, 400) | 1.5 | the pane's KPIs and chart title |

- **Cursor.** Synthetic, added in the edit. Glides 0.5 s with the settle easing; a click is a 100 ms press,
  no ripple. Hidden while typing.
- **Clock.** The recorder sets the story clock per shot (DEMO_SPEC §5.6). Where a write happens a minute
  after the question (approve 08:16, Excel 08:23), the clock steps during a zoom move, so the top bar, the
  toast, the Excel file and the audit log agree.
- **Captions** (silent loop only): rendered by `pipeline/captions/render.js` (Ink panel bottom left, the
  story time in Bright Teal, the text in white Plus Jakarta Sans 600), 150 ms fade in, 200 ms fade out,
  at most 7 words, sentence case, no full stop, never on screen while a question is typed. Framings after
  a question is sent keep the key numbers clear of the bottom-left 900×200 px, where the caption sits.
- **Sound.** Narrated versions: the voice plus a licensed bed at about −24 dB, ducked under the voice. The
  silent loop has no sound.
- **On-screen text stays English** in the French versions.

---

## A. The 60-second cut (61.0 s)

One question per shot; every answer lands in the pane; every shot ends in an action.

| # | In–out | Story time | Question or trigger | Ends in | Loop caption |
|---|---|---|---|---|---|
| 1 | 0.0–6.0 | 07:45 | none: the morning brief | click into the composer | Before you ask, the brief is ready |
| 2 | 6.0–17.0 | 08:15 | Who is late at Apapa today, and what’s waiting on me? | Approve LV-0412 | People and approvals, in one answer |
| 3 | 17.0–29.0 | 08:22 | Which projects are over budget? | Download Excel, the file opens | Projects and money, with the evidence |
| 4 | 29.0–37.0 | 08:31 | Show me everyone’s salaries (sales account) | refused and logged | A sales associate’s account / Security by role, enforced |
| 5 | 37.0–46.0 | 08:38 | Book a stock-count review at Apapa tomorrow at 10 | Save, in the calendar | From question to action |
| 6 | 46.0–54.0 | 08:44 | “Summarise and draft a reply” button | reply drafted | Work done, not just answered |
| 7 | 54.0–61.0 | — | end card | the call to action | none (the card is the text) |

The silent loop is the same picture, 61.0 s, and loops from the end card back to shot 1.

### Shot 1 · Morning brief · 0.0–6.0

- **Screen.** Workspace at 07:45, signed in as Amaka Obi, COO. Top bar: Workspace · Tuesday 3 November ·
  07:45 · AO. Brief: “Good morning, Amaka”, Tuesday 3 November 2026, and four tiles: Sales yesterday
  **₦58.6m** (Douala 21.4m FCFA) · Cash today **₦412.8m** (96.4m FCFA, as of 07:30) · Stock-outs
  **5 lines** (3 at Apapa, 2 at Ibadan) · Staff present **125 of 140** (7 late at Apapa). “Needs you ·
  3 approvals”. Answer pane: four tabs disabled, “Answers open here: numbers, charts, tables and the
  sources behind them.” Composer: “Ask about sales, stock, cash, people or projects”.
- **Beats.** 0.0 the screen is complete, tile values at 97% · 0.3–0.9 the four values settle · 1.0–1.8 zoom
  to Brief (1.3) · 4.6 the cursor enters from the right · 5.4 **clicks into the composer** (caret shows).
- **Typed.** Nothing. **Pane tab.** None (empty, tabs disabled).
- **Zoom.** Full → Brief 1.3 at 1.0–1.8, hold.
- **Loop caption.** 1.0–4.6 · 07:45 · Before you ask, the brief is ready
- **Voice (EN).** 0.3: “07:45 in Lagos. The company reports before the COO asks.”

### Shot 2 · People and approvals · 6.0–17.0

- **Screen.** Workspace at 08:15, composer focused.
- **Typed.** 6.3–10.7: `Who is late at Apapa today, and what’s waiting on me?` (53 characters, 4.4 s).
  Enter at 10.9.
- **Beats.** 10.9–11.9 status lines “Reading attendance at Apapa”, “Checking approvals waiting on you” ·
  11.9 the answer fades in (7 late: 5 clocked in after 07:10, 2 not in yet; 3 approvals waiting), chips
  “Attendance · Apapa · today 07:00–08:15” and “Approvals · waiting on you”, the 3 approvals inline
  (LV-0412 leave, Yetunde Lawal, 9 to 13 November · EXP-2291 fuel, ₦1,486,500 · PO-1187 pallet racking,
  ₦7,940,000) · the pane fills on **Table** (default): the 7 late staff, from Ifeanyi Okoro 07:18 to
  Kunle Ajayi 08:03, Musa Garba and Blessing Etim “Not clocked in” · 14.2 the clock steps to 08:16 during
  the zoom move · 15.3 **clicks Approve on LV-0412**: the row reads “Approved 08:16” · 15.5–17.0 toast
  “Leave approved for Yetunde Lawal, 9 to 13 November. Yetunde has been notified.”
- **Clicks.** Approve (LV-0412). **Pane tab.** Table.
- **Zoom.** Composer 1.6 through typing · 11.9–12.7 to Pane 1.45 · 14.2–15.0 to Thread 1.45 (the approvals).
- **Loop caption.** 11.8–15.2 · 08:15 · People and approvals, in one answer
- **Voice (EN).** 10.9: “Seven late at Apapa, 3 approvals waiting. One click approves the leave.”

### Shot 3 · Projects and budgets · 17.0–29.0

- **Screen.** Workspace at 08:22; the brief has compacted to a row of 4 small tiles; the first answer above.
- **Typed.** 17.3–19.9: `Which projects are over budget?` (31 characters, 2.6 s). Enter at 20.1.
- **Beats.** 20.1–20.7 status “Reading project budgets and actuals” · 20.7 the answer, its chip and the
  actions Download Excel and Open in workspace · the pane fills on **Overview**: KPIs **4 over budget**,
  **₦38.2m over in total**, worst **Ibadan cold room 109.0%**; the chart “4 projects are over budget,
  ₦38.2m in total” grows: % of budget spent for all 8 projects, horizontal bars, a 100% budget line, the
  4 over budget in the bad tone (Ibadan cold room 109.0%, Route optimisation, Lagos 107.0%, ERP rollout
  106.5%, Apapa depot expansion 104.8%) · 24.3 **clicks Table**: the 8 projects (budget, actual, over,
  % spent, status, lead) · the clock steps to 08:23 · 25.4 **clicks Download Excel**: toast “Excel file
  ready: projects-over-budget-2026-11-03.xlsx” · 26.2 cross-fade to the file open in Excel: the title
  row, the question, the sources, “Generated Tuesday 3 November 2026, 08:23 (WAT)”, the formatted
  table with its total row (₦38,204,600 over).
- **Clicks.** Table tab, Download Excel. **Pane tab.** Overview, then Table.
- **Zoom.** Composer 1.6 through typing · 20.9–21.7 to Pane top 1.5 · 24.0–24.8 to Pane 1.35 · on the Excel
  file, 1.3 on the title rows, easing to 1.4 by 29.0.
- **Loop caption.** 21.2–28.6 · 08:22 · Projects and money, with the evidence
- **Voice (EN).** 20.2: “Four projects are over budget, ₦38.2m in total. The evidence goes to Excel,
  sources included.”

### Shot 4 · Security by role · 29.0–37.0

- **Screen.** Workspace at 08:31, signed in as **Chidi Okeke, Sales associate, Ikeja**. The rail shows only
  Workspace, Calendar, Mail, Sales and stock; his name and role at the rail foot.
- **Beats.** 29.0 framing shows the rail foot and the composer · 30.4–31.2 move to Composer · typing ·
  33.7–34.2 status “Checking your permissions” · 34.2 the **refusal**: a lock in a bad-tint circle, “You
  don’t have permission to view salary data. Your request was recorded in the audit log.”, then “Audit
  entry 118204 · 08:31”. The pane has only **Sources** enabled: “Payroll: not read, no permission”.
- **Typed.** 31.2–33.5: `Show me everyone’s salaries` (27 characters, 2.3 s). Enter at 33.7.
- **Clicks.** None after send: the action is the system’s, the request refused and recorded.
  **Pane tab.** Sources.
- **Zoom.** 1.25 at (760, 620) from 29.0 · 30.4–31.2 to Composer 1.6 · 34.3–35.1 to the refusal, 1.55 at
  (724, 760).
- **Loop captions.** 29.2–31.0 · 08:31 · A sales associate’s account · then 34.4–36.9 · 08:31 · Security by
  role, enforced
- **Voice (EN).** 29.1: “A sales associate asks.” · 34.1: “Refused by role, and logged.”

### Shot 5 · From question to action · 37.0–46.0

- **Screen.** Workspace at 08:38, the COO again, composer focused.
- **Typed.** 37.3–41.4: `Book a stock-count review at Apapa tomorrow at 10` (49 characters, 4.1 s).
  Enter at 41.6.
- **Beats.** 41.6–42.1 status “Checking calendars for Wednesday 4 November” · 42.1 the **pre-filled form**
  inline: Stock-count review, Apapa · Wednesday 4 November 2026 · 10:00 to 11:00 (WAT) · Apapa depot,
  meeting room 2 · Amaka Obi (COO), Rotimi Adewale (Depot manager, Apapa), Ngozi Uche (Inventory
  controller), Ibrahim Yusuf (Head of supply chain) · notes “Count noodles and beverages first: 3
  stock-outs at Apapa this morning.” The assistant fills it; the person saves it · 43.9 **clicks Save** ·
  44.3 cut to the Calendar, week of 2 to 8 November: on Wednesday, Weekly sales review 09:00–09:45, the
  new **Stock-count review, Apapa 10:00–11:00** with a 1 s primary ring that fades, Douala branch call
  14:00–14:30.
- **Clicks.** Save. **Pane tab.** Record (the form’s details).
- **Zoom.** Composer 1.6 through typing · 42.2–43.0 to Thread 1.4 on the form · Calendar: 1.4 on the
  Wednesday column from the cut.
- **Loop caption.** 42.3–45.8 · 08:38 · From question to action
- **Voice (EN).** 41.7: “A form, filled in. Saved, and in the calendar.”

### Shot 6 · Work done, not just answered · 46.0–54.0

- **Screen.** Mail at 08:44. Thread list: “Ndali Noodles delivery: revised date”, Olumide Bakare,
  Harbourline Foods Ltd (selected). The thread view shows the supplier’s messages; the pane on the right.
- **Trigger.** 46.6 **clicks “Summarise and draft a reply”** in the thread header. It sends the question
  “Summarise this thread and draft a reply” with the thread as context: nothing is typed.
- **Beats.** 46.8–47.6 status “Reading the thread”, “Checking noodle stock at Apapa and Ibadan” · 47.6 the
  summary in the assistant panel: 2,400 cartons of Ndali Noodles 70g due Wednesday 4 November now
  arrive Monday 9 November, 5 days late (flour held at Tin Can Island port); stock impact: Apapa has
  3.1 days of noodle cover, Ibadan 6.4 days · 50.0 the **draft fills the reply editor** (fades in
  whole): a part delivery of 800 cartons to Apapa by Thursday 5 November, confirmation by 14:00 today ·
  53.0 the cursor rests beside Send; Send is not pressed.
- **Clicks.** Summarise and draft a reply. **Pane tab.** Record (the Apapa noodle stock line).
- **Zoom.** 1.35 at (914, 480) from 46.0 · 47.7–48.5 to 1.45 on the assistant panel · 50.0–50.8 to 1.45 on
  the draft.
- **Loop caption.** 47.0–53.6 · 08:44 · Work done, not just answered
- **Voice (EN).** 46.2: “A supplier’s delay, summarised with the stock impact. The reply is drafted,
  ready to send.”

### Shot 7 · End card · 54.0–61.0

- **Screen.** 54.0 a 320 ms cross-fade to the end card (`pipeline/endcard/endcard.html`): Mist `#F2F5F7`;
  the horizontal logo (`brand/kit/logo/jbs-praxis-horizontal-colour.svg`), the only JBS Praxis mark in
  the film; the Newsreader line **“Ask your company anything.”**; the support line “We design how your
  organisation runs, then build the system that answers for it.”; the button **“Book a free audit and
  demo”**; `jbspraxis.com`. No other text.
- **Beats.** 54.15–55.35 the Praxis rule (2 px, Ink then Teal) draws left to right once, `--motion-story`
  with the settle easing · then stillness to 61.0. Nothing loops inside the card.
- **Zoom.** Full 1.0. **Cursor.** None. **Loop caption.** None.
- **Voice (EN).** 54.4: “Straight answers, from your own data. Ask your company anything.”

---

## B. The 3-minute walkthrough (3:08, 188.0 s)

The same morning in full, in eight YouTube chapters. Each chapter: one question, the pane, one action
(chapter 6 shows a refusal, then the log; chapter 7 holds two shots, the booking and the mailbox).

| Chapter | In–out | YouTube | Story time | Question or trigger | Ends in |
|---|---|---|---|---|---|
| 1 Morning brief | 0.0–16.0 | 0:00 | 07:45 | none: the morning brief | click into the composer |
| 2 People and approvals | 16.0–41.0 | 0:16 | 08:15 | Who is late at Apapa today, and what’s waiting on me? | Approve LV-0412 |
| 3 Projects and budgets | 41.0–64.0 | 0:41 | 08:22 | Which projects are over budget? | Download Excel, the file opens |
| 4 Sales and stock by depot | 64.0–87.0 | 1:04 | 08:26 | How did each depot sell last week, and where is stock low? | sort the table by days of cover |
| 5 Cash and receivables | 87.0–110.0 | 1:27 | 08:29 | Who owes us more than 60 days? | open the record of the largest debtor |
| 6 Security and the audit log | 110.0–140.0 | 1:50 | 08:31, 08:33 | Show me everyone’s salaries (sales account) | open the audit log, then AI spend |
| 7 From question to action | 140.0–176.5 | 2:20 | 08:38, 08:44 | Book a stock-count review at Apapa tomorrow at 10; then the “Summarise and draft a reply” button | Save; reply drafted |
| 8 End card | 176.5–188.0 | 2:56 | — | — | the call to action |

### Chapter 1 · Morning brief · 0.0–16.0 · 07:45

Same screen as 60-second shot 1, held longer. Pane tab: none (empty, tabs disabled). Typed: nothing.

| Time | Picture | Zoom | Voice (EN) |
|---|---|---|---|
| 0.0 | 320 ms fade up from Mist to the workspace; tile values at 97% | Full | |
| 0.3–0.9 | the four KPI values settle | | |
| 0.5–4.5 | a quiet note bottom left, Plus Jakarta Sans 500, 26 px, Slate on a Paper panel: “Ndali Group is a fictional company. All names and figures are demo data.” | | 0.5 “07:45. Before the COO asks anything, Ndali Group reports: yesterday’s sales, cash in naira and CFA francs, 5 stock-outs, and who is in.” |
| 2.0–2.8 | Sales yesterday ₦58.6m (Douala 21.4m FCFA), Cash today ₦412.8m (96.4m FCFA) | 1.4 at (560, 290) | |
| 7.6–8.4 | Stock-outs 5 lines (3 at Apapa, 2 at Ibadan), Staff present 125 of 140 (7 late at Apapa) | pan, 1.4 at (890, 290) | |
| 13.0–13.8 | the whole brief and the composer | to Full | |
| 14.6 | **clicks into the composer** | | |

### Chapter 2 · People and approvals · 16.0–41.0 · 08:15 → 08:16

Typed 19.6–24.0: `Who is late at Apapa today, and what’s waiting on me?` (53 characters, 4.4 s), Enter 24.2.
Clicks: Overview tab, Approve (LV-0412). Pane tabs: Table (default), then Overview.

| Time | Picture | Zoom | Voice (EN) |
|---|---|---|---|
| 16.0 | workspace at 08:15, composer focused | Composer 1.6 | 16.2 “At 08:15, a question about Apapa.” |
| 19.6–24.0 | **typing** (no voice) | hold | |
| 24.2–25.2 | status “Reading attendance at Apapa”, “Checking approvals waiting on you” | | |
| 25.2 | answer, chips, the 3 approvals inline; pane on **Table**: the 7 late staff, 07:18 to 08:03, 2 not clocked in | 25.4–26.2 to Pane 1.45 | 25.2 “Seven people are late at Apapa: 5 clocked in after 07:10, and 2 aren’t in yet.” |
| 29.0 | **clicks Overview**: KPIs 7 late, 2 not yet in, 3 approvals settle; the chart of clock-ins at Apapa per 10-minute slot (06:30–08:10) grows, late slots in the warn tone | | |
| 32.0–32.8 | the inline approvals: LV-0412 leave (Yetunde Lawal, 9 to 13 November), EXP-2291 fuel ₦1,486,500, PO-1187 pallet racking ₦7,940,000; the clock steps to 08:16 | to Thread 1.45 | 32.4 “Three approvals are waiting: a leave request, an expense claim and a purchase order.” |
| 38.1 | **clicks Approve on LV-0412**: “Approved 08:16”; toast “Leave approved for Yetunde Lawal, 9 to 13 November. Yetunde has been notified.” to 41.0 | | 38.2 “One click approves the leave.” |

### Chapter 3 · Projects and budgets · 41.0–64.0 · 08:22 → 08:23

Typed 42.8–45.4: `Which projects are over budget?` (31 characters, 2.6 s), Enter 45.6.
Clicks: Table tab, Download Excel. Pane tabs: Overview (default), then Table.

| Time | Picture | Zoom | Voice (EN) |
|---|---|---|---|
| 41.0 | workspace at 08:22, the brief compacted to one row, the first answer above | Composer 1.6 | 41.2 “Then, the projects.” |
| 42.8–45.4 | **typing** (no voice) | hold | |
| 45.6–46.2 | status “Reading project budgets and actuals” | | |
| 46.2 | answer and actions (Download Excel, Open in workspace); pane on **Overview**: KPIs 4 over budget, ₦38.2m over in total, worst Ibadan cold room 109.0%; the chart “4 projects are over budget, ₦38.2m in total” grows (% of budget spent, 8 projects, 100% budget line, 4 bars in the bad tone) | 46.4–47.2 to Pane top 1.5 | 46.4 “Four of the 8 projects are over budget, ₦38.2m in total.” |
| 53.6 | **clicks Table**: the 8 projects; the clock steps to 08:23 | 53.4–54.2 to Pane 1.35 | 54.0 “The table goes to Excel: a formatted sheet with the question, the sources and the time.” |
| 55.6 | **clicks Download Excel**; toast “Excel file ready: projects-over-budget-2026-11-03.xlsx” | | |
| 56.6 | cross-fade to the file open in Excel: title row, question, sources, “Generated Tuesday 3 November 2026, 08:23 (WAT)” | 1.3 on the title rows | |
| 60.5–61.3 | the formatted table and its total row (₦38,204,600 over) | move to the total row, 1.3 | |

### Chapter 4 · Sales and stock by depot · 64.0–87.0 · 08:26

Typed 67.0–71.8: `How did each depot sell last week, and where is stock low?` (58 characters, 4.8 s), Enter 72.0.
Clicks: Table tab, the cover column header. Pane tabs: Overview (default), then Table.

| Time | Picture | Zoom | Voice (EN) |
|---|---|---|---|
| 64.0 | workspace at 08:26, composer focused | Composer 1.6 | 64.2 “Next, sales and stock by depot.” |
| 67.0–71.8 | **typing** (no voice) | hold | |
| 72.0–72.8 | status “Reading sales by depot, 26 to 31 October”, “Checking stock cover” | | |
| 72.8 | answer; pane on **Overview**: KPIs Lagos (Apapa) ₦168.2m (+3.8% on the week before), Ibadan ₦94.7m (−1.2%), Douala 112.5m FCFA (+6.1%) | 73.0–73.8 to Pane top 1.5 | 73.0 “Sales by depot, in naira and CFA francs, side by side.” |
| 78.6–79.4 | the chart: days of stock cover by depot and category (beverages, noodles, detergents), grouped bars, Apapa noodles 3.1 in the warn tone | to 1.45 at (1560, 640) | 79.2 “Stock is lowest on noodles at Apapa: 3.1 days of cover.” |
| 84.4 | **clicks Table**: depot × category, sales and cover | to Pane 1.45 | |
| 85.4 | **clicks the cover column header**: sorted ascending, Apapa noodles 3.1 days on the first row | | |

### Chapter 5 · Cash and receivables · 87.0–110.0 · 08:29

Typed 92.8–95.3: `Who owes us more than 60 days?` (30 characters, 2.5 s), Enter 95.5.
Clicks: the chip “Mile 12 Provisions Ltd”. Pane tabs: Overview (default), then Record.

| Time | Picture | Zoom | Voice (EN) |
|---|---|---|---|
| 87.0 | workspace at 08:29; the compact tile Cash today ₦412.8m (96.4m FCFA, as of 07:30) | 1.5 at (560, 140) | 87.2 “Cash this morning: ₦412.8m.” |
| 92.0–92.8 | composer focused | to Composer 1.6 | |
| 92.8–95.3 | **typing** (no voice) | hold | |
| 95.5–96.1 | status “Reading receivables ageing” | | |
| 96.1 | answer; pane on **Overview**: KPIs ₦96.3m over 60 days across 14 customers, 28.4m FCFA across 3 in Douala; the chart of Nigerian receivables by age (0–30, 31–60, 61–90, 90+ days) grows | 96.3–97.1 to Pane top 1.5 | 96.3 “More than 60 days: ₦96.3m across 14 customers, and 28.4m FCFA in Douala.” |
| 107.6 | **clicks the chip “Mile 12 Provisions Ltd”**: the **Record** tab opens the customer (Apapa, ₦18,412,600, 74 days) | 107.8–108.6 to Pane 1.45 | |

### Chapter 6 · Security and the audit log · 110.0–140.0 · 08:31, then 08:33

Typed 114.0–116.3: `Show me everyone’s salaries` (27 characters, 2.3 s), Enter 116.5, from the sales account.
Clicks: Audit log, Settings › AI spend (as the COO). Pane tab: Sources (the only one enabled).

| Time | Picture | Zoom | Voice (EN) |
|---|---|---|---|
| 110.0 | workspace at 08:31 as **Chidi Okeke, Sales associate, Ikeja**; the rail shows Workspace, Calendar, Mail, Sales and stock | 1.25 at (760, 620): the rail foot and the composer | 110.1 “Now a different account: a sales associate in Ikeja.” |
| 113.2–114.0 | composer focused | to Composer 1.6 | |
| 114.0–116.3 | **typing** (no voice) | hold | |
| 116.5–117.0 | status “Checking your permissions” | | |
| 117.0 | the refusal: lock icon, “You don’t have permission to view salary data. Your request was recorded in the audit log.”, “Audit entry 118204 · 08:31” | 117.1–117.9 to 1.55 at (724, 760) | 117.2 “The answer is a refusal. The role has no payroll permission, so nothing was read, and the request was logged.” |
| 122.0–122.8 | pane **Sources**: “Payroll: not read, no permission” | to Pane 1.45 | |
| 127.0 | cut: the COO’s workspace at 08:33; 127.4 **clicks Audit log**; 127.8 the log, newest first: 08:31:12 Chidi Okeke, Sales associate · Asked the assistant for salary data · Refused: no payroll permission (bad tone, lock), then the COO’s morning down to 07:45:10 Signed in | 128.0–128.8 to 1.4 on the newest rows | 127.6 “The COO finds it at the top of the audit log, among the morning’s answers, exports and approvals.” |
| 135.4 | **clicks Settings › AI spend**: November 2026, ₦21,860 of ₦250,000 (8.7%), a bar to the cap with the 80% alert mark, 412 requests, October 2026 ₦203,480, spend by role | 135.8–136.6 to 1.45 on the bar | 135.6 “And AI spend has a monthly cap, in naira.” |

### Chapter 7 · From question to action · 140.0–176.5 · 08:38, then 08:44

Booking: typed 143.0–147.1: `Book a stock-count review at Apapa tomorrow at 10` (49 characters, 4.1 s), Enter 147.3.
Mailbox: the button “Summarise and draft a reply” sends “Summarise this thread and draft a reply”; nothing
typed. Clicks: Save; Summarise and draft a reply. Pane tabs: Record (the form), then Record (the Apapa noodle
stock line).

| Time | Picture | Zoom | Voice (EN) |
|---|---|---|---|
| 140.0 | workspace at 08:38, the COO, composer focused | Composer 1.6 | 140.2 “Finally, from question to action.” |
| 143.0–147.1 | **typing** (no voice) | hold | |
| 147.3–147.8 | status “Checking calendars for Wednesday 4 November” | | |
| 147.8 | the pre-filled form inline (as in 60-second shot 5): Wednesday 4 November 2026, 10:00 to 11:00 (WAT), Apapa depot, meeting room 2, 4 attendees, the note on the 3 stock-outs | 148.0–148.8 to Thread 1.4 | 148.0 “A form, filled in: Wednesday at 10:00, Apapa depot, 4 attendees. The COO saves it.” |
| 154.4 | **clicks Save** | | |
| 154.8 | cut to the Calendar: Wednesday, the new Stock-count review, Apapa 10:00–11:00 with a 1 s ring that fades, between the 09:00 sales review and the 14:00 Douala call | 1.4 on the Wednesday column | |
| 157.5 | cut to Mail at 08:44: “Ndali Noodles delivery: revised date”, Harbourline Foods Ltd | 1.35 at (914, 480) | 157.7 “In the mailbox, a supplier has moved a delivery.” |
| 161.5 | **clicks “Summarise and draft a reply”** | | |
| 161.7–162.5 | status “Reading the thread”, “Checking noodle stock at Apapa and Ibadan” | | |
| 162.5 | the summary (2,400 cartons, 5 days late, now Monday 9 November) and the stock impact (Apapa 3.1 days of noodle cover, Ibadan 6.4 days) | 162.6–163.4 to 1.45 on the assistant panel | 162.8 “The summary comes with the stock impact: 3.1 days of noodle cover at Apapa.” |
| 169.6 | the draft fills the reply editor: a part delivery of 800 cartons to Apapa by Thursday 5 November, confirmation by 14:00 today | 169.6–170.4 to 1.45 on the draft | 170.3 “And a reply is drafted, asking for a part delivery of 800 cartons.” |
| 176.0 | the cursor rests beside Send; Send is not pressed | | |

### Chapter 8 · End card · 176.5–188.0

The same card as 60-second shot 7: a 320 ms cross-fade at 176.5; the Praxis rule draws once at 176.8–178.0;
stillness to 188.0. Full framing, no cursor.

| Time | Voice (EN) |
|---|---|
| 176.9 | “Straight answers, from your own data. Ask your company anything.” |
| 183.4 | “Book a free audit and demo: jbspraxis.com.” |

---

## C. Notes for the recording and the edit

- **The French versions** keep this picture frame for frame. Their lines are written to fit the same start
  times and end before the next typing window (checked in `voiceover.json`).
- **Clock steps.** 60-second cut: 08:15 → 08:16 at 14.2; 08:22 → 08:23 before 25.4. 3-minute cut: 08:15 →
  08:16 at 32.0; 08:22 → 08:23 at 53.6. The approval and the Excel export carry those times into the audit
  log (08:16:05, 08:23:40).
- **Audit log in chapter 6.** The 3-minute cut also asks the sales and receivables questions at 08:26 and
  08:29. `story-figures.json` lists no audit entries for them; add two (“Assistant read Sales and stock”,
  “Assistant read Receivables”) before recording, or the log will not show every answer the voice mentions.
- **On-screen summary in the mailbox** says Apapa “would run out on Friday 6 November”. The voice says only
  the days of cover, a figure measured today, never a projected date.
- **Legibility.** Key words to check in the 640 px preview: the four tile values; “7”, “2”, “3” in shot 2;
  “₦38.2m” and the chart title; the refusal sentence; the form’s date and time; the summary’s “3.1 days”;
  the end card line. Every framing above was chosen so these read at 640 px.
