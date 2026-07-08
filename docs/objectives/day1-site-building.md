# Flavorful — Day 1 Hands-On Lab (Site Building, click-by-click)

*Companion to the 5-day prep guide. This is the "how do I actually click through it" version of Day 1. Paths shown are Drupal 10/11 admin paths — your admin toolbar will match. Do every step in your own site; the point is muscle memory, not reading.*

> **Before you start:** confirm you can reach `/admin` as admin, and that the **Views UI**, **Field UI**, **Taxonomy**, and **Media** modules are enabled (Extend → `/admin/modules`). On a standard install they already are. Keep a second browser tab open on the front end so you can watch changes appear.

---

## Part A — Taxonomy first (15 min)

Build vocabularies *before* content types, so the reference fields have something to point at.

1. Go to **Structure → Taxonomy** (`/admin/structure/taxonomy`) → **Add vocabulary**.
2. Create four vocabularies, one at a time: **Cuisine**, **Course**, **Dietary**, **Ingredients**. For each: type the name → **Save**.
3. Add a few terms to each: on the vocabulary row click **List terms → Add term**. E.g. Cuisine: Italian, Thai, Mexican; Course: Starter, Main, Dessert; Dietary: Vegan, Vegetarian, Gluten-free; Ingredients: Tomato, Basil, Chicken.
4. **Make Cuisine hierarchical** to learn nesting: add a parent "Asian", then add "Thai" and set its **Relations → Parent term = Asian**. This teaches you tags-vs-hierarchy.

*What you're learning:* a **vocabulary** is a container; **terms** are the values; terms are **taxonomy_term entities** and can have their own pages at `/taxonomy/term/{id}`.

---

## Part B — The Recipe content type (45 min)

1. **Structure → Content types** (`/admin/structure/types`) → **Add content type**.
2. Name: **Recipe**. Leave "Title" as the label. Under **Submission form settings** you can set the Title field label to "Recipe name" if you like. Click **Save and manage fields**.
3. You're now on **Manage fields** for Recipe. Every field below is added the same way: **Add field → pick type → name it → Save settings**. Add these:

   | Field label | Field type (in the Add-field picker) | Notes |
   |---|---|---|
   | Summary | Text (plain, long) | short description |
   | Hero image | **Reference → Media** | allowed media type: Image |
   | Prep time (min) | Number (integer) | |
   | Cook time (min) | Number (integer) | |
   | Servings | Number (integer) | |
   | Difficulty | **List (text)** | allowed values: `easy\|Easy`, `medium\|Medium`, `hard\|Hard` |
   | Steps | Text (formatted, long) | the method |
   | Cuisine | **Reference → Taxonomy term** | vocabulary: **Cuisine**, single value |
   | Course | **Reference → Taxonomy term** | vocabulary: **Course** |
   | Dietary | **Reference → Taxonomy term** | vocabulary: **Dietary**, **set "Allowed number of values" = Unlimited** |
   | Ingredients | **Reference → Taxonomy term** | vocabulary: **Ingredients**, Unlimited |
   | Chef | **Reference → Content** | target type: Content, allowed bundle: **Chef** (create Chef first — Part C — or come back and set this) |

   **How to add one field, in detail (using Cuisine as the example):**
   - Click **Create a new field** (or **Add field**).
   - Choose category **Reference**, then **Taxonomy term**. Give it label **Cuisine**. Continue.
   - On the field settings step, **Allowed number of values = Limited: 1** (Cuisine is single). Save.
   - On the next screen (field instance settings), under **Reference type → Vocabularies**, tick **Cuisine**. Save settings.

4. **The three tabs that trip everyone up** — click each and understand it:
   - **Manage fields** — *what data exists* on the Recipe.
   - **Manage form display** — *how the edit form looks* (widget order, which widget: autocomplete vs. select vs. checkboxes for Difficulty).
   - **Manage display** — *how the saved node renders* on the page (label position, order, formatters, which fields show in "Default" vs "Teaser" view mode).

5. On **Manage display**, switch the **Teaser** view mode (top of page → enable/customize Teaser) so your listing Views later have a clean teaser: show Hero image, Title, Cuisine, Cook time; hide Steps.

*What you're learning:* a content type is a **bundle** of the Node entity. Fields are reusable across bundles. The form-display / display split is Drupal's separation of *input* from *output*.

---

## Part C — The Chef content type + Media + Roles (30 min)

