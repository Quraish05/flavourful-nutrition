# Flavorful — Day 7 Lab: Advanced Views (click-by-click)

> Companion to the [advanced curriculum](advanced-plan-days6-9.md). Day 1 got you building basic Views; today goes into the features real projects lean on — aggregation, rendering a view *inside* an entity, attachments, exposed-filter UX, and data feeds. Views is the tool you'll touch daily, so depth here is direct proof of site-building experience.

**Build target:** five upgrades to Flavorful's Views — a recipes-per-cuisine **aggregation**, the chef's recipes shown **inside the chef page (EVA)**, a **"More {cuisine} recipes" attachment** under the listing, a **grouped exposed filter + a "Quickest first" sort**, and a **JSON feed** of recipes.

> Prerequisite from Day 6: recipes now have a stored `field_total_time` — that's what makes the "Quickest first" sort possible.

---

## 1. Aggregation — count recipes per cuisine

**Why:** aggregation (`COUNT`, `SUM`, `GROUP BY`) turns a list of rows into a summary. "How many recipes per cuisine?" is a classic reporting need, and knowing Views can do it (without custom code) is a real signal.

1. Go to `/admin/structure/views/add`. **View name:** `Recipes per cuisine`. *Show* = **Content**, *of type* = **Recipe**. Tick **Create a page**, **Path** = `reports/by-cuisine`, **Display format** = **Table** of **Fields**. Click **Save and edit**.
2. Turn aggregation on: on the **right-hand column** under **ADVANCED**, click the link **Use aggregation: No**. In the dialog set it to **Yes** (tick **Aggregate**) → **Apply**. *(Now every field/filter row gains an extra "Aggregation settings" link.)*
3. Add the two fields: **FIELDS → Add** → search `cuisine` → tick **Content: Cuisine** → Add and configure → Apply. Then **FIELDS → Add** again → search `ID` → tick **Content: ID** → Add and configure → Apply.
4. Make **ID** a count: in the **FIELDS** list, click **Content: ID**. At the top of its settings you'll see an **Aggregation settings** link (only appears because step 2 is on) → click it → set **Aggregation type = Count** → **Apply**. Back on the field settings, set the **Label** to `Recipes` → **Apply**.
5. Confirm **Cuisine** groups rather than counts: click **Content: Cuisine → Aggregation settings** → it should be **Group results together** (the default for a text/term field) → **Apply**.
6. Look at the **Preview** at the bottom — one row per cuisine with a count. Click **Save**, then visit `/reports/by-cuisine`.

> **Mental model:** with aggregation on, Views adds a `GROUP BY` on your non-aggregated fields (Cuisine) and applies the aggregate function (`COUNT`) to the chosen field (node ID). Same idea as SQL `GROUP BY`.

---

## 2. EVA — show a chef's recipes *on the chef page*

**Why:** on Day 1 you built a chef's-recipes page at a URL with a contextual argument. **EVA (Entity Views Attachment)** is the more elegant, commonly-used approach: it renders a view *as a field* inside the chef entity's display — no separate URL needed. Knowing EVA (and when to use it vs a path argument) is a strong Views answer.

> **Big picture before the clicks:** EVA lets you attach a View to an entity's display *as if it were a field*. When Drupal renders that entity, EVA hands the **entity's ID** to your View as its contextual-filter argument. So for a chef (a User), EVA passes the chef's **user ID**, and our View filters recipes down to the ones **authored by that user ID**. That's the whole trick — no relationship gymnastics needed, because a node's author (`uid`) is a direct property.

**Step 1 — Install EVA.** In your terminal:

```bash
composer require drupal/eva
drush en eva -y
drush cr
```

**Step 2 — Create the View.**

1. Go to `/admin/structure/views/add`.
2. **View name:** `Chef recipes (EVA)`.
3. Under **VIEW SETTINGS**: *Show* = **Content**, *of type* = **Recipe**.
4. Leave **"Create a page"** and **"Create a block"** *unticked*.
5. Click **Save and edit**.

**Step 3 — Add the EVA display.**

1. In the Views editor, click the **Add** button next to the display tabs (top-left, the "+").
2. In the dropdown pick **EVA Field**. A new **"EVA Field"** display appears — click it to select it.

**Step 4 — Tell EVA which entity to attach to.**

