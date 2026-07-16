---
name: outcome-notes
description: Project-scoped wrapper around /dev-notes for foodrecipes-drupal. Use when documenting staged/working changes in THIS repo. Writes outcome notes under docs/actual-outcomes/ that record what actually shipped versus the plan in docs/objectives/, logging any deviations made due to errors or unknowns — without repeating definitions the objectives already cover.
---

# outcome-notes (project extension of /dev-notes)

This repo keeps a deliberate split:

- [`docs/objectives/`](../../../docs/objectives/) — the **plan**: the 5/9-day labs and what we intended to build.
- [`docs/actual-outcomes/`](../../../docs/actual-outcomes/) — **what reality returned**: deployment runbook, lessons-learned, and per-build outcome docs.

Run `/dev-notes` exactly as written, but apply the project rules below. They override the base skill wherever they conflict.

## Project rules (the disclaimer)

1. **Document outcomes, not the plan.** The notes go in `docs/actual-outcomes/`. Their job is to record what actually shipped and — importantly — **any change that opposed the suggested objective because of an error or an unknown** (missing keys, API shape, environment limits, etc.). A deviation log is mandatory when the build departed from the objective.

2. **Never repeat what `docs/objectives/` already covers.** Do not re-teach concepts, re-paste full listings, or redefine terms already defined in the matching objective file. Summarise the outcome and **link back** to the objective (e.g. `[Day 5 §1.3](../objectives/day5-integrations-identity-interview.md)`) instead.

3. **Treat the two folders as allies that complement each other.** Every outcome doc should establish the relation explicitly: which objective(s) it fulfils/diverges from, cross-linked both ways. When you add an outcome doc, also add a one-line pointer to it under the "Actual outcomes" section of [`docs/objectives/README.md`](../../../docs/objectives/README.md).

4. **Default section set for this repo** (still confirm via the base skill's step-3 menu, but lead with these): an **objective→outcome map** table (objective link · what shipped · status done/partial/deviated), a **deviation log** table (divergence · why · what we did), and only then the base skill's flowchart / deltas-only walkthrough. Prefer a **deltas-only** walkthrough — annotate just what changed against the objective's baseline, never the whole file.

5. **Match the house style of `docs/actual-outcomes/`** — sentence-case headings, `>` intro blockquote, `---` dividers, problem→cause→fix tables, no emojis. Keep each outcome doc scoped to one build slice (e.g. `day4-5-site-studio-nutrition.md`); don't mix unrelated days into one file.

## Everything else

Follow `/dev-notes` for repo inspection, the formatting menu, Mermaid validation, and the optional staging step. Same guardrails: notes only (never touch source), append don't overwrite, no secrets.
