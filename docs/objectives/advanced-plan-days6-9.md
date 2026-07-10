# Advanced Curriculum — Days 6–9: Deepening Drupal Fluency

> Extension to the [5-day plan](5-day-plan.md). Days 1–5 got you to "I can build and ship a Drupal site." Days 6–9 get you to **"I clearly know Drupal from the inside"** — the depth that makes an interviewer stop probing and start nodding. Same practice project (**Flavorful**), each day building on the last.

## Why these four topics, in this order

The interviewer's mental question is *"does this person understand how Drupal actually works, or just where the buttons are?"* These four areas are where that shows:

1. **Day 6 — Hooks & Preprocess:** how Drupal lets you *change its behaviour* without hacking core. The single biggest "do they really know Drupal" signal.
2. **Day 7 — Advanced Views:** Views is the tool you'll use daily; going past the basics (relationships, aggregation, attachments, rewriting) shows real site-building depth.
3. **Day 8 — Twig best practices:** clean, DRY templates using inheritance, includes, embeds and macros — directly relevant to the JD's "work from front-end assets."
4. **Day 9 — Atomic design + Single Directory Components:** the modern, component-first way to build a UI in Drupal — ties your frontend strength to current Drupal architecture.

The progression is deliberate: **extend behaviour (hooks) → shape data (Views) → render it well (Twig) → package it as components (SDC).**

> **Modern-Drupal framing (say this and you sound current — verified for Drupal 11.4, July 2026):**
> - **Hooks are going object-oriented.** The `#[Hook]` PHP attribute landed in **Drupal 11.1**; **11.2** added OOP **preprocess** hooks + an execution-order parameter; **11.3** extended it to themes. Procedural hooks (`.module` functions) still work but are planned for **removal in Drupal 12**. Know both, prefer the attribute for new code.
> - **Single Directory Components (SDC)** have been **stable in core since Drupal 10.3** — the standard way to build reusable UI components (co-located Twig + CSS + JS + schema).

---

## Day 6 — The Hook System & Preprocess Deep Dive

**Objective:** Explain and use Drupal's hook system fluently — both the classic procedural style and the new `#[Hook]` attribute — with a real focus on preprocess hooks and template suggestions.

**Why it matters for the call:** "Tell me how you'd change the output of X without editing core/contrib" is the archetypal Drupal question. Hooks are the answer, and preprocess hooks are where theming and logic meet — you'll be asked.

**Topics**

- **What a hook is** — Drupal's publish/subscribe extension points; how a module "implements" a hook the system invokes.
- **The big families:** alter hooks (`hook_form_alter`, `hook_form_FORM_ID_alter`), entity lifecycle (`hook_entity_presave`, `hook_ENTITY_TYPE_insert/update/delete`, `hook_entity_view`), theme registration (`hook_theme`), template selection (`hook_theme_suggestions_HOOK_alter`), and **preprocess** (`hook_preprocess_HOOK`).
- **Procedural vs OOP hooks** — the classic `mymodule_preprocess_node()` in `.module`, and the modern `#[Hook('preprocess_node')]` method on a class (autoloaded, DI-friendly, unit-testable). Know why the OOP form is preferred going forward.
- **Preprocess deep dive** — the preprocess chain for `node`, `field`, `page`, `html`, `block`; manipulating `$variables`; adding cache metadata; adding classes/attributes via the `Attribute` object; computing derived variables for Twig.
- **Template suggestions** — how filenames map to suggestions and how to *add your own* (e.g. a per-cuisine node template) via `hook_theme_suggestions_node_alter()`.
- **`hook_update_N()`** — how structural/data changes ship as update hooks and run with `drush updb` (ties back to Day 3 deploys).

**Hands-on in Flavorful**

1. `hook_form_alter` — add a helper description to the recipe form, or reorder fields.
2. `hook_entity_presave` (recipe) — auto-populate a `total_time` value from prep + cook so it's stored, not just computed at render.
3. `hook_preprocess_node` — add a `is_quick` boolean + a `recipe--quick` class when total time < 30 min; expose it to Twig.
4. `hook_theme_suggestions_node_alter` — add a `node--recipe--{cuisine}` suggestion so Italian recipes can have their own template.
5. **Do it twice:** write one of these procedurally in `.module`, then rewrite it as a `#[Hook]` class method, and be able to compare them.

**By the end you can answer:** "How do you alter a form / change render output / target a template to specific content?" and "What changed about hooks in Drupal 11?"

