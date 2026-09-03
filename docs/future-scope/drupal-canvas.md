# Future scope — Drupal Canvas + Acquia Nebula

| | |
|---|---|
| **Status** | 🟢 Ready to build — both are open source and run locally. **No Acquia licence needed.** |
| **Depends on** | Nothing. This is the one item in this folder that is unblocked today. |
| **Rough size** | ~1 day |
| **Blocks** | [`canvas-ai.md`](canvas-ai.md) — the AI Assistant needs a Canvas project first |
| **Related** | [`../objectives/day9-atomic-sdc.md`](../objectives/day9-atomic-sdc.md) (SDC), [`../objectives/day4-site-studio.md`](../objectives/day4-site-studio.md) (Site Studio) |

## Goal

Scaffold a Canvas project with the Nebula template, learn its component conventions, and rebuild the **nutrition card** as a Canvas **Code Component** — so the "Site Studio → Canvas" migration path can be spoken to from real experience rather than docs.

## Clear up the names first

- **Drupal Canvas** — the page builder (`canvas` project on drupal.org), formerly *Experience Builder (XB)*. Assembles pages from **SDCs** and **Code Components**.
- **Acquia Nebula** — an **open-source starter/reference repo** (`acquia/nebula`) used by `@drupal-canvas/create` to scaffold a Canvas Code Components project. Pre-wired dev tooling plus agent skills in `.agents/skills/` (`nebula-*` = repo conventions, `canvas-*` = generic Canvas guidance). It is **not** a design system and **not** a hosting product.
- **Relationship to Site Studio** — Canvas is Acquia's stated future for low-code building; Site Studio (this repo's `acquia/cohesion`) is the current generation. Knowing both, plus the migration path, is the valuable part.

```mermaid
flowchart LR
  N[acquia/nebula template] -->|@drupal-canvas/create| P[Canvas project scaffold]
  P --> CC[Code Components]
  SDC[Existing SDCs] --> CC
  CC --> XB[Drupal Canvas page builder]
```

## Prerequisites

- [ ] Node/npm available (Canvas tooling is npm-driven)
- [ ] A **sandbox directory** — scaffold there, not on top of this repo

## Tasks

**1. Scaffold with Nebula**
- [ ] `npm create @drupal-canvas@latest` and select the `acquia/nebula` template (verify the exact command against current Canvas docs)
- [ ] Read one `nebula-*` and one `canvas-*` skill in `.agents/skills/` — these encode the conventions and the AI-assisted workflow
- [ ] Study the example components: file layout, props schema, how a component declares itself to Canvas
- [ ] Run the dev tooling, render an example Code Component, edit a prop and see it change

**2. Rebuild the nutrition card as a Code Component**
- [ ] Create a Code Component with a typed props schema: `calories`, `protein`, `carbs`, `fat`, `servings`
- [ ] Follow the Nebula skill conventions for structure and styling
- [ ] Write down what differs from the Site Studio version:
      code-first and versionable (lives in the repo, PR-reviewable) vs config-in-DB visual builder;
      props schema vs Site Studio form fields + `[field.x]` tokens;
      renders through Drupal Canvas, not the Cohesion content template

**3. Write the migration story**
- [ ] Use the nutrition card as the worked example for "how would we migrate off Site Studio?"

## Acceptance criteria

- A Canvas project exists in a sandbox dir with the Nebula conventions understood, not just scaffolded.
- The nutrition card renders as a Code Component with a typed props contract.
- The Site Studio → Canvas differences can be stated concretely, pointing at two real implementations of the same card.

## Repo-specific note

The recipe full display is currently Site Studio–owned. That makes the nutrition card an unusually good migration example — it is exactly the component a real migration would have to move.

## Site Studio vs Canvas

| | Site Studio (Cohesion) | Drupal Canvas |
|---|---|---|
| Era | current gen (this repo) | go-forward |
| Component | visual builder, config-in-DB | Code Components + SDCs, code-first |
| Editor input | form fields + `[field.x]` tokens | typed props |
| Review | config YAML diff | real code PR |
| AI | — | Canvas AI Assistant ([`canvas-ai.md`](canvas-ai.md)) |

## Sources

- [acquia/nebula (GitHub)](https://github.com/acquia/nebula)
- [Nebula as recommended template for @drupal-canvas/create](https://www.drupal.org/project/canvas/issues/3571591)
- [Drupal Canvas FAQ (Acquia)](https://www.acquia.com/blog/drupal-canvas-faq)
- [Single-directory components](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components)
