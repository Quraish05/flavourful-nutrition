# Plan — Views output (Notion Week 3–5)

> Branch: config changes made directly in the Views UI; not yet exported or committed · Created: 2026-09-14 · Last updated: 2026-09-14
>
> **Status: complete — all four findings fixed, verified and exported.** Shipped as PR #27. V-4 needed three passes at the Views UI (deviation 4).
>
> Execution plan for the *Views output* section of the Notion doc *"Phase 1 — Accessibility to Audit Level"* (Week 3–5, *Audit Flavourful for real*). Neither an objective nor an outcome — this is the transient middle state. It feeds an eventual outcome note under [`docs/actual-outcomes/`](../actual-outcomes/).

---

## Why this plan exists

Three of the section's five bullets are already closed — `scope="col"` and `aria-sort` come from core, the glossary table got its caption in Task 5, and `related_recipes` renders an `<h2>` since the Cellar redesign. What the checklist did **not** mention is the strongest defect on the two pages it named, and it is not subtle: `/chefs` ships ten links with no accessible name at all.

**Decided:** fix all four in the Views UI and export selectively. Not one of them needs a line of PHP or Twig — which is worth stating in the audit, because "the defect was in configuration, not code" is a different conversation with a client than "your developer wrote bad markup".

**Rejected — do not re-propose:** fixing V-1 by overriding `views-view-fields.html.twig` to strip the inner link. The nested anchor is produced by two Views settings that both mean "make this a link"; a template that papers over the second one leaves the misconfiguration in place for the next display that uses the same field.

**Known ordering constraint:** cut this branch only once `fix/a11y-aria-current` has merged. That branch is theme-only and this one is config-only; overlapping them means a `config:export` that sweeps up a half-finished working tree.

---

## Findings

| # | Finding | Where | Criterion | Fix |
|---|---|---|---|---|
| **V-1** | `/chefs` renders **10 links with no accessible name** | `views.view.chefs` title field | **2.4.4 (A)**, **4.1.2 (A)** | uncheck one box |
| **V-2** | `/reports/by-cuisine` table has **no `<caption>`** | `views.view.recipes_per_cuisine` | **1.3.1 (A)** | one text field |
| **V-3** | `/chefs` rows carry **no headings at all** | `views.view.chefs` title field | 2.4.6 (AA) | one dropdown |
| **V-4** | Pager heading is `h4`, so the outline skips h1 → h4 | both views' pager | 1.3.1 (A), weakly | one dropdown |

### V-1 — two link mechanisms stacked on one field

The chefs title field is configured to link **twice**:

```
settings: {"link_to_entity": true, "link_rel": "canonical"}    → links to /node/67
alter:    {"make_link": true, "path": "chefs/{{ nid }}/recipes"} → wraps that in another link
```

which emits `<a href="/chefs/67/recipes"><a href="/node/67">Joe H</a></a>`. Nested anchors are invalid HTML, and the parser does not simply ignore the inner one — it **splits them into siblings**. Verified in the live DOM:

```html
<a href="/chefs/67/recipes"></a><a href="/node/67" hreflang="en">Joe H</a>
```

**20 links on the page, 10 of them empty.** A screen reader announces "link" with no name; a keyboard user tabs onto ten invisible zero-size targets. And the failure is precisely inverted from the intent: the destination somebody configured deliberately — the chef's recipe listing — is the one with no name, while the name that *is* exposed points at an unaliased `/node/67`.

Worth flagging in the audit as the **one finding in this set that an automated scan would catch.** Every other finding in this workstream needed reading config or the post-JS DOM. That contrast is the argument for paid audit work, and it is more persuasive when you can name the exception.

### V-2 — the table style has no options at all

```php
'style' => ['type' => 'table']
```

No `options` key whatsoever, so no caption, no summary, no description. The theme's `views-view-table.html.twig` renders `<caption>` only when one is configured, and none is — so the table announces with no name. This is the same defect fixed for `glossary` in Task 5, on a view nobody revisited.

### V-3 — a listing with nothing to navigate by

`row: fields` with `element_type: ''` on the title, so each chef renders `<div><span><a>`. Ten chefs, zero headings. `/recipes` gives every card an `<h2>`, so a screen-reader user can jump between recipes but not between chefs — and the inconsistency is what makes this worth fixing rather than the absolute severity.

### V-4 — the pager heading level

Both views set `pagination_heading_level: h4`. On `/chefs` and `/reports/by-cuisine` the deepest content heading is the `h1`, so the outline reads h1 → h4: a two-level skip, in a visually-hidden heading nobody sees and every screen reader announces.

---

## Observations — log, do not fix

- **`h2` "Main navigation" and "Breadcrumb" precede the `h1`** on every page. This is core's `aria-labelledby` pattern for menu blocks and is standard Drupal practice, so it is not a defect — but an auditor will ask about it, and having the answer ready is worth more than the fix would be.
- **`/recipes/italian` returns 200 with zero rows.** Not accessibility at all: the contextual filter wants a term ID, not a slug. A URL shape that looks valid and silently shows nothing is a content bug worth raising separately.