---

## Day 7 — Advanced Views (more practice)

**Objective:** Go beyond the Day-1 basics into the Views features that come up on real projects.

**Why it matters:** Views is the workhorse. Depth here — relationships, aggregation, attachments, contextual-filter validation, rewriting — is concrete proof of site-building experience.

**Topics**

- **Relationships, revisited** — reaching author/media/referenced entities; required vs optional; chaining relationships.
- **Aggregation** — `COUNT`, `SUM`, `GROUP BY` (e.g. recipes per cuisine); when to aggregate vs use a separate view.
- **Contextual filters, advanced** — default-value providers, **validation criteria**, "exclude", and access — deepening the Day-1 related-content pattern.
- **Attachments & feeds** — the **Attachment** display (attach a "more like this" view under a page), and the **Feed/REST export** display for a data endpoint.
- **EVA (Entity Views Attachment)** — rendering a view *inside* an entity's display (e.g. a chef's recipes on the chef page) — contrast with the Day-1 path-argument approach.
- **Rewriting & global fields** — `Global: Custom text`, combining fields, `No results behavior`, replacement tokens and dependency order (recap the token-order gotcha).
- **Exposed filter UX** — exposed forms as a block, grouped filters, "Any" defaults, sorts exposed to users.
- **Views in config/code** — the exported `views.view.*.yml` (ties to Day 3 config), and where you'd reach for a custom Views field/filter/area **plugin** (awareness, not mastery).

**Hands-on in Flavorful**

1. Add an **aggregation** view: recipe count per cuisine.
2. Convert the "chef's recipes" page to an **EVA** shown directly on the chef's profile.
3. Add an **Attachment** display: "More {cuisine} recipes" beneath the recipe listing.
4. Build a **grouped exposed filter** and expose a sort ("Quickest first" using total time).
5. Add a **Feed / REST export** display exposing recipes as JSON (bridges to the Day-5 API mindset).

**By the end you can answer:** "How would you show related content inside an entity?" (EVA), "Have you used aggregation / attachments / feeds in Views?", "How do Views travel between environments?" (config).

---

## Day 8 — Twig Templating Best Practices (inheritance, include, extends, embed, macros)

**Objective:** Write clean, DRY, reusable Twig using the full toolkit — the exact skill the JD's "work from front-end assets" implies.

**Why it matters:** Anyone can edit a `.twig` file. Knowing **when** to use `include` vs `embed` vs `extends`, and writing a **macro** for a repeated element, is what separates a themer from someone who just pastes markup.

**Topics**

- **Inheritance — `extends` + `block`** — a base layout template with named `{% block %}` regions that child templates override. The backbone of DRY theming.
- **`include`** — pull in a partial (`{% include '@flavorful_theme/partials/recipe-card.html.twig' with { recipe: node } only %}`); the meaning of `with` and `only` (pass data explicitly, isolate scope).
- **`embed`** — `include` + the ability to **override blocks** inside the included template; when it beats plain include.
- **`macro`** — reusable, parameterised snippets (a button, a rating stars widget); `import`/`from`; why macros are great for atoms.
- **Drupal-specific Twig** — the `attributes` object (`addClass`, `setAttribute`, `without()`), `{{ content|without('field_x') }}`, `create_attribute()`, `{{ 'text'|t }}`, `clean_class`/`clean_id`, `{{ link() }}`, `{{ attach_library('theme/lib') }}`, and why `|raw` is a last resort (autoescaping).
- **Whitespace control & readability** — `{%- -%}`, keeping templates scannable; comments `{# #}`.
- **Template suggestions recap** — pairing Day-6 suggestions with these constructs.

**Hands-on in Flavorful**

1. Create a base `partials/base-card.html.twig` with `{% block media %}`, `{% block title %}`, `{% block meta %}` blocks.
2. Build `recipe-card.html.twig` that **`embed`s** the base card and overrides its blocks with recipe fields.
3. Make a **macro** file `macros/ui.html.twig` with `button(text, url, variant)` and `tag_pill(label)`; use them in the card.
4. Refactor the Day-2 `node--recipe.html.twig` to **`include`** the recipe card and use the macros — removing duplicated markup.
5. Add `attach_library` for the card's CSS and use `attributes.addClass()` cleanly.

**By the end you can answer:** "What's the difference between include, extends and embed?", "When would you use a Twig macro?", "How do you add a class/library to a template the Drupal way?"

---

