# Drupal / Acquia Vetting Call — 5-Day Prep Guide

*Prepared for a mid-senior Drupal developer role on Acquia Cloud. ~8 hrs/day × 5 days.*
*Current as of July 2026: Drupal 11.4 is the stable release; Drupal 12 lands week of Dec 7, 2026; Drupal 10 hits end-of-life Dec 9, 2026. Talk in Drupal 10/11 terms.*

---

## 1. How to use this guide

You have 5 days. The goal is **not** to become a Drupal architect — it's to pass a vetting call where a technical lead confirms you can genuinely site-build, work with Acquia's toolchain, and pick up Site Studio quickly. Vetting calls reward *precise, honest, hands-on-sounding* answers over encyclopedic recall.

Three rules for the whole week:

1. **Build, don't just read.** Spin up a real Drupal site (Day 1) and touch every concept in the admin UI. "I've done it" beats "I've read about it" every time.
2. **Learn the vocabulary cold.** Half of vetting is whether you *sound* like someone who's shipped Drupal. Config sync, entity, render array, hook, Twig preprocess, Composer-managed, deploy hook — use these naturally.
3. **Bridge from frontend honestly.** Your 5 years + Drupal theming/Twig is a real asset. Don't hide the backend/Site Studio gaps — frame them as "I've done theming and Twig on Drupal; I'm ramping site-building and Site Studio and here's how I think about it."

---

## 2. Your positioning: strengths, gaps, and the honest bridge

**Genuine strengths to lean on**
- 5 years web dev, strong frontend (HTML/CSS/JS) — directly maps to the "Nice to Have" list and to Drupal theming.
- Drupal theming + Twig templating — this is real Drupal experience. Own it confidently.
- Git and collaborative workflows — you have this from any pro dev job.

**Gaps to close this week (in priority order per the JD)**
1. **Site building** — content types, fields, views, taxonomy, custom modules. *Mandatory, weighted heavily.*
2. **Acquia Cloud + DevOps** — Acquia Cloud Platform, Composer, Drush, config management, environment deploys. *Mandatory.*
3. **Acquia Site Studio** — components, layouts, templates, styles. *Explicitly "strong hands-on" required — the biggest single risk area.*
4. Integrations (external APIs, identity/profile mapping) — "experience supporting" is softer; a conceptual answer is fine.

**The bridge sentence (memorize a version of this):**
> "My core is 5 years of web development with strong frontend and hands-on Drupal theming and Twig. Over the last stretch I've been deepening backend site-building, the Acquia Cloud deployment workflow, and Site Studio. I learn fast in a real codebase and I'm comfortable working from front-end assets a lead hands me — that's exactly how I've worked."

Never claim production Site Studio depth you don't have. If pressed: *"I've built the mental model and done tutorial-level component/style work; I haven't shipped a large Site Studio site solo yet, but I understand the component → style → template flow and I ramp quickly."*

---

## 3. Priority map — where your hours should go

| Area | JD weight | Your gap | Hours this week |
|---|---|---|---|
| Site building (CT, fields, views, taxonomy) | Mandatory, high | Medium | ~10 |
| Custom module dev + Drupal APIs | Mandatory | Medium-high | ~6 |
| Acquia Cloud + Composer/Drush/config/deploy | Mandatory | High | ~8 |
| Site Studio / Cohesion | Mandatory, high risk | High | ~8 |
| Theming + Twig | Required, you're OK | Low | ~3 (refresh + connect to Site Studio) |
| Integrations / identity | Softer | Medium | ~2 |
| Interview drills (Q&A, talk track) | — | — | ~3 |

---

## 4. The practice project: **Flavorful** — a recipe platform

Everything hands-on this week builds one real site: **Flavorful**, a recipe platform. It's chosen because a single project touches *every* skill the JD tests — and it's motivating enough to actually finish in 5 days. Each day below has a **🍳 Build target** telling you exactly what to add.

**The data model (build it Day 1, extend it all week):**

