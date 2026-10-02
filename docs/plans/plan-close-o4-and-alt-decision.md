# Plan — close O-4 and the alt-text decision

**Goal:** take the audit's Open table to zero, honestly.

Two items, both small. Neither is the work the original findings asked for, and
that is the interesting part of both.

**Standing rule for this plan: nothing is committed until you say so.**

---

## Before you start

```bash
git checkout master && git pull
git checkout -b fix/a11y-close-o4-and-alt
```

You are currently on `ci/a11y-gates`, which is merged. If
`docs/actual-outcomes/accessibility-audit.md` still shows as modified there
(the "Stale" rewrite), carry it across first:

```bash
git stash          # on ci/a11y-gates
git checkout master && git pull
git checkout -b fix/a11y-close-o4-and-alt
git stash pop
```

---

## Phase 1 — O-4: the finding was wrong, not the code

### What the finding said

> `button.twig` is unconditionally `<a href>`. Fix: an `as` / `element` prop
> switching between `<a>` and `<button>`, with `url` required only for the link
> variant.

### What is actually true

Checked on 16 September:

| Claim in the finding | Reality |
|---|---|
| The atom is always a link | True of `button`, but `components/action-button/` exists and emits `<button type="button">` |
| Actions are rendered as links | False. All six action call sites in `recipe-tools.twig` already use `action-button` |
| No disabled state | False. `js/recipe-tools.js:84,87` sets `prev.disabled` / `next.disabled` |
| No way to express `aria-expanded` | False. `js/facet-soft-limit.js:22` sets it on the facet show-more toggle |
| `tag-pill` is a plain `<span>` | Still true, still correct |

Both remaining `flavourful:button` call sites are navigation and pass a real
`url` behind an `{% if %}`:

- `templates/layout/page.html.twig:222` — "Browse all recipes", guarded by `{% if recipes_url %}`
- `components/section-header/section-header.twig:22` — guarded by `{% if url and link_label %}`

**So the proposed fix would have been a regression.** An `as` prop would merge
two atoms that were deliberately separated, and the separation is the better
design — it is what "use the right element, do not parameterise the element"
means in practice. `action-button.component.yml` even declares the CSS
dependency so the two cannot drift apart visually.

### The one real residue

`button.twig:8` still reads:

```twig
  href="{{ url|default('#') }}"
```

Both current callers guard against a missing `url`, so this never fires today.
But `url` is **not** in the component's `required` list, so a future caller can
omit it and get `<a href="#">` — a link to the current page, announced as a
link. That is the genuine, latent part of the finding.

### Step 1.1 — make `url` required

**File:** `docroot/themes/custom/flavourful/components/button/button.component.yml`

Change:

```yaml
  required:
    - label
```

to:

```yaml
  required:
    - label
    - url
```

And in the same file, remove the fallback so a missing `url` fails loudly
rather than silently rendering `#`. Change:

```yaml
    url:
      type: string
      title: URL
      description: 'Destination of the link.'
      default: '#'
```

to:

```yaml
    url:
      type: string
      title: URL
      description: >-
        Destination of the link. Required and with no default: a button atom
        with no destination is an <a href="#">, which announces as a link to
        the current page. If you have no destination, you want action-button.
```

### Step 1.2 — drop the `#` default from the template

**File:** `docroot/themes/custom/flavourful/components/button/button.twig`

Change line 8:

```twig
  href="{{ url|default('#') }}"
```

to:

```twig
  href="{{ url }}"
```

### Step 1.3 — record why this atom stays a link

**File:** `docroot/themes/custom/flavourful/components/button/button.twig`

Replace the docblock at the top (lines 1–6):

```twig
{#
/**
 * @file
 * Button atom. See button.component.yml for the props contract.
 */
#}
```

with:

```twig
{#
/**
 * @file
 * Button atom. See button.component.yml for the props contract.
 *
 * This deliberately has no `as` / `element` prop. An audit finding proposed one,
 * switching between <a> and <button> — but the right answer to "sometimes I
 * need an action" is the action-button atom, not a parameterised element.
 * Splitting them keeps ARIA's first rule enforceable at the component boundary:
 * a caller cannot accidentally render an action as a link. `url` is required
 * for the same reason.
 */
#}
```

### Verification for Phase 1

```bash
cd docroot/themes/custom/flavourful
grep -n "url" components/button/button.twig          # expect href="{{ url }}", no default
grep -n -A2 "required:" components/button/button.component.yml   # expect label AND url
```

Then confirm nothing broke — both call sites pass `url`, so both should render:

```bash
cd /Users/quraish/Desktop/WORK/AXELERANT/repos/foodrecipes-drupal
ddev drush cr
curl -s https://foodrecipes-drupal.ddev.site/ | grep -c 'class="btn'
```

Expect a non-zero count and no PHP error in `ddev logs`. If SDC throws
`Missing required prop`, a call site you have not seen is omitting `url` — find
it with `grep -rn "flavourful:button" docroot/themes/custom/flavourful` and
report back rather than re-adding the default.

---

## Phase 2 — the alt-text decision

### What the finding asked

> Check whether the image inside `{% block media %}` carries meaningful alt text
> that is also being discarded — decide deliberately whether it is decorative.

### The facts to decide from

- `config/sync/field.field.node.recipe.field_hero.yml` sets
  **`alt_field_required: true`** — editors must supply alt. The data exists.
- In `recipe-card.twig`, `{% block media %}` renders **inside** the
  `aria-hidden="true"` media link, so that alt is discarded in the a11y tree.
