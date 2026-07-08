# Flavorful — Day 9 Lab: Atomic Design with Single Directory Components (SDC)

> Companion to the [advanced curriculum](advanced-plan-days6-9.md). This is where your frontend background becomes a Drupal superpower. **Single Directory Components (SDC)** are the modern, core-standard way to build reusable UI in Drupal (stable in core since 10.3) — and they map cleanly onto the JD workflow: the front-end developer hands you assets, you package them as components.
>
> Builds on the Twig kit from [Day 8](day8-twig-best-practices.md); the components live in your `flavorful_theme`.

**Build target:** a small component library organised by atomic-design level — two **atoms** (`button`, `tag-pill`), one **molecule** (`recipe-card` with props *and* a slot) — rendered from both a node template and a Views listing, plus a written **SDC vs Site Studio vs Paragraphs** comparison.

---

## 1. Atomic design in 60 seconds (the vocabulary)

**Why:** it gives you and the front-end dev a shared language, and it's the mental model behind component libraries.

- **Atoms** — smallest UI pieces: button, input, tag/pill, icon.
- **Molecules** — a few atoms combined into a unit: a card (image + title + tags + button).
- **Organisms** — molecules composed into a section: a recipe grid, a header.
- **Templates / Pages** — layouts and real content.

The point: build small, test small, compose upward. Change the button atom once and every card, every page updates.

> **Interview line:** "I think in atoms, molecules and organisms — small components composed upward — and in Drupal I build them as Single Directory Components so the markup, styles, schema and JS live together and are reusable across templates, Views, and Layout Builder."

---

## 2. What a Single Directory Component is (anatomy)

**Why:** SDC is *the* current answer to "how do you build reusable components in Drupal?" Know the file layout cold.

A component is a folder under `components/` in a theme or module. Minimum: a `*.component.yml` (metadata) and a `*.twig` (markup); optional co-located `*.css` / `*.js`. All files share the same **kebab-case** base name.

```
flavorful_theme/
└── components/
    ├── button/
    │   ├── button.component.yml
    │   ├── button.twig
    │   └── button.css
    ├── tag-pill/
    │   ├── tag-pill.component.yml
    │   └── tag-pill.twig
    └── recipe-card/
        ├── recipe-card.component.yml
        ├── recipe-card.twig
        └── recipe-card.css
```

Two kinds of input — **know the difference, it's a common SDC question:**

- **Props** = strictly-typed data (strings, numbers, enums) validated by a JSON-Schema in the `.yml`. Use for logic/values: a button's `label`, `variant`.
- **Slots** = free-form render areas that can hold anything (text, HTML, other components). Use for content you don't want to type-constrain: a card's tag area.

---

## 3. Atom — the `button` component

**Why:** the simplest possible SDC — all props, no slots — so the mechanics are clear.

`components/button/button.component.yml`:

```yaml
$schema: 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json'
name: Button
status: stable
props:
  type: object
  required:
    - label
  properties:
    label:
      type: string
      title: Label
    url:
      type: string
      title: URL
      default: '#'
    variant:
      type: string
      title: Variant
      enum: [primary, ghost]
      default: primary
```

`components/button/button.twig`:

```twig
<a href="{{ url }}" class="btn btn--{{ variant }}"{{ attributes }}>{{ label }}</a>
```

`components/button/button.css`:

```css
.btn { display:inline-block; padding:.5rem 1rem; border-radius:.375rem; text-decoration:none; }
.btn--primary { background:#b3541e; color:#fff; }
.btn--ghost { background:transparent; border:1px solid #b3541e; color:#b3541e; }
```

`drush cr`, then render it anywhere in Twig:

```twig
{{ include('flavorful_theme:button', { label: 'View recipe', url: '/recipes/x', variant: 'primary' }) }}
```

> **Why the schema is worth it:** the `props` schema validates inputs and powers IDE autocomplete. `variant` is an `enum`, so passing `variant: 'huge'` errors loudly instead of silently rendering a broken class. That validation is a real advantage over a plain Twig include.

---

## 4. Atom — the `tag-pill` component

`components/tag-pill/tag-pill.component.yml`:

```yaml
$schema: 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json'
name: Tag pill
status: stable
props:
  type: object
  required: [label]
  properties:
    label:
      type: string
      title: Label
```

`components/tag-pill/tag-pill.twig`:

```twig
<span class="pill"{{ attributes }}>{{ label }}</span>
```

Render: `{{ include('flavorful_theme:tag-pill', { label: 'Vegan' }) }}`.

---

## 5. Molecule — the `recipe-card` (props **and** a slot)

**Why:** this is the payoff — a component that takes typed props *and* a free-form slot which we fill with `tag-pill` atoms. It demonstrates composition, the heart of atomic design.

`components/recipe-card/recipe-card.component.yml`:

