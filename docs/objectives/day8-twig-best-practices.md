# Flavorful — Day 8 Lab: Twig Templating Best Practices

> Companion to the [advanced curriculum](advanced-plan-days6-9.md). Anyone can edit a `.twig` file; today is about writing **clean, DRY, reusable** Twig — knowing *when* to use `include` vs `embed` vs `extends`, and reaching for a `macro` for repeated elements. This is the exact skill behind the JD's "develop Twig templates working from front-end assets."
>
> Builds on the `flavourful` theme (in your repo: `docroot/themes/custom/flavourful/`).

> 📁 **Exact file locations & the Twig namespace — read once.** Every template below lives under your theme's `templates/` folder, i.e. `docroot/themes/custom/flavourful/templates/…`. When one template references another, Drupal uses the **Twig namespace `@flavourful`**, which maps to that `templates/` directory. So `@flavourful/partials/recipe-card.html.twig` = the file `docroot/themes/custom/flavourful/templates/partials/recipe-card.html.twig`.
>
> ⚠️ **This is a real bug currently in your code:** your `recipe-card.html.twig` and `node--recipe.html.twig` reference `flavorful_theme/…` / `flavourful_theme/…` (no `@`, wrong name) — neither resolves, so the include/embed/import throw "template not found". Every snippet below uses the correct `@flavourful/…` form. (See the "Fix your current files" box at the end.)

**Build target:** a small, reusable template kit in your theme — a **base card** others `extends`/`embed`, a **recipe-card partial** you `include`, and a **macro file** of UI atoms (button, tag pill) — with the Day-2 `node--recipe.html.twig` refactored to use them, so there's zero duplicated markup.

---

## 0. The four tools, in one table (know this cold)

**Why:** the single most common Twig interview question is *"what's the difference between include, extends and embed?"* Here's the whole answer:

| Construct | What it does | Reach for it when |
|---|---|---|
| `{% include %}` | Drops another template's rendered output in place | You want to reuse a self-contained partial as-is (a card, a byline) |
| `{% extends %}` + `{% block %}` | Child template inherits a parent and **overrides named blocks** | You have a base layout and variations that fill in the gaps |
| `{% embed %}` | `include` **+** the ability to override the included template's blocks inline | You want a reusable partial but need to tweak one region *at the call site* |
| `{% macro %}` | A reusable, parameterised snippet (like a function returning markup) | Small repeated elements — buttons, pills, icons |

Golden line: **include = reuse whole · extends = inherit & override · embed = include + override · macro = a markup function.**

We'll build one of each, against Flavorful.

> Create `docroot/themes/custom/flavourful/templates/partials/` and `.../templates/macros/` folders to keep these organised (you already have them).

---

## 1. Inheritance — a base card with `extends` + `block`

**Why:** inheritance is the backbone of DRY theming. Define the *shape* once; let variations fill in the blanks.

📄 **File to edit:** `docroot/themes/custom/flavourful/templates/partials/base-card.html.twig`

```twig
{#
/**
 * @file
 * Base card skeleton. Children override the blocks.
 */
#}
<article{{ attributes.addClass('card') }}>
  <div class="card__media">{% block media %}{% endblock %}</div>
  <div class="card__body">
    <h3 class="card__title">{% block title %}{% endblock %}</h3>
    <div class="card__meta">{% block meta %}{% endblock %}</div>
    <div class="card__tags">{% block tags %}{% endblock %}</div>
  </div>
</article>
```

The `{% block %}` tags are empty slots. On their own they render an empty card; children give them content (next section).

> **Why blocks matter:** a child template can override *just* the parts it cares about and inherit the rest. Change the card's wrapper once here and every card updates.

---

## 2. `embed` — a recipe card that fills the base card's blocks

**Why:** `embed` = `include` the base card **and** override its blocks right at the call site. Perfect when you have a shared skeleton but per-use content.

📄 **File to edit:** `docroot/themes/custom/flavourful/templates/partials/recipe-card.html.twig`