- The card's title link already names the recipe.
- In the `list` variant the media link is not rendered at all, so there is no
  image and no question.
- The full recipe page is owned by Site Studio, so the teaser is the surface
  that this template actually controls.

### The decision

**Decorative in this context, deliberately.** The alt is required at the field
level (correct — it is meaningful in other contexts), and suppressed here
because the title link already supplies the name. Surfacing it would announce
the dish twice per card.

This is a 1.1.1 judgement, not a defect. It belongs in the audit's *Criteria
assessed and found not to apply* section, not in the Fixed table.

### Step 2.1 — write the decision into the template

**File:** `docroot/themes/custom/flavourful/components/recipe-card/recipe-card.twig`

Find the existing comment block above the media link (it currently ends
`...it now renders in the body and is positioned over the media by CSS.`) and
append a paragraph inside the same `{# ... #}` block:

```
    The hero image inside this link is decorative *here* by decision, not by
    oversight. field_hero sets alt_field_required: true, so the alt exists and
    is meaningful elsewhere — but the title link below already names the recipe,
    so announcing it again would double every card. If this image is ever moved
    outside the aria-hidden link, that decision has to be revisited: the alt
    becomes live, and a decorative-looking photo starts speaking.
```

### Step 2.2 — record it in the audit

**File:** `docs/actual-outcomes/accessibility-audit.md`

In the section **"Criteria assessed and found not to apply"**, whose columns are
`| Criterion | Where | Why it does not apply |`, add this row at the end of the
table:

```
| **1.1.1 Non-text Content (A)** | Recipe card hero image | The image renders inside the `aria-hidden` duplicate media link, so its alt is not exposed. Assessed as **decorative in this context, by decision**: `field_hero` sets `alt_field_required: true`, so the alt exists and is meaningful elsewhere — but the card's title link already names the recipe, and announcing it twice per card is worse than not announcing it. Revisit if the image ever renders outside that link |
```

Then bump the **At a glance** count for
`Criteria assessed and found **not** to apply` from **5** to **6**.

---

## Phase 3 — close O-4 in the audit

**File:** `docs/actual-outcomes/accessibility-audit.md`

Three edits:

1. **At a glance** — in that table, change the row

   ```
   | Open, scoped | 1 |
   ```

   to

   ```
   | Withdrawn after re-checking (see O-4) | 1 |
   ```

   Leave `Defects found | **25**` and `Fixed and retested | **24**` alone. Do
   **not** write "25 fixed": O-4 was not fixed, it was found not to exist.
   Those are different claims, and the difference is the credibility of the
   whole document.

2. **The "Open — the one" section** — retitle to `Withdrawn — the one`. The
   existing row already concedes *"today's usages are all navigational, so
   nothing currently announces the wrong role"*, which was the right instinct;
   it just stopped one directory short. Replace the paragraph below the table
   (`Left last deliberately: …`) with:

   > **Withdrawn on 16 September, not fixed.** The finding assumed the `button`
   > atom was the only button-like component. `components/action-button/` already
   > emits a real `<button type="button">`, all six action call sites use it, and
   > both `button` call sites are navigation. The proposed `as` / `element` prop
   > would have merged two atoms that were deliberately separated. One real
   > residue was fixed instead: `url` is now a required prop with no `'#'`
   > fallback, so a future caller cannot silently render `<a href="#">`.

3. **The "Three findings the redesign closed first" section** — this is now a
   fourth, and it failed differently again. Add a line after that section's
   table:

   > **A fourth expired while this document was being written, in a third way.**
   > O-4 was neither wrong-when-measured nor overtaken by a redesign: it was
   > **incomplete**, written from one component without checking whether a
   > sibling already solved it. Category A findings came from reading config
   > without loading the page; this one came from reading one file without
   > reading the directory.

---

## Phase 4 — Notion

Page: **Phase 1 — Accessibility to Audit Level**

1. **Finding 6** — untick the `as` / `element` box and rewrite it as withdrawn,
   citing `action-button`. Leave the `tag-pill` bullet as prose (still correct).
2. **Finding 1** — tick the alt-text box, citing the decision and
   `alt_field_required: true`.
3. **Systematic audit surface** — the `field/` and `content/` box can now be
   ticked: heading levels were already settled, and image alt handling is the
   decision above.
4. **Deliverables — sharpened** — `element` / `as` prop on `button` becomes
   struck through with "withdrawn — see Finding 6".

Tell me when Phases 1–3 are done and I will make the Notion edits.

---

## Verification, all phases

```bash
cd /Users/quraish/Desktop/WORK/AXELERANT/repos/foodrecipes-drupal
ddev drush cr
python3 scripts/a11y-sweep.py        # expect: 9 routes, 0 flagged
python3 scripts/contrast.py          # expect: 19 pairs, 0 failing
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice docroot/themes/custom
git diff --stat
```

Expected files touched: `button.twig`, `button.component.yml`,
`recipe-card.twig`, `accessibility-audit.md`. No CSS rebuild is needed — no
SCSS changes here — so the CI CSS-freshness gate will be a no-op.

**Then stop.** Do not commit until you have decided the work is right.

---

## Deviation log

Record anything that did not match this plan. Pre-populated with what is
already known:

1. **O-4's proposed fix was wrong and this plan does not implement it.** The
   `as` / `element` prop would have been a regression. Discovered 16 September
   by checking `components/` for siblings before writing the steps — which is
   exactly the check the original finding skipped.
2.