```yaml
$schema: 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json'
name: Recipe card
status: stable
props:
  type: object
  required: [title, url]
  properties:
    title:
      type: string
    url:
      type: string
    total_time:
      type: integer
      default: 0
    difficulty:
      type: string
      enum: [easy, medium, hard]
      default: easy
    image_url:
      type: string
slots:
  tags:
    title: Tags
    description: Dietary/cuisine pills.
```

`components/recipe-card/recipe-card.twig`:

```twig
<article class="recipe-card"{{ attributes }}>
  {% if image_url %}<img class="recipe-card__img" src="{{ image_url }}" alt="">{% endif %}
  <div class="recipe-card__body">
    <h3 class="recipe-card__title"><a href="{{ url }}">{{ title }}</a></h3>
    <p class="recipe-card__meta">{{ total_time }} min · {{ difficulty }}</p>
    <div class="recipe-card__tags">{% block tags %}{% endblock %}</div>
  </div>
</article>
```

Render it with props, and fill the **slot** with atoms via `embed`:

```twig
{% embed 'flavorful_theme:recipe-card' with {
  title: node.label,
  url: path('entity.node.canonical', { node: node.id }),
  total_time: node.field_total_time.value,
  difficulty: node.field_difficulty.value,
} %}
  {% block tags %}
    {% for tag in node.field_dietary %}
      {{ include('flavorful_theme:tag-pill', { label: tag.entity.label }) }}
    {% endfor %}
  {% endblock %}
{% endembed %}
```

> **Props vs slots, made concrete:** `title`, `total_time`, `difficulty` are **props** — typed, validated. The tags area is a **slot** — it holds *other components* (the pills), which props can't do. That's exactly the rule: props for data, slots for "anything, including components."

---

## 6. Use the components for real

**In the node template** (`node--recipe.html.twig`): replace the Day-8 include with the `recipe-card` embed above.

**In a Views listing:** the cleanest way is a **Twig field/template**. Set the Recipes view to **Fields**, add a **Global: Custom text** or use a `views-view-fields` template override, and inside it render `{{ include('flavorful_theme:recipe-card', { title: ..., url: ... }) }}` per row. (Simpler alternative: a `views-view-unformatted--recipes.html.twig` that loops rows and includes the card.)

`drush cr` and confirm the same card renders on the node page and in the listing — one component, many contexts.

---

## 7. SDC vs Site Studio vs Paragraphs vs Twig partials (the comparison they'll ask)

**Why:** you now know four ways to build UI. Being able to choose is the senior signal.

| Approach | What it is | Best for | Trade-off |
|---|---|---|---|
| **SDC** | Code-first components in core (Twig+CSS+JS+schema) | A versioned, reusable component library; decoupled-friendly | Developer-built; editors don't assemble pages with them directly |
| **Site Studio** (Day 4) | Acquia low-code visual component + style system | Editors/site-builders building pages visually, fast | Proprietary, Acquia licence, lock-in, extra deploy step |
| **Paragraphs** | Contrib structured content components (fielded entities) | Editors composing flexible body content from typed chunks | Content model can sprawl; styling still theme-driven |
| **Twig partials/macros** (Day 8) | Plain reusable templates | Quick reuse without schema/versioning overhead | No prop validation, no co-located assets, less discoverable |

> **The line that lands:** "SDC is my default for a reusable, versioned component library and it pairs well with a decoupled or design-system approach. Site Studio wins when editors need to build pages visually without a developer. Paragraphs is for editor-composed structured *content*. Plain Twig partials are fine for lightweight reuse. On this role I'd translate the front-end dev's assets into SDCs, and drop into Site Studio where the team's authoring workflow needs it."

---

## 8. End-of-day verification (say these out loud)

1. Atoms → molecules → organisms, with the button → card → grid example.
2. SDC anatomy: the folder, `*.component.yml` + `*.twig`, kebab-case, optional CSS/JS.
3. **Props vs slots** — typed data vs free-form/other-components — with the recipe-card example.
4. How you render an SDC (`include('theme:component', {props})`) and fill a slot (`embed` + block).
5. SDC vs Site Studio vs Paragraphs vs Twig partials — when each wins.
6. How this ties to the JD: you turn the front-end dev's assets into reusable components.

## Interview Q&A

| Question | Answer shape |
|---|---|
| "Build reusable UI components in modern Drupal?" | Single Directory Components — co-located Twig/CSS/JS/schema, stable in core since 10.3. |
| "Props vs slots?" | Props = typed, validated data; slots = free-form areas that can hold anything, including other components. |
| "SDC vs Site Studio?" | SDC = code-first, versioned, free; Site Studio = low-code, visual, editor-first, Acquia-licensed. |
| "Where does SDC fit with a front-end dev?" | I package their HTML/CSS/JS assets into SDCs with a clear props/slots contract. |

---

### Sources

- [Using Single-Directory Components (Drupal.org)](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components)
- [Props and slots in SDC (Drupal.org)](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/what-are-props-and-slots-in-drupal-sdc-theming)
- [Creating a single-directory component (Drupal.org)](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/creating-a-single-directory-component)