1. **Add content type → Chef** (`/admin/structure/types/add`). Add fields: **Bio** (Text long), **Photo** (Reference → Media / Image), **Specialty** (Text plain). Save.
2. Go back to Recipe's **Manage fields** and finish the **Chef** reference field: target type Content, allowed bundle **Chef**.
3. **Media:** Media is core. When you first add a Hero image on a recipe, you'll use the **Media library** widget — click **Add media → upload**. Media items are reusable entities (`/admin/content/media`).
4. **Roles & permissions:**
   - **People → Roles** (`/admin/people/roles`) → **Add role**: create **Editor** and **Chef**.
   - **People → Permissions** (`/admin/people/permissions`): give **Editor** create/edit/delete for Recipe + Chef; give **Chef** create/edit *own* Recipe only. Give neither any "Administer …" permission.

*What you're learning:* least-privilege. Editors never get `administer content types` or `administer permissions` — those are admin-only.

---

## Part D — Enter sample content (20 min)

**Content → Add content**. Create 2–3 **Chefs** first, then **5–6 Recipes** spread across at least 2 cuisines and a couple of dietary tags, each assigned to a chef. You need this data or the Views below will look empty and you won't be able to tell if they work.

---

## Part E — The three Views (the main event, ~2 hrs)

Views is the single most-tested site-building skill. Build all three. Read the "why" notes — interviewers ask *why*, not just *how*.

### View 1 — Recipe listing page with exposed filters

**Goal:** a page at `/recipes` showing recipe cards, with Cuisine + Dietary dropdown filters visitors can use.

1. **Structure → Views → Add view** (`/admin/structure/views/add`).
2. **View name:** `Recipes`.
3. **View settings:** Show **Content** of type **Recipe**, sorted by **Newest first**.
4. Tick **Create a page**. Page **title** = `Recipes`, **path** = `recipes`.
5. **Display format:** choose **Grid** of **Fields** (pick "Fields" so you can hand-pick and rewrite columns; choose "teasers/Content" only if you want the whole node rendered through its view mode). Items to display: `12`, tick **Use a pager**.
6. Click **Save and edit**. You're now in the Views UI. The layout: left = **Fields/Filter/Sort** etc., center = display settings, bottom = **Preview**.

> **Gotcha — "The selected style or row format does not use fields."** If the **FIELDS** box shows this message, your row format is set to **Content** (rendered teaser), which has no separate fields. Fix it in the **FORMAT** section (top-center): next to **Show:** click **Content** → in the popup choose **Fields** → **Apply**. The FIELDS box activates (Title is added for you) and **Rewrite results** becomes available on each field. Switch **Show** back to **Content** any time you'd rather render teasers.

**Add the exposed filters:**

7. In the **FILTER CRITERIA** box → **Add** (the little dropdown/＋).
8. Search **Cuisine** → tick **Content: Cuisine** → **Add and configure filter criteria**.
9. On the config screen: tick **Expose this filter to visitors**. Set **Operator** = "Is one of". Under **Selection type** choose **Dropdown** (nicer than autocomplete for a known list). Optionally rename the exposed label to "Cuisine". **Apply**.
10. Repeat **Add filter → Content: Dietary**, expose it, Dropdown. Because Dietary allows multiple, you can also tick **Allow multiple selections**.
11. Confirm the auto-added filters are there: **Content: Published (Yes)** and **Content: Content type (= Recipe)**. Leave them un-exposed.
12. Look at the **Preview** at the bottom — you should see the exposed filter dropdowns and results. Then visit **`/recipes`** on the front end and use the filters.

**Bonus — fields vs relationships (do this to learn the concept):** To show each recipe's **chef name** in the listing:

13. **ADVANCED** (right side) → **RELATIONSHIPS → Add** → search "Chef" → tick **Content: Chef** (the field_chef reference) → Apply. (This "reaches through" the reference to the Chef node.)
14. Now **FIELDS → Add** → search **Title** → you'll see a Title option scoped to the **Chef** relationship — add it, and in its config set the **Relationship** dropdown to your Chef relationship. That prints the chef's name.

> **Why it matters (interview gold):** a **field** is a column you output; a **relationship** joins in *another entity* so you can then output *its* fields. "Show the author's name on an article listing" is the canonical relationship example.