---

## Phase 1 — `/chefs` (V-1 and V-3)

Both are on the same field, so do them in one visit. Views UI: `/admin/structure/views/view/chefs`.

| # | Step | Where | Status |
|---|---|---|---|
| 1 | Open the title field | *Fields* → **Content: Title** | **Done** |
| 2 | **Untick "Link to the Content"** | In the **Formatter settings** area near the top (the `link_to_entity` checkbox) | **Done** — 0 nested anchors remain |
| 3 | Confirm the rewrite survives | Expand **Rewrite results** → *Output this field as a custom link* must stay ticked, with link path `chefs/{{ nid }}/recipes` | **Done** — links point at `/chefs/67/recipes`, not `/node/67` |
| 4 | Set the HTML element to **H2** | Expand **Style settings** → tick *Customize field HTML* → **HTML element: H2** | **Done** — renders `<h2 class="field-content">` (note: `field-content`, not `views-field`; see deviation 3) |
| 5 | Apply, then **Save** the view | The blue *Save* button — Views changes are not live until this | **Done** |

Notes:

- **Step 2 is the fix; step 3 is the guard.** Removing the wrong one of the two link mechanisms leaves a single link that points at `/node/67` — still one link, still named, but aimed at an unaliased path rather than the chef's recipe listing somebody deliberately configured. Check the rewrite before saving, not after.
- **Step 4 turns the wrapper into `<h2>`, not the link.** The heading contains the link; that is the correct nesting and what `/recipes` already does.
- If *Customize field HTML* is not visible, the field row is collapsed — **Style settings** is a collapsed `<details>` near the bottom of the modal.

## Phase 2 — `/reports/by-cuisine` (V-2)

Views UI: `/admin/structure/views/view/recipes_per_cuisine`.

| # | Step | Where | Status |
|---|---|---|---|
| 6 | Open the table settings | *Format:* **Table** → **Settings** | **Done** |
| 7 | Set **Caption for the table** | e.g. `Recipes grouped by cuisine` | **Done** — renders exactly that |
| 8 | Leave *Table description* and *Summary title* empty | Same modal — they add `<summary>`/`<details>`, which this table does not need | **Done** |
| 9 | Apply, then **Save** | | **Done** |

The caption is the table's accessible name, so make it say what the table contains, not what the page is called — it is read immediately after "table" and before the row count.

## Phase 3 — the pager heading (V-4)

Do this on **both** views.

| # | Step | Where | Status |
|---|---|---|---|
| 10 | Open the pager settings | *Pager:* **Mini** → click the **Paged output, mini pager** link → **Pager options** | **Done** |
| 11 | Set **Pagination heading level** to **h2** | Same modal | **Done** — both views `h2`; the `h1 → h4` skip is gone from both pages |
| 12 | Repeat for the second view, then **Save** both | | **Done** — this is the step that needed repeating (deviation 4) |

`h2` rather than `h3`: on `/chefs` the pager will sit alongside the chef headings that step 4 creates, and on `/reports/by-cuisine` the only other heading is the `h1`. `h2` is correct in both, and keeping the two views consistent matters more than a perfect per-page fit.

## Phase 4 — verify, export, document

| # | Step | Status |
|---|---|---|
| 13 | Run the verification block below — all four checks must pass | **Done** — V-1 confirmed in the browser: 20 links / 10 empty → 10 links / 0 empty |
| 14 | Selective export via a temp dir: **`views.view.chefs.yml` and `views.view.recipes_per_cuisine.yml` only.** A plain `config:export` sweeps in the block drift, `system.site`, `system.performance` and the rest | **Done** — twelve items drifted; only these two copied across |
| 15 | Commit, PR. Note this PR **needs a config import on deploy**, like #25 | **Done** — PR #27 |
| 16 | Notion — tick the Views output bullets, and add V-1 as a finding. It is not in the checklist today | **Done** — V-1 added as a resolved finding, V-4 as a bullet that did not previously exist |

---

## Verification

```bash
# V-1 — source check only. Counting links with curl does NOT work (see below);
# all it can honestly tell you is whether the nested anchor is still emitted.
# Expect: 10 before the fix, 0 after.
curl -sk https://foodrecipes-drupal.ddev.site:33001/chefs \
  | grep -o '<a [^>]*><a ' | wc -l

# V-3 — expect 10 after the fix, 0 before.
# NOTE the class is `field-content`, not `views-field` — the first version of
# this check looked for the wrong one and reported 0 on a fixed page.
curl -sk https://foodrecipes-drupal.ddev.site:33001/chefs \
  | grep -o '<h2 class="field-content"' | wc -l

# V-2 — expect: caption: YES
curl -sk https://foodrecipes-drupal.ddev.site:33001/reports/by-cuisine | python3 -c "
import sys; h = sys.stdin.read()
print('caption:', 'YES' if '<caption' in h else 'NO')"

# V-4 — expect h2, not h4, and no h1 -> h4 skip
for u in /chefs /reports/by-cuisine; do
  echo \"== \$u\"
  curl -sk \"https://foodrecipes-drupal.ddev.site:33001\$u\" | python3 -c "
import sys, re
h = sys.stdin.read()
prev = 0
for m in re.finditer(r'<(h[1-6])[^>]*>(.*?)</\1>', h, re.S):
    lvl = int(m.group(1)[1])
    txt = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', m.group(2))).strip()[:40]
    skip = '  <-- SKIP' if prev and lvl > prev + 1 else ''
    print(f'  h{lvl}  {txt}{skip}')
    prev = lvl"
done
```

