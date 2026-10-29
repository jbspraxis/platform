# Visual pass: jbspraxis.com, 29 Oct 2026

Checked in the browser at a 1366×800 desktop viewport and a 375×812 phone viewport. Pages looked at: home (whole page), services, portfolio.

## Overall look
- Dark navy with neon cyan. Terminal and military theatre throughout:
  - "// SYS.ARCH : ONLINE", "INITIATE TRANSFORMATION", "STATUS: DIAGNOSTIC_MODE"
  - "THE PRAXIS STRIKE TEAM", "UNIT_01: ONLINE", "CLASSIFIED INTEL", "ACCESS DOSSIERS"
  - "ENGAGE JBS", "> SYSTEM BOOT... OK. SECURE CONNECTION ESTABLISHED."
- Headings are all caps and set in Inter, with Roboto Mono labels.
- A pharma or FMCG CFO or procurement manager reads this as a hacker or gaming site, not a vendor to trust with an ERP.
- Three font families render: Inter, Roboto Mono and Georgia.

## Homepage
- **Too long:** 8,726 px on desktop (about 11 screens) and 11,530 px on a phone (about 14 screens).
- **Too many sections (11):**
  1. Hero "JBS PRAXIS / BEYOND STRATEGY"
  2. "The strategy graveyard is full"
  3. "System architecture scan", a fake terminal
  4. "The Predictive Praxis Framework™", with tabs
  5. "Infrastructure that thinks"
  6. "Strike team"
  7. "Architecture in production", a testimonial carousel
  8. "Provision is not mystical. It is structured."
  9. "Operational chaos tax", a calculator
  10. CTA "Is your business running on systems or hope?"
  11. Footer
- **The hero says nothing concrete:**
  - The H1 is the company name.
  - The subhead is a 60-word paragraph about "a unified nervous system".
  - It names no sector, no outcome, no proof and no demo.
- **Blank viewport bug:** scrolling to about 1,600 px shows an entirely empty dark screen. The content is hidden until a reveal animation fires, so fast scrollers, screenshots and crawlers see nothing.
- **Fake terminal** ("System architecture scan"): buttons labelled [Fragmented_Data] [Compliance_Risk] [Capital_Leakage] [Outdated_Software] type out console text. Gimmick, no information.
- **Testimonials** are anonymous ("COO, Pan-African Logistics Network", "Commercial Director, Continental Pharma Manufacturer", "Managing Partner, Tier-1 Real Estate"). They are small italic cyan text in a carousel, so they have low credibility and are hard to read.
- **"Operational chaos tax" calculator (COPQ):**
  - It defaults to USD and has 5 sliders.
  - It carries a "Tactical Scratchpad" with a full calculator keypad bolted on (7/8/9/÷…).
  - The idea is good (cost of manual work), but the execution is cluttered.
- **Footer "The Praxis SLA":** "99.9% system uptime", "ZERO data loss, PostgreSQL arch", "ISO compliant tech maintenance" and "<30m critical incident response". These are unverifiable public commitments, and "ISO compliant" with no certificate is a liability.
- **Footer addresses:**
  - "Global HQ (West Africa): 21B Bode Ajakaiye Street, Lekki, Lagos"
  - "CEMAC Regional Desk: 1030 Avenue Douala Manga Bell, Douala"
  - Emails info@ and intelligence@, plus a phone number.
- **Navigation:**
  - Items: HOME, THE FIRM, SERVICES, PORTFOLIO, KAIZEN HUB, CLIENT PORTAL, plus a globe (language), a theme toggle and an "ENGAGE JBS" button.
  - There is no Industries/Sectors item, no Case studies item, and no Contact wording.
  - Client Portal sits in the main marketing nav.

## Phone
- No horizontal overflow (scroll width 375).
- The floating ">_" AI-agent button overlaps the hero's "INITIATE TRANSFORMATION" CTA.
- Long paragraphs fill the screen, and the H2s wrap to 3–4 lines.

## Services (9,870 px tall)
- Hero "THE PREDICTIVE PRAXIS FRAMEWORK / ENGINEERED RESILIENCE".
- A fake terminal prints "ISO 9001 Compliance: HARDCODED", which is a risky claim made as a joke.
- Copy: "We do not just look for flaws; we hunt for wasted capital and time", "Six Sigma", "Internal Control Systems".

## Portfolio (5,515 px)
- Hero "SANITIZED ARCHITECTURE / CLASSIFIED INTEL": "all identifying client data has been cryptographically scrubbed".
- **The real gold is buried:** "Client: Tier-1 Beverage Production & Distribution (CEMAC region). End-to-end logistics, fleet & asset tracking ERP." It covers:
  - returnable 20 L containers vanishing in the field
  - fuel fraud and odometer fraud
  - proof of delivery and cash reconciliation
  - a mobile-first PWA with camera-verified fuel logging and QR proof of delivery
  - "100% asset accountability"

  This is exactly the FMCG story the owner wants, but it is told in hacker theatre with no screenshots and no numbers beyond "100%".

## Every page
- Every page has the same `<title>`: "JBS Praxis | Predictive Architecture & Enterprise Intelligence".
