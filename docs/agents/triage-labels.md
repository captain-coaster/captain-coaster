# Triage labels

The skills speak in five canonical triage **state** roles and two **category** roles. This file maps them to this repo's tracker. When a skill names a role ("apply the AFK-ready triage label"), use the label or issue type below.

## State roles

| Role in mattpocock/skills | Label in our tracker     | Meaning                                  |
| ------------------------- | ------------------------ | ---------------------------------------- |
| `needs-triage`            | `status/needs-triage`    | Maintainer needs to evaluate this issue  |
| `needs-info`              | `status/needs-info`      | Waiting on reporter for more information |
| `ready-for-agent`         | `status/ready-for-agent` | Fully specified, ready for an AFK agent  |
| `ready-for-human`         | `status/ready-for-human` | Requires human implementation            |
| `wontfix`                 | `status/wontfix`         | Will not be actioned                     |

**`ready-for-human` means implementation, not review**: a human has to write the code, because of a judgment call, external access, a design decision or manual testing. Check for an open PR before applying it to an issue that looks active.

Once work starts, the triage label gives way to a work-state label:

- `status/in progress`: someone is coding, no PR yet.
- `status/to review`: a PR is open.
- `status/done`: merged or shipped.

`status/duplicate` marks an ask that already has a home elsewhere. An outdated issue is a `status/wontfix` with the reason in the closing comment.

## Category roles

Category is GitHub's native **Issue Type**, not a label: `gh issue edit <n> --type <name>`.

| Role in mattpocock/skills | Issue Type in our tracker | Meaning                    |
| ------------------------- | ------------------------- | -------------------------- |
| `bug`                     | `Bug`                     | Something is broken        |
| `enhancement`             | `Feature`                 | New feature or improvement |

The `Task` type exists but is not used during triage. A question is a comment, not a label.

## Tech-area labels

`tech/frontend` (Twig templates included), `tech/backend`, `tech/other`. An issue that spans the stack carries both.

## The full label set

`status/{needs-triage,needs-info,ready-for-agent,ready-for-human,wontfix,duplicate,in progress,to review,done}`, `tech/{frontend,backend,other}`, `wayfinder/{map,research,prototype,task}`.

A new label group uses `/` as its separator.