**V-1 must be confirmed in the browser, and this is not a preference.** The defect does not exist in the source — it is created by the HTML parser. In the source there is one `<a>` per chef, wrapping another; only once parsed does that become two siblings, one of them empty. Any curl-and-regex count will therefore report the page as fine, and the first version of the check in this plan did exactly that: *"links: 12 | empty: 0"* on a page carrying ten nameless links.

```js
// DevTools console on /chefs — this is the authoritative check
const links = [...document.querySelectorAll('.view-content a')];
console.log('total', links.length, 'empty', links.filter(a => !a.textContent.trim()).length);
// before: 20 / 10     after: 10 / 0
```

Generalise it: **anything whose defect is created by the parser or by JavaScript cannot be verified with curl.** That covers V-1 here, the facet checkbox upgrade, and the soft-limit toggle's `aria-expanded` — three findings in this workstream where the source and the DOM disagree.

### Observing the changes that have no visible surface

Only **V-3** changes anything a sighted user can see, and only slightly — chef names become `<h2>`, so they inherit heading type scale. V-1, V-2 and V-4 are invisible by design: the page will look identical before and after. That is the expected result, not a sign the change failed, so each needs a way to observe what actually moved.

| # | Visible? | How to observe it |
|---|---|---|
| V-1 | No | Tab through `/chefs`. Before: focus lands on ten invisible zero-width stops between names. After: one stop per chef |
| V-2 | No | DevTools → Elements → select the `<table>` → **Accessibility** pane → **Name** should read your caption text. Before: empty |
| V-3 | Slightly | Chef names render at heading scale. Also DevTools → full-page accessibility tree, or the HeadingsMap extension |
| V-4 | No | The pager heading is `.visually-hidden` — it never appears on screen in either state |

**Accessibility tree checks**, the cheapest evidence:

- `/chefs` — click a chef link. **Name** must be the chef's name and **not** empty. Before the fix, every second link in the tree has an empty name.
- `/reports/by-cuisine` — click the `<table>`. **Name** must be the caption. Before the fix there is no name at all.

**VoiceOver + Safari**, if you want the recording for the test log:

- `VO+U` → **Links** on `/chefs`. Before: twenty entries, ten of them announcing as bare "link" with no text. After: ten, each announcing the chef's name. This is the clearest 30-second clip in the whole workstream — the rotor list itself shows the duplication without any narration needed.
- `VO+U` → **Headings** on `/chefs`. Before: "Chefs" then "Pagination". After: "Chefs", then ten chef names, then "Pagination".
- `VO+Command+T` (next table) on `/reports/by-cuisine`. Before: "table, 3 columns, 11 rows" with no name. After: it leads with the caption.

Log what was **announced** separately from what is **on screen** — for V-1 and V-2 the on-screen column reads "no visible change", and that contrast is exactly what makes the finding legible to a client.

---

## Deviations log

| # | What changed from the plan as written | Why |
|---|---|---|
| 1 | The section's headline finding (V-1) was not in the Notion checklist at all, while three bullets that were in it are already closed | The checklist was written from template and config review. V-1 only shows up when you count rendered links — the source looks like one link per chef, and it is the HTML parser that turns it into two |
| 3 | Step 4's verification command was wrong and reported `0` on a page that was already fixed | It grepped for `<h2[^>]*views-field`, but Views puts the field wrapper class on the parent `<div>` and gives the element itself `field-content`. The heading outline was what actually proved the fix. Same failure mode as the V-1 check: a check written against the wrong layer cannot fail, so it cannot pass either |
| 4 | Phase 3 (V-4) needed three passes, not one | First attempt: neither view changed. Second: `chefs` took it, `recipes_per_cuisine` did not. Third: both. The cause each time is the Views UI's two-step save — *Apply* closes the modal and changes nothing until the blue **Save** is pressed — and with two views to edit it is easy to save one and navigate away from the other. Worth writing into any future Views plan as a numbered step of its own per view, rather than a clause at the end of another step |
| 2 | A fifth finding was investigated and **withdrawn**: `/reports/by-cuisine` appeared to have a `<th>` missing `scope` | The regex `<th[^>]*>` also matches `<thead>`. All three real header cells carry `scope="col"`, set unconditionally by the theme's `views-view-table.html.twig`. Third harness false positive in this workstream, after the `rawKeyDown` skip link and the synchronous `aria-expanded` read — all three caught by re-checking a result before reporting it |