1. On the EVA display, find the **Entity type** setting (in the EVA display's settings, usually shown as `Entity type: …` in the middle column).
2. Click it, set **Entity type = User**, click **Apply**. *(Chefs are Users after Day 5. If you kept Chef as a content type instead, choose **Content** and its bundle, and in Step 5 use the `field_chef` reference instead of the author.)*

**Step 5 — Filter recipes to the current chef (contextual filter).**

1. On the right, in the **ADVANCED** section, next to **CONTEXTUAL FILTERS** click **Add**.
2. In the popup **search box, type `authored`**.
3. Tick **Content: Authored by** (this filters nodes by their author's user ID) → click **Add and configure contextual filters**.
4. On the config screen:
   - Under **WHEN THE FILTER VALUE IS NOT AVAILABLE**, choose **Hide view** *(so it only shows when EVA supplies a user ID)*.
   - Leave everything else as default → click **Apply**.
   - *You do **not** set a default value — EVA injects the viewed user's ID automatically.*
5. Click **Save** (top-right of the Views editor).

> *Fallback:* if you don't see **Content: Authored by** in the list, instead add a **Relationship** (`ADVANCED → RELATIONSHIPS → Add → Content: Author`), then add the contextual filter **User: Uid** and set its *Relationship* to that author relationship. Same result, one extra hop.

**Step 6 — Place the EVA field on the chef's page.**

1. **First make sure the View is saved** — clicking **Save** (top-right of the Views editor) *after* adding the EVA display and setting its Entity type, then run `drush cr`. The field won't exist until you do this.
2. Go to the Manage display screen **that matches the entity type you picked in Step 4**:
   - Entity type = **User** → `/admin/config/people/accounts/display`
   - Entity type = **Content / Chef** → `/admin/structure/types/manage/chef/display`
3. Make sure you're on the **Default** view-mode tab.
4. **Scroll to the bottom** — the field **"Chef recipes (EVA)"** starts in the **Disabled** region at the end of the field table.
5. Drag it **up** into the visible area, set its **Label** (e.g. "Recipes by this chef"), click **Save**.

> 🛠 **Can't find the field?** Work through these — it's almost always #1 or #2:
> 1. **View not saved / cache** — re-open the View, click **Save**, run `drush cr`, reload the Manage display page.
> 2. **Wrong screen for your entity type** — if you set Entity type = User, it's on the *people* display; if Chef is still a **content type**, it's on `/admin/structure/types/manage/chef/display`, not the user display.
> 3. **EVA display's Entity type didn't stick** — re-open the View → **EVA Field** display → confirm `Entity type: User` (set + Save if blank).
> 4. **Bundle mismatch** — if the EVA display shows a **Bundles** selector, make sure it includes the right bundle (or is left to "all").
> 5. **Wrong view mode** — you're on Teaser/Compact instead of **Default**.

**Step 7 — 🔎 Test it (user-facing UI).**

1. Visit a chef's page at `/user/{uid}` (use a real chef's user ID from `/admin/people`).
2. Their recipes render inline on the profile. Open a different chef → different recipes. No custom URL involved — that's the EVA payoff.

> **EVA vs the Day-1 path argument (say this):** "A path-argument view lives at its own URL and is great for a dedicated page. EVA embeds the view *inside* the entity's rendered display, so it travels with the entity wherever it's shown. I'd use EVA for 'related content on the entity' and a path view for a standalone listing page."

---

## 3. Attachment display — "More {cuisine} recipes" under the listing

**Why:** an **Attachment** is a secondary view display that renders before/after another display. It's core (no contrib), and the pattern — "attach a related block beneath a page" — is common.

1. Open your Day-1 **Recipes** view (`/admin/structure/views/view/recipes`). Click the **Add** button next to the display tabs → choose **Attachment**. Click the new **"Attachment"** display to select it.
2. Set its format: **FORMAT → Format:** click the current value → choose **Grid** → Apply; and **Show:** **Content** (teasers). Set **PAGER → Items to display = 3** (Display a specified number of items, 3).
3. Filter it to the same cuisine: **ADVANCED → CONTEXTUAL FILTERS → Add** → search `taxonomy` → tick **Has taxonomy term ID** → Add and configure. Under **WHEN THE FILTER VALUE IS NOT AVAILABLE** → **Provide default value → Taxonomy term ID from URL** → tick **Load default filter from node page** + **Limit terms by vocabulary → Cuisine** (exactly like Day 1's related block) → Apply.
4. Attach it to the page: still on the Attachment display, in the middle column find **ATTACHMENT SETTINGS**. Click **Attach to:** → tick the **Page** display → Apply. Click **Attachment position:** → choose **After** → Apply.
5. *(Optional)* Add a heading: **HEADER → Add** → **Global: Text area** → type "More recipes like this." → Apply.
6. Click **Save**. Visit `/recipes` (or a recipe) → the attachment renders beneath the main list.

> **Attachment vs Block vs EVA:** attachment = bolt another display onto *a specific display of the same view*; block = reusable display you place anywhere; EVA = display that rides inside an entity. Being able to pick the right one is the point.

---

## 4. Exposed filters & sorts — group them and add "Quickest first"

**Why:** exposed-filter UX is what editors and visitors actually touch. Grouped filters and exposed sorts show you think about usability, not just data.

**Grouped exposed filter (difficulty as friendly labels):**

1. On the **Recipes** view's **Page** display → **FILTER CRITERIA → Add** → search `difficulty` → tick **Content: Difficulty** → **Add and configure filter criteria**.
2. On the config screen, tick **Expose this filter to visitors** → **Apply** (accept the exposed defaults).
3. Re-open the filter (click **Content: Difficulty** again). Near the top choose the radio **Grouped filters** (instead of *Single filter*).
4. Now define the groups in the table that appears — click **Add another item** for each:
   - Row 1: **Label** = `Easy meals`, **Operator** = *Is equal to*, **Value** = `easy`.
   - Row 2: **Label** = `Quick & simple`, **Operator** = *Is equal to*, **Value** = `medium` (etc.).
   - The blank first row acts as **"- Any -"** (the default). → **Apply**.

**Exposed sort ("Quickest first"):**

5. **SORT CRITERIA → Add** → search `total` → tick **Content: Total time** (the field you stored on Day 6) → **Add and configure sort criteria** → set order **Sort ascending** → **Apply**.
6. Re-open it (click **Content: Total time**) → tick **Expose this sort** → set **Label** = `Quickest first` → **Apply**.
7. *(Optional)* **SORT CRITERIA → Add → Authored on** → expose it, label `Newest`, so visitors get a choice.
8. Check the **Preview** → you now see a "Sort by" dropdown and a friendly difficulty filter. Click **Save**, visit `/recipes`.

> **Why the Day-6 stored field matters here:** you can only sort on a *stored* value. If total time were computed only at render (Day 2), this sort would be impossible — which is exactly why Day 6 stored it via `presave`.

---

## 5. Feed / REST export — recipes as JSON

**Why:** Views can expose data as an API endpoint. This bridges to the Day-5 integration mindset (now you're the *provider*) and is a genuinely useful skill.

1. Enable the core modules: in a terminal run `drush en rest serialization -y` (or **Extend** → tick **RESTful Web Services** + **Serialization** → Install), then `drush cr`.
2. Open the **Recipes** view (`/admin/structure/views/view/recipes`). Click the **Add** button next to the display tabs (top-left, the "+") → choose **REST export**. A **"REST export"** display appears — click it.
3. Set its **path**: in the middle column click the **Path** setting (**PATH SETTINGS → Path: /…**) → type `api/recipes` → Apply.
4. Pick the output format: **FORMAT → Settings** (next to *Serializer*) → tick **json** (and `hal_json`/`xml` if you like) → Apply.

**⚠️ The "does not use fields" fix (your exact problem):**

5. That message means the display's **row style is set to "Entity"**, which serializes the *whole* entity and therefore ignores the FIELDS section. You have two valid choices:
   - **Option A — export the whole entity (simplest):** leave **Show = Entity**. Skip the FIELDS section entirely; every recipe field comes out automatically. The "does not use fields" message is expected and fine here.
   - **Option B — hand-pick fields (what you were trying):** in the **FORMAT** section, next to **Show:** click the current value (**Entity**) → choose **Fields** → **Apply**. The FIELDS section now activates.
6. **(Option B only)** **FIELDS → Add** → add **Title**, **Content: Cuisine**, **Content: Total time**, and a link field. Each exposes its raw value in the JSON.
7. Click **Save** (top-right).

**🔎 Test it:** visit `/api/recipes?_format=json` in your browser → you get a JSON array of recipes (whole entities in Option A, just your chosen fields in Option B).

> **Interview framing:** "Views REST export gives you a read API over your content without writing a controller — great for a decoupled front end or a partner integration. Set the row style to **Fields** to hand-pick the payload, or **Entity** to dump the whole thing. For anything beyond simple reads I'd reach for JSON:API (core) instead."

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