```twig
{#
/**
 * @file
 * A recipe card. Expects: recipe (node).
 */
#}
{% embed '@flavourful/partials/base-card.html.twig' with { attributes: create_attribute().addClass('card--recipe') } only %}
  {% block media %}
    {{ recipe.field_hero_image|view }}
  {% endblock %}
  {% block title %}
    <a href="{{ path('entity.node.canonical', { node: recipe.id }) }}">{{ recipe.label }}</a>
  {% endblock %}
  {% block meta %}
    {{ recipe.field_total_time.value }} min · {{ recipe.field_difficulty.value }}
  {% endblock %}
{% endembed %}
```

Note `with { … } only`: **`with`** passes data in; **`only`** isolates scope so the partial can't accidentally read outer variables — a best practice for predictable, reusable partials.

> **embed vs include (say this):** "I use `include` when a partial is complete as-is, and `embed` when I want that partial's structure but need to override a block or two at the call site — `embed` is `include` plus block overrides."

---

## 3. `macro` — UI atoms (button, tag pill)

**Why:** for tiny, repeated elements, a macro is cleaner than a template file — it's a parameterised markup function you call inline.

📄 **File to edit:** `docroot/themes/custom/flavourful/templates/macros/ui.html.twig`

```twig
{% macro button(text, url, variant = 'primary') %}
  <a href="{{ url }}" class="btn btn--{{ variant }}">{{ text }}</a>
{% endmacro %}

{% macro tag_pill(label) %}
  <span class="pill">{{ label }}</span>
{% endmacro %}
```

Use them (import once, then call):

```twig
{% import '@flavourful/macros/ui.html.twig' as ui %}

{{ ui.button('View recipe', path('entity.node.canonical', { node: recipe.id })) }}
{{ ui.button('Save', '#', 'ghost') }}
{{ ui.tag_pill('Vegan') }}
```

> **When a macro vs a partial:** macro for small, logic-light, highly-repeated bits (buttons, icons, pills); a `.twig` partial (include/embed) when there's real structure or fields involved. Macros are the natural home for **atoms** — which is exactly where Day 9 (SDC) picks up.

---

## 4. `include` — pull the card into a listing

**Why:** now reuse the recipe card wherever you list recipes, with zero duplication.

📄 **File to edit:** a Views row template — for the recipes listing that's `docroot/themes/custom/flavourful/templates/views/views-view-unformatted--recipes.html.twig` (you already have `views-view--recipes.html.twig` / `views-view-grid--recipes.html.twig` in that folder — use whichever matches your view's row style). Example loop:

```twig
<div class="recipe-grid">
  {% for item in recipes %}
    {% include '@flavourful/partials/recipe-card.html.twig' with { recipe: item } only %}
  {% endfor %}
</div>
```

One card definition, used on the node page, the listing, the "related" block — change it once, everywhere updates.

---

## 5. Refactor `node--recipe.html.twig` to use the kit

**Why:** prove the payoff — the Day-2 template shrinks to composition.

📄 **File to edit:** `docroot/themes/custom/flavourful/templates/content/node--recipe.html.twig`

```twig
{#
/**
 * @file
 * Recipe node — now composed from the card + macros.
 */
#}
{% import '@flavourful/macros/ui.html.twig' as ui %}

<div{{ attributes.addClass('recipe-full') }}>
  {% include '@flavourful/partials/recipe-card.html.twig' with { recipe: node } only %}

  <div class="recipe-full__tags">
    {% for tag in node.field_dietary %}
      {{ ui.tag_pill(tag.entity.label) }}
    {% endfor %}
  </div>

  <div class="recipe-full__method">
    <h2>{{ 'Method'|t }}</h2>
    {{ content.field_steps }}
  </div>

  {{ ui.button('Print recipe', '#', 'ghost') }}
</div>
```

> 🔎 **Test it (user-facing UI):** `drush cr`, then open a recipe page. It should render the same as before the refactor — the card, the tag pills, the buttons — just from far less markup. In your browser DevTools, confirm the `.card--recipe`, `.pill`, and `.btn` classes are present. With **Twig debug** on, View Source will show the `recipe-card.html.twig` / `base-card.html.twig` partials being loaded (proof the include/embed chain resolved). If it's unstyled or blank, check the template path in the `include`/`embed` matches your theme name.

---

## 6. The Drupal-specific Twig you must know

**Why:** these come up constantly and mark you as a *Drupal* themer, not just someone who knows Twig.

