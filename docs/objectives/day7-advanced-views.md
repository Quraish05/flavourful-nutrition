# Flavorful — Day 7 Lab: Advanced Views (click-by-click)

> Companion to the [advanced curriculum](advanced-plan-days6-9.md). Day 1 got you building basic Views; today goes into the features real projects lean on — aggregation, rendering a view *inside* an entity, attachments, exposed-filter UX, and data feeds. Views is the tool you'll touch daily, so depth here is direct proof of site-building experience.

**Build target:** five upgrades to Flavorful's Views — a recipes-per-cuisine **aggregation**, the chef's recipes shown **inside the chef page (EVA)**, a **"More {cuisine} recipes" attachment** under the listing, a **grouped exposed filter + a "Quickest first" sort**, and a **JSON feed** of recipes.

> Prerequisite from Day 6: recipes now have a stored `field_total_time` — that's what makes the "Quickest first" sort possible.

---

## 1. Aggregation — count recipes per cuisine

**Why:** aggregation (`COUNT`, `SUM`, `GROUP BY`) turns a list of rows into a summary. "How many recipes per cuisine?" is a classic reporting need, and knowing Views can do it (without custom code) is a real signal.

1. **Structure → Views → Add view.** Name `Recipes per cuisine`. Show **Content** of type **Recipe**. Create a **page**, path `reports/by-cuisine`, format **Table**. Save and edit.
2. In the **ADVANCED** section (right), find **Use aggregation** → set to **Yes** → Apply. (This unlocks aggregation settings on each field.)
3. **FIELDS** → add **Content: Cuisine** (the term) and **Content: ID** (the node id).
4. Click **Content: ID** → its **Aggregation settings** → choose **Count** → Apply. Rename its label to "Recipes".
5. Make sure Cuisine's aggregation type is **Group results together** (the default for a non-numeric field).
6. Preview: you get one row per cuisine with a count. Visit `/reports/by-cuisine`.

> **Mental model:** with aggregation on, Views adds a `GROUP BY` on your non-aggregated fields (Cuisine) and applies the aggregate function (`COUNT`) to the chosen field (node ID). Same idea as SQL `GROUP BY`.

---

## 2. EVA — show a chef's recipes *on the chef page*

**Why:** on Day 1 you built a chef's-recipes page at a URL with a contextual argument. **EVA (Entity Views Attachment)** is the more elegant, commonly-used approach: it renders a view *as a field* inside the chef entity's display — no separate URL needed. Knowing EVA (and when to use it vs a path argument) is a strong Views answer.

1. Add the module: `composer require drupal/eva` then `drush en eva -y`. (EVA is contrib; core has no built-in "view as a field on an entity" display.)
2. **Add view.** Name `Chef recipes (EVA)`. Show **Content** of type **Recipe**. Add an **EVA** display (the "Add" display dropdown now lists **EVA field**).
3. On the EVA display: set **Entity type = User** (chefs are users after Day 5; if you kept Chef as a content type, choose Content/Chef). This exposes the view as a pseudo-field on that entity.
4. **CONTEXTUAL FILTERS →** add the relationship from recipe → author so it filters to *this* chef. With EVA, the current entity is passed as the argument automatically; set **When the filter value is not available → Provide default value → the entity from context**.
5. Go to the User (or Chef) entity's **Manage display** (`/admin/config/people/accounts/display`) — you'll see a **"Chef recipes (EVA)"** field. Drag it into the display, set its label.
6. View a chef → their recipes render inline, no custom URL.

> **EVA vs the Day-1 path argument (say this):** "A path-argument view lives at its own URL and is great for a dedicated page. EVA embeds the view *inside* the entity's rendered display, so it travels with the entity wherever it's shown. I'd use EVA for 'related content on the entity' and a path view for a standalone listing page."

---

## 3. Attachment display — "More {cuisine} recipes" under the listing

**Why:** an **Attachment** is a secondary view display that renders before/after another display. It's core (no contrib), and the pattern — "attach a related block beneath a page" — is common.

1. Open your Day-1 **Recipes** view → **Add → Attachment**.
2. On the Attachment: format **Grid** of teasers, **Items to display = 3**.
3. **CONTEXTUAL FILTERS →** add **Has taxonomy term ID** (as in Day 1) so it shows same-cuisine recipes; default from URL / node context.
4. Scroll to the Attachment's **Attachment settings** → **Attach to:** tick the **Page** display → set **Attachment position: After**.
5. Add a header (Global: Text area) titled "More recipes like this."
6. Visit the recipes page → the attachment renders beneath it.