- **Recipe** (content type) — title, hero image (Media), summary, prep time, cook time, difficulty (list field: Easy/Medium/Hard), servings, **ingredients** (reference to Ingredient taxonomy, or a Paragraph with quantity + ingredient), preparation steps, **Chef** (entity reference to a Chef/User), **Cuisine** (taxonomy), **Course** (taxonomy), **Dietary tags** (taxonomy).
- **Chef** (start as a Chef content type Day 1; convert the story to **User + profile fields** on Day 5 to exercise identity/profile mapping) — name, bio, photo, specialty.
- **Taxonomy vocabularies** — Cuisine (Italian, Thai…), Course (Starter/Main/Dessert), Dietary (Vegan, Gluten-free…), Ingredients.
- **Media** — recipe hero + step images.
- **Roles** — Admin, Editor, Chef (least-privilege permissions).

**What each JD skill maps to in Flavorful:**

| JD skill | Where you build it |
|---|---|
| Content types, fields, entity reference | Recipe + Chef, ingredient/chef references |
| Taxonomy | Cuisine / Course / Dietary / Ingredients vocabularies |
| Views | Recipe listing w/ exposed filters; "related recipes by shared ingredient" (contextual filter); a chef's-recipes page |
| Media | Recipe hero & step images |
| Custom module + Drupal APIs | `flavorful_nutrition` module: a block + service that fetches nutrition data |
| External API integration | Pull nutrition/food data from a free API, map onto the recipe |
| Identity / profile mapping | Chef login via OpenID Connect, map external profile → User fields (Day 5) |
| Theming + Twig | Recipe node template + preprocess; recipe-card markup |
| Site Studio | A reusable **Recipe Card** component + listing layout |
| Performance / SEO / a11y | Pathauto URLs, image styles, metatag, accessible listing (Day 5) |

Keep the scope tight — a handful of recipes and 2–3 chefs is plenty. Depth of the *model and workflow* matters, not content volume.

---

## 5. The 5-day plan (building Flavorful)

### Day 1 — Environment + Site Building foundations (8 hrs)

**🍳 Build target:** A running Drupal site with the full Flavorful content model — Recipe + Chef content types, all four taxonomies, media, roles, and 3 working Views. A few sample recipes entered.

**Goal:** Have a running Drupal site and be fluent in the core building blocks.

- **Hr 1 — Stand up Drupal locally.** Use DDEV (fastest, industry-standard):
  ```bash
  # install DDEV, then:
  mkdir mydrupal && cd mydrupal
  ddev config --project-type=drupal --docroot=web
  ddev start
  ddev composer create drupal/recommended-project
  ddev drush site:install --account-name=admin --account-pass=admin -y
  ddev launch
  ```
  If DDEV is a hurdle, use the **Acquia Cloud IDE** or a one-click sandbox (simplytest.me). The point is a working admin UI in front of you.
- **Hr 2–3 — Content architecture (build Recipe + Chef).** Create the **Recipe** content type with every field type it needs — text (summary), number (prep/cook time, servings), list (difficulty), image/media (hero), entity reference (Chef), and taxonomy-reference fields (Cuisine, Course, Dietary). Create the **Chef** content type (name, bio, photo, specialty). Along the way, internalize the difference between a **field** (reusable, added to bundles) and a **content type** (a bundle of the Node entity), and master **Manage fields / Manage form display / Manage display** — the three tabs everyone confuses.
- **Hr 4 — Taxonomy (build the four vocabularies).** Create **Cuisine, Course, Dietary, Ingredients** vocabularies with sample terms; wire the reference fields on Recipe to them. Make one hierarchical (e.g., Cuisine → regional sub-terms) to understand tags vs. hierarchy, and articulate why taxonomy beats a plain list field here (reuse, relationships, term pages, faceting).
- **Hr 5–6 — Views (build the 3 core listings).** (1) **Recipe listing page** with exposed filters for Cuisine + Dietary. (2) **"Related recipes"** block using a **contextual filter** on the current recipe's Cuisine (or shared Ingredient), excluding the current node. (3) **Chef's recipes** page — a View with a relationship/contextual filter tying recipes to a chef. Learn formats (table/grid/unformatted), fields vs. relationships, and rewriting field output. Views is *the* most-tested site-building skill — spend real time here.
- **Hr 7 — Blocks, menus, regions, users/roles/permissions.** Place the "Related recipes" block in a region and restrict it; build the site menu (Recipes, Chefs, Cuisines). Create **Editor** and **Chef** roles with least-privilege permissions and confirm why you never hand editors "administer" perms.
- **Hr 8 — Enter sample content + consolidate.** Add ~5 recipes and 2–3 chefs so your Views have something to show. Then write yourself one page: *"What is an entity in Drupal?"* — everything is an entity (node, user, taxonomy term, media, block content, paragraph); entities have **bundles**; bundles have **fields**. This model unlocks everything else.

