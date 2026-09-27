---
target: app navigation (header, footer, guest sheet, search dialog)
total_score: 24
max_score: 36
na_heuristics: 9
p0_count: 0
p1_count: 3
target_identity: "file:templates/base.html.twig"
target_fingerprint: "sha256:66615343bc24d246c104f5bc7c24dfc98bca722af8b814dc3d4c0239635f58b6"
target_path: templates/base.html.twig
timestamp: 2026-09-23T20-21-59Z
slug: templates-base-html-twig
---
Method: dual-agent (A: design review · B: detector + browser)

## Design Health Score
1 Visibility 3 · 2 Real world 3 · 3 Control 3 · 4 Consistency 2 · 5 Error prevention 3 · 6 Recognition 3 · 7 Flexibility 2 · 8 Aesthetic 2 · 9 Error recovery n/a · 10 Help 3. Total 24/36 (Acceptable).

## Design Specificity Verdict
Only the tab bar is authored for Captain Coaster. Top bar and footer are template-generic: no brand color, no coaster character. Detector: 0 CLI findings in nav templates; browser: advisory thin-border+wide-shadow on Nav/SearchField.html.twig:35 (also AccountMenu); footer viewport-edge is a false positive; the rest is legacy page content or the overlay itself.

## Priority Issues
- [P1] Legacy filter sidebar (z 98) paints over sticky desktop header (z 40) on Ranking; header container misaligned with content column when sidebar present. Fix: sidebar under header, sticky at top-16. /impeccable harden
- [P1] Top bar has no presence and doubles with page_header (two white bands, 175px chrome on Ranking); mismatched heights 32/40/44/44; search 113px at 800px FR. Fix: logo_dark.svg, h1 on canvas, filled quiet search field flex-1, icon below lg, one control height, shadow on scroll. /impeccable layout, bolder
- [P1] Footer preferences read as disabled (grey on grey), Units track stub, 7 ungrouped links. Fix: compact language row, 2-option units toggle, grouped links (Community / About), full logo. /impeccable distill, polish
- [P2] Guest sheet: 717px, no title/handle/close/accessible name, duplicates footer links. Fix: handle, heading, close, pitch + Sign in + one Language & units row, ~50% height. /impeccable distill
- [P2] Search dialog thin: blank empty state, results in floating 5-row card, mark padding splits words, truncated placeholder. Fix: full-width 56px rows with icons, 8-10 results, hint + entry points. /impeccable onboard, polish

## Persona Red Flags
Casey: sheet without close hides tab bar; 5 results with keyboard up. Jordan: blank search; header says nothing on mobile Home; disabled-looking language. Alex: no / or Cmd-K; brand hidden under filters. Sam: sheet unnamed; focus ring 2px/0 vs 3px/3px.

## Minor Observations
Remove mobile bell; mobile header Sign in duplicates Profile sheet; long privacy label; desktop header at md too early for FR/DE (use lg).

## Questions to Consider
Does the mobile top bar need more than the logo? Should full-screen search be exploratory? One language/units chip instead of footer controls?
