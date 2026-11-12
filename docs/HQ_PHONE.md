# HQ on phones: the app shell

Agreed with the owner on 12 November 2026. On phones and tablets in portrait (900 px wide and below), HQ (`hq.jbspraxis.com`) is a native-feeling app with a top bar and a bottom bar, on the pattern of the MyCity App staff app (`praxisjbs/mycity-app`, decisions F16 to F18 and F28) and HOD Lekki (`docs/UI.md` section 6). Computers and tablets in landscape keep the Ink rail. The Client Portal is not touched.

## Owner decisions

| # | Question | Decision |
|---|---|---|
| 1 | The five places in the bottom bar | **Home, Mail, Leads, Projects, More.** Home is My workspace. The bell and the account move off the bar (decision 5). |
| 2 | A place the person cannot open | **Filled from a reserve**, and **Mail opened to everyone** with the Mail permission. A place the person cannot open goes to the next module they can, in the reserve's order: Finance, Team, Campaigns, Documents, Overview, Marketing, Company profile, Master data, Settings. Everyone has a full bar, and nobody sees a module they cannot open. |
| 3 | What More opens | **A tile launcher, as in MyCity**: a sheet from the bottom with the person's row (their profile, and the bell), the app actions (install, turn on notifications), every other module they can open as tiles four to a row in two groups (Work, Company), and Sign out at the foot. A module's pages (for example Sections, Versions, Client logos, Share links) become a strip of chips under the top bar. |
| 4 | What else comes with it | **The bell as a sheet** from the bottom; **the title on scroll** (the top bar shows the page's title once the page's own heading has scrolled away); **pull down to refresh** in the installed app; **Company profile tidied** for phones. |
| 5 | The top bar | **The logo mark, then the page's title, and the avatar on the right. Nothing else. Light only, no dark theme.** The avatar opens More (as in MyCity); the bell is More's bell, and the avatar carries the unread count. |

## What the owner sees

- **Top bar** (white, fixed at the top): the JBS mark (it goes to My workspace), the page's title once the page's own heading has scrolled under the bar, and the avatar with a red count of unread notifications. A page with no heading of its own shows its module's name all the time. The status bar above it stays Ink in the installed app (`docs/PWA.md`).
- **Pages of the module**: on any module with more than one page, a strip of chips under the top bar, the current one in teal. It scrolls sideways; the current chip is brought into view.
- **Bottom bar** (white, fixed at the foot, above the home indicator): five places, an icon and one word each. The current one has a teal pill behind its icon. Mail shows its unread count. More is highlighted when the page in view is one of its modules.
- **More**: tapping More or the avatar slides up a sheet. Drag the handle down, tap outside or press Esc to close it. Tapping the person's row opens My profile; the bell opens the notifications.
- **Notifications**: the bell's list as a sheet from the bottom, with "Notification settings" and the app actions at its foot.
- **Pull to refresh** (installed app only, where the browser has no refresh button): pull down at the very top of a page; a disc follows the finger, turns teal at the notch, and letting go loads the page again in place. It never starts mid-scroll, over a sheet or dialog, or inside a list scrolled away from its own top. A page can turn it off on an element with `data-pull="off"`.
- **Toasts, the install card and Mail's "Sending · Undo"** wait above the bottom bar.

## How it is built

- **One navigation registry**: `App\Support\HqNav`. `sections($user)` lists every module the person can open, in rail order, with its pages, counts and icons; `bar()` picks the five places (decision 2); `more()` groups the rest; `current()` names the module in view. The rail on computers, the bottom bar, More and the strip all draw it, so a link shows in all of them or none. A new module is one entry in `sections()`. Hiding a link is not a permission: every route still checks access itself.
- **The layout**: `resources/views/components/layouts/hq.blade.php`. The rail, the top bar (with the phone's mark, title and avatar), the strip, the bottom bar and the More sheet (Alpine; `x-trap` holds focus and the page still while it is open). The avatar opens More on phones with the browser event `hq-open-more`; More's bell opens the bell's panel with `hq-open-bell` (`resources/views/livewire/hq/components/notification-bell.blade.php`).
- **Styles**: `resources/css/hq-phone.css`, imported by `resources/css/app.css` after `hq-pwa.css` (one compiled stylesheet). Everything is under `max-width: 900px` and scoped to `.hq-shell` and `.hq-body`, which only the HQ layout has. On phones the window scrolls (the top bar sticks, the bottom bar is fixed); `--hq-top-h` and `--hq-bar-h` are the two bars' heights. The top bar sits at z-index 35: above the bottom bar (30), so the bell's sheet inside it covers the bar, and under page backdrops and drawers (40 and up).
- **Behaviour**: `resources/js/hq-phone.js`, imported by `resources/js/app.js`: the title on scroll (an IntersectionObserver on the first `.page-title`), centring the current chip, drag to close (`[data-hq-sheet]` with a `[data-hq-sheet-grab]` handle sends `hq-sheet-close`), and pull to refresh (`Livewire.navigate()` to the same address; a reload without Livewire). All of it starts again on `livewire:navigated`.
- **Icons**: the registry names Lucide icons in PHP; `scripts/icons.mjs` reads each `section()`'s fourth argument and `barIcon:` from `app/Support/HqNav.php`, so the bundle keeps them. `tests/Feature/HqPhoneShellTest.php` checks every one is in `resources/js/icons.js`.
- **Support text behind an (i)**: `<x-hq.info label="About versions">…</x-hq.info>` (`resources/views/components/hq/info.blade.php`): opens on hover on a computer and on a tap on a phone, and keeps its text on screen.
- **Mail open to everyone**: migration `2026_11_12_100000_open_mail_to_everyone` sets `mail_settings.open_to` to `everyone` with the same audit line as the Mailboxes button. An Owner account can limit it again on Mailboxes; Mail then leaves the bar for everyone else (`docs/MAIL.md`, decision 1).

## For a screen

- The top bar takes the page's title from its first `.page-title`. Keep one `h1.page-title` at the top of each page.
- Something that floats at the foot of the screen gets the class `hq-lift` so it waits above the bottom bar (the Tracker's message button has it).
- Something sticky at the top of a page, when the window scrolls on a phone, sticks at `top: calc(var(--hq-top-h) + var(--hqp-safe-top))`, under the top bar (Mail's reader toolbar does).
- A map, a signature pad or anything else that needs a downward drag: `data-pull="off"`.

## Tests

`tests/Feature/HqPhoneShellTest.php` (MySQL, as the other feature tests): the owner's bar; the reserve for every starter role; Mail leaving the bar when limited to Owner accounts; More holding every other module and nothing the person cannot open; the strip; the phone top bar; the icons. `tests/Feature/MyProfileTest.php` checks Sign out is only in the avatar's menu and in More. `tests/Feature/Mail/MailAccessTest.php` checks Mail is open to everyone and can be limited again.

Look at it in a browser at 390 px (phone) and 1440 px (computer): the bar and More on My workspace, Company profile (strip, title on scroll, the (i)), Finance (a long strip), Mail (the bar's count, the reader's toolbar).