## Day 9 — Atomic Design with Single Directory Components (buttons, cards)

**Objective:** Build a small, reusable component library the modern Drupal way — **Single Directory Components (SDC)** — organised by atomic-design levels.

**Why it matters:** This is where your frontend background becomes a Drupal superpower. SDC is the current, core-supported answer to component-based theming, and it maps cleanly onto the "front-end developer hands you assets, you build components" workflow in the JD. It also gives you an intelligent way to compare **SDC vs Site Studio** (Day 4).

**Topics**

- **Atomic design** — atoms (button, tag) → molecules (card) → organisms (recipe grid) → templates → pages; why a component library beats one-off templates.
- **SDC anatomy** — a folder under `components/` with `{name}.component.yml` (metadata: `props`, `slots`, using JSON Schema) + `{name}.twig` + optional `{name}.css` / `{name}.js`, kebab-case base name.
- **Props vs slots** — **props** are strictly-typed data (a button's `label`, `variant`); **slots** are free-form render areas (a card's body can hold anything). Knowing the difference is a common SDC interview point.
- **Rendering a component** — `{{ include('flavorful_theme:recipe-card', { title: node.label }) }}` (or the `{% embed %}`/component render element); passing props and filling slots.
- **How SDC relates to everything else** — vs classic Twig partials (Day 8), vs Site Studio components (Day 4), vs Paragraphs; when each fits.
- **Front-end assets** — co-locating CSS/JS with the component; libraries auto-attached; replaceable via theme overrides.

**Hands-on in Flavorful**

1. **Atom:** `button` SDC — props `label`, `variant` (primary/ghost), optional `url`; a little co-located CSS.
2. **Atom:** `tag-pill` SDC for dietary/cuisine tags.
3. **Molecule:** `recipe-card` SDC — props (`title`, `total_time`, `difficulty`, `image_url`), a **slot** for tags that composes the `tag-pill` atoms.
4. Render the `recipe-card` from the Day-1 Views listing (Views "Fields" → a rewritten/twig field, or a template) and from `node--recipe`.
5. Write the compare-and-contrast: **SDC vs Site Studio component** (code-first, versioned, free vs low-code, visual, Acquia-licensed).

**By the end you can answer:** "How do you build reusable UI components in Drupal today?", "Props vs slots?", "SDC vs Site Studio vs Paragraphs — when would you use each?"

---

## How to run these four days

- **Same rhythm as Days 1–5:** each day is ~8 hrs; build in Flavorful, then rehearse the "by the end you can answer" prompts out loud.
- **Build > read.** Every topic here has a hands-on task — do it in the running project so you can say *"I built this,"* not *"I read about this."*
- **Keep the honesty rule.** These deepen your genuine range; you don't need to claim mastery, just working, explained-out-loud competence.

## Interview payoff — the deeper questions you'll now handle

| Question you might get | Where you learned to answer it |
|---|---|
| "Alter a form / change render output without hacking core?" | Day 6 (hooks) |
| "What changed about hooks in Drupal 11?" | Day 6 (`#[Hook]` attribute) |
| "Target a template to specific content?" | Day 6 (theme suggestions) |
| "Show related content inside an entity?" | Day 7 (EVA / attachments) |
| "Aggregate or expose data from Views?" | Day 7 (aggregation / feeds) |
| "include vs extends vs embed? When a macro?" | Day 8 (Twig) |
| "Build reusable UI components in modern Drupal?" | Day 9 (SDC) |
| "SDC vs Site Studio vs Paragraphs?" | Day 9 + Day 4 |

---

## The hands-on labs

Each day now has a full step-by-step lab (how / what / why + code), like Days 1–5:

- [`day6-hooks-preprocess.md`](day6-hooks-preprocess.md)
- [`day7-advanced-views.md`](day7-advanced-views.md)
- [`day8-twig-best-practices.md`](day8-twig-best-practices.md)
- [`day9-atomic-sdc.md`](day9-atomic-sdc.md)

---

### Sources

- [Object-oriented hooks / `#[Hook]` attribute (Drupal.org change record)](https://www.drupal.org/node/3442349) · [Drupal 11.1 adds hooks as classes (Drupalize.Me)](https://drupalize.me/blog/drupal-111-adds-hooks-classes-history-how-and-tutorials-weve-updated)
- [Using Single-Directory Components (Drupal.org)](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components) · [Props and slots in SDC (Drupal.org)](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/what-are-props-and-slots-in-drupal-sdc-theming)
