---
version: 1
slug: "templates-ranking-index-html-twig"
primary_target: "templates/ranking/index.html.twig"
related_targets: ["templates/ranking/results.html.twig","templates/ranking/learn_more.html.twig"]
---

# Ranking page

Mode: Operate (list page), learn-more is Read. Scope: ranking index, results partial, learn-more. Filters out of scope. Feature scope: GitHub #451.

Audience: search visitors scanning the world ranking, and signed-in riders checking the new month and their top-100 progress. Phone first.

## Direction contract

THESIS: Each monthly ranking is an issue of a music chart. The top three are its cover, everything after is the chart: oversized position numbers, movement under each number, "new" and "best ever" as real chart states. Refuses the feed of identical stacked photo cards and the stats-panel-on-top arrangement.

OWN-WORLD: DESIGN.md unchanged. Cool canvas, one white ledger card with hairline row dividers, Barlow Condensed tabular rank numerals in a fixed left column, Source Sans rows, action blue only for links and the meter bar, sunshine only for the New pill, success/danger ramps for movement arrows. Photos only on the cover (16:9) and as small 4:3 thumbnails.

STORY: The visitor sees which month this is and whether it just changed, their top-100 standing if signed in, the podium, then scans the chart with ridden marks, and loads more or jumps to a rank.

FIRST VIEWPORT: Page header (title + filter button). Month line (lead, muted-strong) with New pill, caption line "Next ranking in N days · How it works". Signed-in: top-100 meter card (38 / 100 in display numerals, bar with 25/50/75 notches, "+N legends ridden"). #1 cover card starts, photo loaded with high priority.

FORM: Chart ledger with a three-coaster cover (user-steered fusion of "the chart ledger" #2 and the dealt lead "the monthly edition" #7). Seed key 3c66a5ee.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Signature interaction

"Load 50 more" is a real ?page=N link; enhanced, it appends the next rows (180ms fade, reduced-motion none) and replaces the URL, so refresh and back keep the position.

## Adaptations

- From sm the #1 cover sits beside #2/#3 (two of three columns, both rows); its photo fills the height left by its text, close to 4:3, served from a 4:3 source. Phones keep 16:9.
- No "N new entries" link in the month line: new entries are reached through the "new this month" filter (maintainer decision).
- From a 56rem results width (desktop) the cover steps aside: #1–#3 are plain ledger rows, so every coaster gets the manufacturer, country and score columns. No medal colors, no larger photos (user decision). Phones keep the cover unchanged.
- Learn-more metric switch: one pill row with short labels (units-toggle pattern), scrolling sideways if a locale does not fit.