- **The `attributes` object:** `{{ attributes.addClass('x').setAttribute('data-y', z) }}` — renders and escapes safely. Build new ones with `create_attribute()`.
- **`without()`:** `{{ content|without('field_steps') }}` renders everything *except* a field (e.g. print the rest, then place one field manually).
- **Translation:** `{{ 'Save'|t }}` or `{% trans %}…{% endtrans %}` — always wrap UI strings.
- **`attach_library`:** `{{ attach_library('flavourful/recipe-card') }}` to load a component's CSS/JS from the template. ⚠️ Library names are **not** template namespaces: use `themename/library` — **no** `@` and **no** `templates/` (the library is declared in `flavourful.libraries.yml`).
- **Helpers:** `clean_class`, `clean_id`, `{{ link(text, url) }}`, `{{ path('route', {...}) }}`, `|default('…')`, `|merge({...})`.
- **`|raw` is a last resort:** Twig autoescapes by default (a security feature). Only use `|raw` on markup you fully trust; prefer rendering through the field/entity pipeline.

---

## 7. Whitespace & readability

- Trim whitespace with `{%- … -%}` when stray spaces matter (inline elements).
- Comment with `{# … #}` (these never reach the browser).
- Keep templates scannable — if a template has heavy logic, that logic probably belongs in a **preprocess hook** (Day 6), not the template.

> **Separation-of-concerns line:** "I keep logic in preprocess and keep Twig about presentation — templates should read like markup, not code."

---

## 🛠 Fix your current files (checked against your repo)

Your partials exist, but their cross-references use the wrong Twig namespace, so the includes fail. Make these exact edits:

1. **`docroot/themes/custom/flavourful/templates/partials/recipe-card.html.twig`**
   - Change `{% embed 'flavorful_theme/partials/base-card.html.twig' … %}`
   - → `{% embed '@flavourful/partials/base-card.html.twig' … %}`

2. **`docroot/themes/custom/flavourful/templates/content/node--recipe.html.twig`**
   - Change `{% import 'flavourful_theme/macros/ui.html.twig' as ui %}` → `{% import '@flavourful/macros/ui.html.twig' as ui %}`
   - Change `{% include 'flavorful_theme/partials/recipe-card.html.twig' … %}` → `{% include '@flavourful/partials/recipe-card.html.twig' … %}`

3. **Rename the theme file** `docroot/themes/custom/flavourful/flavourful_theme.theme` → **`flavourful.theme`**. Drupal only auto-loads `{machine-name}.theme`, and your machine name is `flavourful` — so the current file (and any preprocess in it) is being ignored. *(Your `is_quick` badge still works because that preprocess lives in the `flavourful_nutrition` **module**, not the theme — but rename the file so the theme's own preprocess isn't dead.)*

After editing: `drush cr`, then open a recipe — the card should now render (no "template not found" error). The rule to remember: **`@flavourful` = the theme's `templates/` folder.**

> Want me to apply edits 1–3 to your repo directly? Say the word.

---

## 8. End-of-day verification (say these out loud)

1. `include` vs `extends` vs `embed` vs `macro` — the one-line differences and when you pick each.
2. What `with` and `only` do on an include/embed and why `only` is good practice.
3. When a macro beats a partial (atoms).
4. The `attributes` object and the *right* way to add classes/attributes.
5. Why `|raw` is dangerous and what you do instead.
6. Where logic belongs (preprocess) vs presentation (Twig).

## Interview Q&A

| Question | Answer shape |
|---|---|
| "include vs extends vs embed?" | include = reuse whole; extends+block = inherit & override; embed = include + override blocks inline. |
| "When a Twig macro?" | Small, repeated, logic-light markup — buttons, pills, icons (atoms). |
| "Add a class/library to a template the Drupal way?" | `attributes.addClass()` / `create_attribute()`; `attach_library()` for assets. |
| "Print everything except one field?" | `{{ content|without('field_x') }}`, then place `field_x` manually. |
| "Where should template logic live?" | In a preprocess hook, not the Twig — keep templates presentational. |

---

### Sources

- [Twig in Drupal — theming (Drupal.org)](https://www.drupal.org/docs/theming-drupal/twig-in-drupal)
- [Twig `embed` / `include` / `macro` (Twig docs)](https://twig.symfony.com/doc/3.x/tags/embed.html)
