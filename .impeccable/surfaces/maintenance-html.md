---
version: 1
slug: "maintenance-html"
primary_target: "maintenance.html"
related_targets: []
---

# Surface brief: maintenance.html

Scope: the static page nginx serves (HTTP 503) for every URL while `deploy.sh` holds maintenance mode. Mode: Operate (the visitor's task is to understand the stop and get back to where they were).

Constraints: one self-contained file. During maintenance every path except `/favicon.ico` returns this page, so fonts, logo and images are inlined; no request to `/build/` or `/icon.svg`. Light theme only. Four locales (en, fr, es, de): language from the requested URL prefix, then the browser. Useful: detects the site's return and reloads the requested page itself; ride-operator voice ("Please remain seated", technical stop). Motion respects reduced-motion.

## Direction contract

THESIS: The page is the queue-entrance closure sign every rider knows: closed for now, reopening, with a live status. Refuses the centred card on a gradient.
OWN-WORLD: Canvas `bg`, a white sign panel (30rem max) with a thick ink frame, Barlow Condensed display, Source Sans 3 body, a sunshine live-status strip along the bottom of the sign (moved from a header band: kicker ban), two ink posts carrying the sign with a sunshine-and-ink queue chain slung between rings at mid-height, a plain ink spinner (a check on return). No links or buttons.
STORY: The visitor sees the ride is on a short technical stop, trusts it will reopen, and waits while the page checks every 10 s (and on tab focus or reconnect); when the site answers, the strip turns green and the page returns them to the URL they asked for. No Retry control (removed at the user's request).
FIRST VIEWPORT: Full logo top centre; the sign centred, max 30rem: huge condensed "Please remain seated", the announcement at lead size, then the sunshine status strip with the live dot and countdown; posts and chain below the sign; the thanks line at the foot.
FORM: Queue-entrance closure sign, candidate 3 of 7, seed key b5f8a696.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
