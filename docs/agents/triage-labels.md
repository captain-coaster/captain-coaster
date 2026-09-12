# Triage Labels

The skills speak in terms of five canonical triage **state** roles and two canonical **category** roles. This file maps those roles to the actual label strings and fields used in this repo's issue tracker.

## State roles

All five states now live under the `status/` prefix, alongside the pre-existing workflow labels — one unified axis instead of two overlapping ones.

| Label in mattpocock/skills | Label in our tracker      | Meaning                                   |
| --------------------------- | -------------------------- | ------------------------------------------ |
| `needs-triage`              | `status/needs-triage`      | Maintainer needs to evaluate this issue   |
| `needs-info`                | `status/needs-info`        | Waiting on reporter for more information  |
| `ready-for-agent`           | `status/ready-for-agent`   | Fully specified, ready for an AFK agent   |
| `ready-for-human`           | `status/ready-for-human`   | Requires human implementation             |
| `wontfix`                   | `status/wontfix`           | Will not be actioned                      |

`status/to do` was retired (2026-09-10): once triage exists, "not started" is already implied by `status/needs-triage` / `status/ready-for-agent` / `status/ready-for-human` before work begins. `status/in progress`, `status/to review`, and `status/done` stay — they track real work state (someone is actively coding, PR is up, merged/shipped) once an issue has moved past triage, which the triage-state labels don't capture.

`status/outdated` was folded into `status/wontfix` (2026-09-10): both meant "not being actioned," just with a different flavor of reason — that reason belongs in the closing comment, not a separate label. `status/duplicate` stays, since it's a genuinely different claim (this exact ask already has a home elsewhere, not a decision to skip it).

**`ready-for-human` means implementation, not review.** It says a human (not an AFK agent) needs to write the code — because of a judgment call, external access, a design decision, or manual testing. The moment an issue has an open PR against it, implementation has started: swap `status/ready-for-human` for `status/to review` (or `status/in progress` if the PR isn't up yet but someone's actively coding) instead of leaving the triage-state label in place. Check for an open PR before applying `ready-for-human` to an issue that looks active.

## Category roles

Category uses GitHub's native **Issue Types** (the repo's `Bug`/`Feature`/`Task` types, set via `gh issue edit <n> --type <name>` — not a label). `Task` exists as a third native type but isn't part of the skill's two-role model; don't use it during triage.

| Role in mattpocock/skills | Issue Type in our tracker | Meaning                    |
| --------------------------- | -------------------------- | -------------------------- |
| `bug`                        | `Bug`                       | Something is broken        |
| `enhancement`                 | `Feature`                   | New feature or improvement |

(An earlier pass created `type/bug`/`type/enhancement` labels for this — removed the same day in favor of the native field, which already existed at the org level.)

## Tech-area labels (not part of the skill's roles, but touched by the same cleanup)

`tech/php`, `tech/js`, `tech/css`, `tech/twig` were collapsed into `tech/frontend` and `tech/backend` (2026-09-10). Twig templates count as `tech/frontend` (view/markup layer), even though they're PHP-rendered — group with JS/CSS, not with PHP business logic. `tech/other` is untouched. An issue can carry both when it genuinely spans the stack.

## Label hygiene (2026-09-10 pass)

- **One separator, `/`, everywhere.** `wayfinder:map`/`wayfinder:research`/`wayfinder:task` used `:`; renamed to `wayfinder/map` etc. to match `status/*`, `tech/*`. If a new label group gets added, use `/`.
- **`type/help wanted` and `type/question` removed** — unused (`help wanted`) or redundant now that category lives on the native Issue Type field (`question` doesn't map to `Bug`/`Feature` anyway; use a comment instead of a label for that case).
- **Dependabot's auto-applied labels removed**: `dependencies`, `docker`, `github_actions`, `php` (bare, not `tech/php`) only ever appeared on Dependabot PRs. Safe to delete — `.github/dependabot.yml` sets `open-pull-requests-limit: 0` on every ecosystem, so Dependabot no longer opens PRs and won't recreate them.

The full current label set: `status/{needs-triage,needs-info,ready-for-agent,ready-for-human,wontfix,duplicate,in progress,to review,done}`, `tech/{frontend,backend,other}`, `wayfinder/{map,research,task}`.

When a skill mentions a role (e.g. "apply the AFK-ready triage label"), use the corresponding label string (or, for category, the issue type) from the tables above.