**Bonus — rewrite field output:** This is done **in the Views UI** (inside the `Recipes` view you're building — *not* in the Recipe content type). It only appears when the view's **Show** setting is **Fields** (not "Content/teaser"), so make sure Cook time is added under **FIELDS** first. Then: in the **FIELDS** box click **Content: Cook time** → in its settings expand **REWRITE RESULTS → Override the output of this field** → set the text to `{{ field_cook_time }} min` → **Apply**. This teaches token-based field rewriting (and you can concatenate fields, e.g. build a "Prep + Cook = total" string).

---

### View 2 — "Related recipes" block (contextual filter, current node)

**Goal:** a block that, on any recipe page, lists *other* recipes sharing that recipe's **Cuisine**, excluding the one you're viewing. This is the classic contextual-filter exercise — the exact steps matter.

> **Contextual vs. exposed filters (know this cold — it's a common interview question).**
> - **Exposed filter** — the *visitor* picks the value; it renders as a form control (dropdown/textfield) on the page. Example: the Cuisine/Dietary dropdowns on `/recipes`.
> - **Contextual filter** (Views calls it an *argument*) — the value comes from *context*, not the visitor: the **URL path** or the **current node/page**. It's invisible to the visitor. Examples: related recipes reads the current recipe's cuisine; the chef page reads the chef ID from the URL.
> - **Plain (non-exposed) filter** — a fixed value *you* set at build time, e.g. `Published = Yes`.
>
> Memory hook: **exposed = the user chooses · contextual = the page/URL decides · plain = you decide.**

1. **Structure → Views → Add view.** Name `Related Recipes`. Show **Content** of type **Recipe**. **Do NOT** tick "Create a page." Instead scroll down and tick **Create a block**. **Save and edit.**
2. **Display format** = **Unformatted list** of **teasers**, **Items to display = 4**, pager off.
3. **ADVANCED → CONTEXTUAL FILTERS → Add** → search **taxonomy** → tick **Content: Has taxonomy term ID** → **Add and configure**.
4. Under **WHEN THE FILTER VALUE IS NOT AVAILABLE**, select **Provide default value**. In the **Type** dropdown choose **Taxonomy term ID from URL**.
5. Extra options appear — tick these three:
   - **Load default filter from term page** (works on `/taxonomy/term/*` pages)
   - **Load default filter from node page** ← *this is the key one — it reads the term off the current recipe node*
   - **Limit terms by vocabulary** → tick **Cuisine** (so it relates by cuisine, not by every term)
6. Under **WHEN THE FILTER VALUE IS AVAILABLE OR A DEFAULT IS PROVIDED**, tick **Allow multiple values** and **Reduce duplicate values**. **Apply.**

**Exclude the current recipe from its own related list:**

7. **CONTEXTUAL FILTERS → Add** again. In the "Add contextual filters" popup:
   - Set the **Category** dropdown at the top to **Content** (or **- All -**).
   - In the **search box type just `ID`** (not "Content ID" — that won't match).
   - In the results, find the row where **Category = Content** and **Name = ID** — that's **`Content: ID`** (its machine name is `nid`). Tick its checkbox.
   - Click **Add and configure contextual filters**.
   - *Can't see it at all?* Then this view's base isn't "Content" — but the Recipes-based view you built here will have it.
8. **WHEN THE FILTER VALUE IS NOT AVAILABLE → Provide default value → Type = Content ID from URL**. (On a recipe page this resolves to the current node's ID.)
9. Scroll down and expand **MORE** → tick **Exclude**. **Apply.** (With "Exclude" on, the current node's ID is *removed* from the results, so a recipe never lists itself.)

**Place the block so it only shows on recipe pages:**

10. **Structure → Block layout** (`/admin/structure/block`) → find your theme → in a region (e.g., **Sidebar** or **Content**) click **Place block** → find **Related Recipes** → **Place block**.
11. In the block config → **Visibility → Content types → Recipe** (or **Pages** tab restrict to `/node/*`). **Save.**
12. Test: open a recipe on the front end. You should see up to 4 other same-cuisine recipes, and *not* the current one. Open a recipe with a unique cuisine → the block should be empty (correct behaviour).

> **Why it matters:** a **contextual filter** takes its value from context — the URL or the current node — instead of from the visitor. "Related content" and "show items for the term you're on" are the textbook use cases. The **"Load default filter from node page"** checkbox is the exact trick that makes it read the current node's term; interviewers love when you name it.

---

### View 3 — A chef's recipes page (contextual filter from the path)

**Goal:** a page at `/chefs/{chef-id}/recipes` listing all recipes by that chef.

1. **Add view.** Name `Chef Recipes`. Show **Content** of type **Recipe**. Tick **Create a page**, **path** = `chefs/%/recipes` (the `%` is the argument slot). **Save and edit.**
2. Format = **Grid** or **Unformatted list** of teasers.
3. **ADVANCED → CONTEXTUAL FILTERS → Add** → search **Chef** → tick **Content: Chef** (the `field_chef` target ID) → Add and configure.
4. **WHEN NOT AVAILABLE →** choose **Display "Page not found" (404)** — you always want a chef ID in the URL for this page.
5. **Apply**, then confirm the **path** shows the argument. Visit **`/chefs/<a-chef-node-id>/recipes`** (use a real Chef node ID from `/admin/content`). You'll see only that chef's recipes.
6. **Make it reachable — add a link to each chef's recipes page.** The page works, but nothing links to it yet. Do it with one of these two fully-core methods (the contextual-filter mechanics above are the real learning goal; this is polish):

   **Method A — a "Chefs" listing View whose names link to the recipes page (recommended):**
   1. **Structure → Views → Add view.** Name `Chefs`. Show **Content** of type **Chef**. Tick **Create a page**, path `chefs`, **Display format = Unformatted list** (or Table) of **Fields**. **Save and edit.**
   2. In **FIELDS**, add the node ID so its token is available: **Add → search `ID` → Content: ID → Apply**. In its settings tick **Exclude from display** (you only need the token, not the number shown). **Apply.**
   3. **Reorder fields so `Content: ID` sits ABOVE `Title`** — click the **dropdown arrow next to Add → Reorder** (or the "⠿"/arrange handle) and drag ID above Title. *Tokens are only available from fields listed above the one using them* — this is the #1 reason a rewrite token comes out blank.
   4. Click the **Content: Title** field → expand **REWRITE RESULTS** → tick **Output this field as a link** → in **Link path** enter:
      ```
      chefs/{{ nid }}/recipes
      ```
      (No leading slash. To confirm the token name, expand **REPLACEMENT PATTERNS** just below — you'll see `{{ nid }}` listed.) **Apply.**
   5. **Save.** Visit **`/chefs`** — each chef's name is now a link to their recipes page.

   **Method B — a link on the chef's own node page, via Twig (ties into Day 2 theming):**
   1. In your `flavorful_theme`, create `templates/node--chef.html.twig` (copy the base `node.html.twig` structure, or start minimal).
   2. Add this where you want the link:
      ```twig
      <a class="chef__recipes-link" href="/chefs/{{ node.id }}/recipes">
        {{ 'View all recipes by'|t }} {{ label }}
      </a>
      ```
   3. `drush cr`, open a Chef node → the "View all recipes by …" link takes you to that chef's recipes page.

> **Alternative worth knowing:** you could instead add a **relationship** on `field_chef` and filter on the chef's node ID — teaches that contextual filters and relationships often solve the same problem two ways.

---

## Part F — Formats & consolidation (20 min)

Cycle the **Format** setting on any view (**Table / Grid / Unformatted list / HTML list**) and the **Show** setting (**Fields vs. Content/teaser**) so you can speak to the difference:

- **Fields** = you pick individual columns/values (max control; needed for tables and custom cards).
- **Content (teaser/full)** = renders the node through its view mode (fast, consistent with theming).
- **Table** = sortable columns; **Grid** = cards; **Unformatted** = divs you style yourself.

**End-of-day verification (say these out loud):**
1. Difference between a **field** and a **relationship** in Views, with the chef-name example.
2. How the "Related recipes" block finds the right recipes *without* the visitor choosing anything (contextual filter + "load default from node page").
3. When you'd use **Content type** filter vs **taxonomy** vs a **List field** in the data model.
4. `drush cr` if a View change isn't showing — and why (render cache).

---

## Handy paths & Drush

```
/admin/structure/types            content types
/admin/structure/taxonomy         vocabularies
/admin/structure/views            views
/admin/structure/block            block layout
/admin/content   /admin/content/media    nodes / media
/admin/people/roles  /admin/people/permissions

drush cr                 rebuild cache after config/UI changes
drush cex / cim          export / import config (Day 3)
drush uli                admin login link
```

*Next: want the same click-by-click treatment for Day 2 (the `flavorful_nutrition` module + Twig recipe template) and Day 4 (the Site Studio Recipe Card)? Say the word and I'll write those labs too.*

---

### Sources
- [Views contextual filter — load default from node page (Drupal.org issue #2974879)](https://www.drupal.org/project/drupal/issues/2974879)
- ["Has taxonomy term ID" default value integration (Drupal.org #2986923)](https://www.drupal.org/project/domain_taxonomy/issues/2986923)
- [Adding a contextual filter to a View (Red Crackle)](https://redcrackle.com/blog/adding-contextual-filter-view-drupal-8/)