**End-of-day check:** Explain out loud how Flavorful models "recipes with cuisines, an author, and a listing filtered by cuisine" — and how the "related recipes" block works (contextual filter on the current node's term). If that flows naturally, your site-building foundation is solid.

### Day 2 — Drupal APIs, custom modules, theming/Twig (8 hrs)

**🍳 Build target:** A `flavorful_nutrition` custom module scaffolded (route + controller + a block plugin returning placeholder nutrition data — real API wiring comes Day 5), plus a custom theme with a recipe node template and a preprocess function.

**Goal:** Be able to talk credibly about writing a custom module and preprocessing.

- **Hr 1 — Module anatomy (scaffold `flavorful_nutrition`).** Create `web/modules/custom/flavorful_nutrition/` with `flavorful_nutrition.info.yml`, a `.routing.yml`, and a controller returning a render array (a stub "nutrition summary" page). Understand the `.info.yml` fields (name, type: module, core_version_requirement, dependencies).
- **Hr 2–3 — The Drupal API concepts that come up in vetting:**
  - **Hooks** — `hook_form_alter`, `hook_preprocess_HOOK`, `hook_theme`, `hook_entity_presave`. Know that hooks are Drupal's classic extension mechanism (and that Drupal is moving toward OOP hooks/event-style patterns in 11).
  - **Render arrays** — associative arrays with `#type`/`#theme` that Drupal renders lazily. Know why they matter (cacheability, alterability).
  - **Services & dependency injection** — `*.services.yml`, `\Drupal::service()` vs. injecting in a class constructor. Know that injecting is best practice.
  - **Entity API** — loading/creating nodes programmatically: `Node::load()`, `$node->save()`, `\Drupal::entityTypeManager()->getStorage('node')`.
  - **Plugins** — blocks, field formatters, field widgets are plugins with annotations/attributes.
  - **Events** — `EventSubscriberInterface` for responding to kernel/other events.
- **Hr 4 — Cache & performance basics.** Cache tags, cache contexts, max-age; BigPipe; the render cache. Vetting loves "how do you debug a page that won't update?" → cache tags / `drush cr`.
- **Hr 5–6 — Theming refresh (your home turf, applied to Recipe).** Custom theme with `.info.yml` + libraries (`*.libraries.yml`), attaching CSS/JS. Create `node--recipe.html.twig` and style the recipe display; add a `field--field-ingredients.html.twig` override. Use `{{ content.field_prep_time }}`, `{{ node.label }}`, `|t`, `|raw` (and why raw is dangerous), and add a **preprocess function** (`hook_preprocess_node`) that computes "total time = prep + cook" and passes it to the template. Enable Twig debug (`services.yml` → `twig.config: debug: true`) and read the template-suggestion comments in page source.
- **Hr 7 — Media & Layout Builder.** Media library, media types, remote video. Layout Builder (core's layout tool) — know it exists and how it *differs* from Site Studio (see Day 4). This distinction is a likely question.
- **Hr 8 — Drill.** Explain the request-to-render lifecycle at a high level: route → controller → render array → theme layer → Twig → response, with caching throughout.

### Day 3 — Acquia Cloud, Composer, Drush, config management, deployment (8 hrs)

**🍳 Build target:** Flavorful is under Git + Composer, all your Day 1–2 config exported to `config/sync` and committed, a contrib module (e.g., Pathauto) added via Composer, and a Config Split set up so a dev-only module (Devel) is enabled just on dev. You can narrate the full Acquia deploy sequence.

**Goal:** Sound like someone who has deployed Drupal through environments.

- **Hr 1–2 — Composer.** Drupal is Composer-managed. Know:
  ```bash
  composer require drupal/pathauto        # add a contrib module
  composer require drupal/core-recommended:^11 --update-with-dependencies
  composer update drupal/token --with-dependencies
  composer remove drupal/somemodule
  ```
  Understand `composer.json` vs `composer.lock` (lock = exact versions, committed to Git), why you **never** hand-edit `vendor/` or download modules manually, and patching with `cweagans/composer-patches`.
- **Hr 3 — Drush (the CLI you'll use daily).** Memorize:
  ```bash
  drush cr                 # cache rebuild
  drush cim / drush cex    # config import / export
  drush updb               # run database updates
  drush uli                # one-time admin login link
  drush sql-dump / sql-sync
  drush en / drush pmu     # enable / uninstall module
  drush ws                 # watchdog / logs
  drush cst                # config status (diff DB vs files)
  ```
- **Hr 4–5 — Configuration management (huge in vetting).** The whole model:
  - Config lives in the database at runtime but is exported to YAML in `config/sync`.
  - Workflow: change config locally → `drush cex` → commit YAML to Git → deploy → `drush cim` on the target environment. **Never** click-configure production.
  - **Config Split** for environment-specific config (e.g., dev modules like devel enabled only on dev).
  - **Config ignore** / overrides in `settings.php` for per-environment values (API keys, etc. — use env vars/secrets, not committed config).
  - Know the failure mode: config schema drift after module updates → run `drush updb` before `drush cim`.
- **Hr 6–7 — Acquia Cloud Platform.** Learn the mental model even without an account:
  - **Environments:** Dev / Stage / Prod (plus on-demand/CD environments). Code moves via Git; databases and files are *dragged/copied down* the other direction (Prod → Stage → Dev) to refresh with real data.
  - **Deployment:** push to a branch/tag → deploy that ref to an environment. **Cloud Hooks** run scripts on deploy (e.g., `drush updb`, `drush cim`, `drush cr`) — this is the standard post-deploy sequence.
  - **Acquia Cloud IDE** — browser-based dev environment pre-configured for Drupal/Acquia.
  - **BLT is end-of-life** (Acquia archived it March 2025; it never supported Drupal 11). Acquia now points to **Drupal Recommended Settings (DRS)** for settings generation and Composer-based automation. *Mention this if BLT comes up — it signals you're current, not stuck in 2022.*
  - **ACSF (Acquia Cloud Site Factory)** — for running many sites off one codebase (multisite at scale). Know what it's *for*; deep detail is "nice to have."
  - **Pipelines / CI-CD** — automated build/test/deploy. Historically `acquia-pipelines.yml`; today many teams use GitHub Actions/GitLab CI or Acquia's newer pipeline tooling. Frame around the concept: build (composer install, front-end build), test (PHPUnit/Behat, linting), deploy (artifact to Acquia + cloud hooks).
- **Hr 8 — Deploy narrative drill.** Practice describing a full release: "Feature branch → PR + code review → merge → CI builds artifact and runs tests → deploy to Stage → run cloud hook (updb, cim, cr) → UAT → tag and deploy to Prod." This single answer covers Git, Composer, Drush, config, Acquia, and CI/CD at once.

### Day 4 — Acquia Site Studio (8 hrs) — your highest-risk area

**🍳 Build target:** A Site Studio **Recipe Card** component (image + title + total time + difficulty + dietary tags) with styles applied, placed on a Layout Canvas as a recipe listing/grid. If you have no Acquia access, produce a written build-plan + annotated screenshots from the tutorials instead — you still need the story.

**Goal:** Genuinely understand the Site Studio build model and be able to demo the vocabulary.

Site Studio (formerly **Cohesion**) is Acquia's **low-code, drag-and-drop** site-building layer *on top of* Drupal. It's not a replacement for theming — it's a visual system editors and site builders use to assemble pages without writing markup each time.

- **Hr 1 — Install & orient.** If you have an Acquia trial/sandbox, install the Site Studio module and import a **UI Kit** (a starter library of 50+ components). Otherwise, watch Acquia's official Site Studio tutorials and follow the docs (sitestudiodocs.acquia.com) while narrating each screen. Get the layout of the land: the Site Studio admin section, the **Layout Canvas**, and the element tree.
- **Hr 2–3 — The core building blocks (learn these terms cold):**
  - **Components** — reusable "mini-templates" with a **component form** (the fields an editor fills in) and a **layout** (how it renders). This is the heart of Site Studio. Newer versions support a **Field repeater** for repeating field groups.
  - **Layout Canvas** — the drag-and-drop structural view where you assemble components into a page/template.
  - **Styles** — visual styling defined in the UI: **base styles** (element defaults) and **custom styles** (reusable classes). This replaces hand-writing most CSS.
  - **Templates** — content templates (how a content type renders), master templates (page shell), view templates, menu templates.
  - **Helpers** — saved groups of components/elements you reuse to speed up building.
  - **Style guide / style helpers, Style builder** — tokens and reusable style definitions.
  - **Visual Page Builder** — the newer WYSIWYG in-context editing experience.
- **Hr 4 — Build the Recipe Card component.** Create a **Recipe Card** component with a component form (fields: image, title, total time, difficulty, dietary tags) and a layout, apply base + custom styles, and place several on a Layout Canvas as a grid. Even a tutorial-level build gives you a real story to tell — and it mirrors the Views listing you built Day 1, so you can compare "core Views vs. Site Studio" from experience.
- **Hr 5 — How Site Studio fits the JD's workflow.** The role says you'll "develop themes, Twig templates, and Site Studio components, working from front-end assets prepared by the internal front-end developer." So the flow is: FE dev hands you HTML/CSS/JS for a recipe card → you translate it into a Site Studio component + styles (and Twig where needed). Be ready to say exactly that. Your frontend background is a *strength* here — you understand the assets you're translating.
- **Hr 6 — Site Studio vs. the alternatives (very likely question):**
  - **Site Studio vs. core Layout Builder:** Layout Builder is Drupal core, developer-oriented, layouts via code/UI but styling is theme-driven. Site Studio is a proprietary Acquia low-code product with full visual styling, its own component/style system, and is more editor-friendly — but it's an Acquia dependency and adds complexity/lock-in.
  - **Site Studio vs. Paragraphs:** Paragraphs = structured content components (contrib); Site Studio components are more design/layout-oriented and visually built.
  - **Site Studio vs. classic theming:** classic theming = full control, code-first; Site Studio = speed, editor empowerment, less bespoke code.
- **Hr 7 — Governance & gotchas.** Site Studio config is large and lives in config too — know that syncing Site Studio config across environments has its own **rebuild step** (`drush cohesion:import` / `drush sitestudio:build` style commands) after `drush cim`. Mention "Site Studio needs a rebuild on deploy" — it's the kind of detail that signals real exposure.
- **Hr 8 — Site Studio Q&A drill.** Rehearse: *"Walk me through building a new page section in Site Studio from a design the FE dev gave you."* → identify reusable pieces → build/extend a component with the right form fields → apply styles → assemble on the Layout Canvas / template → test responsive → export config → deploy + rebuild.

### Day 5 — Integrations, quality bar, and interview drills (8 hrs)

**🍳 Build target:** Wire `flavorful_nutrition` to a real external API (fetch nutrition data via Drupal's `http_client`, map it onto the recipe display), convert Chefs to Users with profile fields for the identity/profile-mapping story, then a performance/SEO/a11y pass on the recipe listing.

- **Hr 1 — Integrations & identity (build the API + profile story).** In `flavorful_nutrition`, inject Drupal's `http_client` (Guzzle) service to fetch nutrition data for a recipe's ingredients from a free food/nutrition API, and map the response onto the recipe display via your Day 2 block. Understand the queue API for background sync and JSON:API / REST for exposing Drupal data. For identity: switch Chefs to **User accounts** with profile fields, and be able to explain SSO/SAML/OpenID Connect (`openid_connect` contrib) mapping external profile data onto those user fields. You don't need production depth — a working API call plus a clear conceptual identity answer is enough.
- **Hr 2 — Non-functional requirements the JD calls out:**
  - **Performance:** caching (tags/contexts/BigPipe), CDN, aggregation of CSS/JS, image styles/responsive images, lazy loading.
  - **Security:** Drupal's sanitization (Twig autoescape, `Xss::filter`), never trust user input, keep core/contrib patched, permissions least-privilege, `drush` security updates. Know that Drupal has a dedicated Security Team and SA advisories.
  - **SEO:** Pathauto (clean URLs), Metatag, XML sitemap, semantic markup, redirects.
  - **Accessibility (WCAG):** semantic HTML, ARIA where needed, color contrast, keyboard nav, alt text — Drupal core aims for WCAG 2.x AA. Your frontend background lets you speak to this well.
- **Hr 3 — Git & collaboration.** Feature-branch workflow, PRs, code review etiquette, meaningful commits, not committing `vendor/`/`node_modules`, resolving conflicts in `composer.lock` and config YAML (regenerate rather than hand-merge where possible).
- **Hr 4–6 — Mock interview.** Answer the Section 6 questions out loud, ideally recorded or with a peer. Time yourself: aim for 60–120 second answers. Redo any answer that rambles or that you fumbled.
- **Hr 7 — Prepare your own questions + your project story.** Vetting calls end with "any questions?" Have 3 ready (team size, Site Studio maturity of their codebase, their deploy/CI setup). Also prepare a crisp 2-minute walkthrough of a past project emphasizing Drupal theming/Twig and frontend delivery.
- **Hr 8 — Light review & rest.** Re-skim Sections 6–8 below. Don't cram new topics the night before — consolidate.

---

## 6. Likely vetting questions + how to answer

**Site building**
- *Difference between a content type and an entity?* → Node is an entity type; a content type is a **bundle** of the Node entity. Fields attach to bundles. Other entity types: user, taxonomy_term, media, block_content, paragraph.
- *When do you use taxonomy vs a list field?* → Taxonomy when terms are reusable, relational, hierarchical, or need their own pages/faceting; a list field for a small fixed set with no relationships.
- *Build a "related articles" block.* → A View, content type = article, contextual filter on the taxonomy term from the current node, exclude current node, limit N.
- *Views: fields vs relationships?* → Relationships pull in related entities (e.g., author user) so you can then add *their* fields; fields are the columns/output you display.

**APIs / modules**
- *How would you alter another module's form?* → `hook_form_alter` / `hook_form_FORM_ID_alter` in a custom module.
- *Load and update a node programmatically?* → `entityTypeManager()->getStorage('node')->load($id)`, set field, `->save()`. Prefer injected services over `\Drupal::`.
- *A change isn't showing on the page — debug it?* → Cache. `drush cr`; check cache tags/contexts; check Twig template suggestion is the one you edited.
- *Hook vs service vs plugin?* → Hooks = procedural extension points; services = reusable objects via DI container; plugins = swappable, discoverable implementations (blocks, formatters).

**Acquia / DevOps**
- *Describe your deployment workflow.* → (Use the Day 3 Hr 8 narrative.)
- *How do you move config between environments?* → `drush cex` locally → commit YAML → deploy → `drush updb` then `drush cim` on target (often via a Cloud Hook). Config Split for env-specific.
- *How does code vs database/files move on Acquia?* → Code flows *up* via Git/deploys (Dev→Stage→Prod); DB and files are copied *down* (Prod→Stage→Dev) to refresh lower envs with real data.
- *What are Cloud Hooks?* → Scripts Acquia runs on deploy/DB-copy events; standard use is post-deploy `drush updb && drush cim && drush cr` (+ Site Studio rebuild).
- *Do you use BLT?* → "BLT reached end of life in 2024 and was archived in early 2025 — it never supported Drupal 11. Acquia now recommends Drupal Recommended Settings plus Composer-based automation, and CI via pipelines/Actions." (Shows you're current.)

**Site Studio**
- *What is Site Studio and how does it differ from theming/Layout Builder?* → (Use Day 4 Hr 6.)
- *Walk me through building a section from a design.* → (Use Day 4 Hr 8.)
- *What's a component vs a style vs a template in Site Studio?* → Component = reusable mini-template with an editor form + layout; styles = UI-defined base/custom visual styling; templates = how content/pages render (content, master, view templates).
- *Anything special about deploying Site Studio?* → Yes — after `drush cim` you run a Site Studio rebuild so the generated styles/templates regenerate on the target environment.

**Frontend / quality (your strengths)**
- *How do you ensure accessibility?* → semantic HTML, keyboard nav, contrast, ARIA sparingly, alt text; test with axe/Lighthouse; target WCAG 2.x AA.
- *Front-end performance in Drupal?* → CSS/JS aggregation, image styles + responsive images, lazy loading, caching/BigPipe, CDN, minimize render-blocking assets.

---

## 7. Honesty & talk-track playbook

- **When you know it:** answer crisply, then add one detail that shows depth ("…and the gotcha there is X").
- **When you half-know it:** say what you *do* know, then reason out the rest. "I haven't done X in production, but the way I'd approach it is…" — vetting leads value reasoning over bluffing.
- **When you don't know it:** "I haven't worked with that directly. My closest experience is Y, and I'd get up to speed by Z." Never fake it — Drupal leads catch fabrication instantly and it's the fastest way to fail.
- **On Site Studio specifically:** be upfront that your deep production time is limited, immediately followed by your build model understanding and your frontend background as the reason you'll ramp fast. The role explicitly pairs you with an internal FE dev and technical lead — you're not expected to be the lone architect.
- **Lead with the frontend strength.** It's your differentiator and it's on their "Nice to Have" list. Responsive UI, cross-browser, front-end performance, translating designs into components — that's you.

---

## 8. Quick-reference cheat sheet

**Drush (daily)**
```
drush cr        rebuild cache            drush cim / cex   config import / export
drush updb      run DB updates           drush cst         config diff (DB vs files)
drush uli       admin login link         drush en / pmu    enable / uninstall module
drush sql-dump  export DB                drush ws          view logs (watchdog)
```

**Composer**
```
composer require drupal/MODULE          add a module
composer update drupal/MODULE -W        update with dependencies
composer.json  = intended versions      composer.lock = exact, committed to Git
```

**Deploy sequence (Cloud Hook / release)**
```
git deploy → drush updb → drush cim → (Site Studio rebuild) → drush cr → smoke test
```

**Entity mental model**
```
Entity type (Node, User, Term, Media, Paragraph…) → Bundle (e.g. "Article") → Fields
```

**Config workflow**
```
change locally → drush cex → commit YAML (config/sync) → deploy → drush cim on target
Config Split = per-environment config   |   secrets → settings.php / env vars, never committed
```

**Site Studio vocabulary**
```
Component (form + layout) · Layout Canvas · Styles (base/custom) · Templates
(content/master/view) · Helpers · UI Kit · Visual Page Builder · rebuild on deploy
```

**Key facts to sound current (July 2026)**
- Drupal 11.4 stable; Drupal 12 due Dec 2026; Drupal 10 EOL Dec 9, 2026.
- BLT is end-of-life (archived Mar 2025) → Drupal Recommended Settings + Composer/CI.
- Site Studio = Acquia low-code layer (formerly Cohesion), Visual Page Builder is the newer WYSIWYG.
- Drupal Recipes (stable from 10.3+) = shareable, composable site configuration.

---

## 9. If you only have time for the essentials

Cut to these four things and you'll survive the call:
1. **Build a real site (Day 1)** and be fluent in content types, fields, Views, taxonomy.
2. **Nail the deploy narrative (Day 3 Hr 8)** — it demonstrates Composer + Drush + config + Acquia + CI in one answer.
3. **Learn Site Studio vocabulary + the build flow (Day 4)** even if you can't build deeply — components, styles, templates, Layout Canvas, rebuild-on-deploy.
4. **Rehearse the honesty talk-track (Section 7)** and lead with your frontend/theming strength.

Good luck — you've got a real foundation to build on.

---

### Sources
- [Drupal core release schedule](https://www.drupal.org/about/core/policies/core-release-cycles/schedule) · [Drupal 2026 release/support timeline](https://www.thedroptimes.com/70355/drupal-2026-release-support-timeline)
- [Acquia Site Studio docs](https://docs.acquia.com/site-studio) · [Site Studio features](https://www.acquia.com/products/drupal-cloud/site-studio/features) · [Site Studio components (docs)](https://sitestudiodocs.acquia.com/6.0/user-guide/components)
- [Announcing BLT's End of Life (acquia/blt #4736)](https://github.com/acquia/blt/issues/4736) · [Using Drupal Recommended Settings](https://docs.acquia.com/drupal-starter-kits/using-drupal-recommended-settings-plugin) · [You don't need BLT on Acquia Cloud](https://dev.acquia.com/tutorial/you-dont-need-blt-acquia-cloud)
- [Config management workflow with Drush (Drupal.org)](https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/workflow-using-drush) · [Recipes Cookbook (Drupal.org)](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/distributions-and-recipes-initiative/recipes-cookbook)