> **Attachment vs Block vs EVA:** attachment = bolt another display onto *a specific display of the same view*; block = reusable display you place anywhere; EVA = display that rides inside an entity. Being able to pick the right one is the point.

---

## 4. Exposed filters & sorts — group them and add "Quickest first"

**Why:** exposed-filter UX is what editors and visitors actually touch. Grouped filters and exposed sorts show you think about usability, not just data.

**Grouped exposed filter (difficulty as friendly labels):**

1. On the **Recipes** page view → **FILTER CRITERIA** → add **Content: Difficulty** → expose it.
2. In the exposed settings click **Grouped filters** → define groups, e.g. label "Easy meals" → operator *is* → value `easy`; "Any" as the default. Apply.

**Exposed sort ("Quickest first"):**

3. **SORT CRITERIA** → add **Content: Total time** (the field you stored on Day 6) → **Sort ascending**.
4. Click the sort → **Expose this sort** → give it the label "Quickest first". Apply.
5. Add a second exposed sort on **Authored on** labelled "Newest" if you want a choice.
6. Preview → visitors now get a "Sort by" dropdown and a friendly difficulty filter.

> **Why the Day-6 stored field matters here:** you can only sort on a *stored* value. If total time were computed only at render (Day 2), this sort would be impossible — which is exactly why Day 6 stored it via `presave`.

---

## 5. Feed / REST export — recipes as JSON

**Why:** Views can expose data as an API endpoint. This bridges to the Day-5 integration mindset (now you're the *provider*) and is a genuinely useful skill.

1. Ensure core **RESTful Web Services / Serialization** is on (`drush en rest serialization -y` if needed).
2. Open the **Recipes** view → **Add → REST export**.
3. Set the REST export **path** to `api/recipes`.
4. **FORMAT → Serializer → Settings →** tick **json** (and hal_json if you like).
5. **FIELDS →** choose the fields to expose (Title, Cuisine, Total time, a link). REST export uses the raw field values.
6. Save. Visit `/api/recipes?_format=json` → a JSON array of recipes.

> **Interview framing:** "Views REST export gives you a read API over your content without writing a controller — great for a decoupled front end or a partner integration. For anything beyond simple reads I'd reach for JSON:API (core) instead."

---

## 6. Rewriting & global fields (quick recap + one addition)

- **Rewrite results** (Day 1): override a field's output with tokens, e.g. `{{ field_total_time }} min`.
- **Global: Custom text** field: inject arbitrary markup/tokens as a "field" — handy for building a combined label or a call-to-action link. Remember **token order**: a field can only use tokens from fields listed **above** it.
- **No results behavior**: add a Global: Text area under "No results behavior" so empty views show a friendly message, not a blank space.

---

## 7. Views live in config (ties to Day 3)

Everything you built today is configuration. Run `drush cex` and look in `config/sync`: each view is a `views.view.*.yml` file (e.g. `views.view.recipes.yml`). That means:

- Views travel between environments via `cex`/`cim` like any config.
- A code reviewer reviews your view as a YAML diff in the PR.
- When core Views isn't enough, you write a **custom Views plugin** (field/filter/sort/area) — a plugin class in `src/Plugin/views/…`. Awareness is enough for the vetting call; know it's *possible* and that it's a plugin.

---

## 8. End-of-day verification (say these out loud)

1. How aggregation works in Views (it's a `GROUP BY` + aggregate function).
2. **EVA vs path-argument vs block vs attachment** — when you'd use each to show related content.
3. Why you can sort on `total_time` now but couldn't with a render-only value (Day 6 link).
4. Grouped exposed filters and exposed sorts — why they matter for UX.
5. REST export vs JSON:API for exposing content.
6. That Views are config and travel via `cex`/`cim`; a custom Views plugin is the escape hatch.

## Interview Q&A

| Question | Answer shape |
|---|---|
| "Show related content inside an entity?" | EVA (renders a view as a field on the entity); contrast with a path-argument page. |
| "Count/aggregate content in Views?" | Turn on aggregation → GROUP BY non-aggregated fields, COUNT/SUM the rest. |
| "Attach a related list under a page?" | Attachment display, attach to the page, position After. |
| "Expose data from Drupal as an API?" | Views REST export for simple reads; JSON:API for a full entity API. |
| "How do Views move between environments?" | They're config (`views.view.*.yml`) — `cex`/`cim`. |

---

### Sources

- [Views overview (Drupal.org)](https://www.drupal.org/docs/user_guide/en/views-chapter.html)
- [EVA: Entity Views Attachment (Drupal.org project)](https://www.drupal.org/project/eva)
- [JSON:API & REST in core (Drupal.org)](https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module)
