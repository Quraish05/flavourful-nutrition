# Flavourful — Day 12 Lab: JavaScript in Drupal (behaviors, once(), AJAX & a React island)

> Companion to the [advanced curriculum](advanced-plan-days6-9.md). Bank §5 (Frontend/JS) is your strong suit — this day makes it **Drupal-specific** so you answer "how does JS work *in Drupal*?" with real code, and covers the "React inside Site Studio" question from §2.
>
> Uses your real project: theme `flavourful` at `docroot/themes/custom/flavourful/`.

**Build target:** a JS behavior that enhances recipe cards (using `Drupal.behaviors` + `once()`), a small AJAX interaction, `drupalSettings` passing data PHP→JS, and a mounted **React island** — all wired the Drupal way.

---

## 0. How Drupal loads JavaScript (the part interviewers check)

**🧠 In plain terms:** you don't drop `<script>` tags. You declare a **library** and attach it.

1. Define the library in `flavourful.libraries.yml`:

📄 **File:** `docroot/themes/custom/flavourful/flavourful.libraries.yml`

```yaml
recipe-interactions:
  version: 1.x
  js:
    js/recipe-interactions.js: {}
  dependencies:
    - core/drupal          # gives you Drupal.behaviors
    - core/once            # gives you once()
    - core/drupalSettings  # gives you drupalSettings
```

2. Attach it — globally from the theme (`flavourful.info.yml` → `libraries:`), per-template with `{{ attach_library('flavourful/recipe-interactions') }}`, or in a render array with `#attached`.

> 📖 **Glossary:** *library* = a named bundle of JS/CSS + dependencies. *`core/drupal`* = the JS that provides `Drupal.behaviors`. *`core/once`* = the modern replacement for the old `jQuery.once`.

---

## 1. `Drupal.behaviors` + `once()` — the core pattern

**🧠 In plain terms:** don't use `$(document).ready()`. Drupal re-runs behaviors on content added later (via AJAX), and `once()` stops you from binding the same element twice.

📄 **File:** `docroot/themes/custom/flavourful/js/recipe-interactions.js`

```js
((Drupal, once) => {
  Drupal.behaviors.recipeInteractions = {
    attach(context) {
      // once() returns only elements not yet processed in this context.
      once('recipe-toggle', '.recipe-card', context).forEach((card) => {
        const btn = card.querySelector('.recipe-card__toggle');
        if (!btn) return;
        btn.addEventListener('click', () => {
          card.classList.toggle('is-expanded');
        });
      });
    },
  };
})(Drupal, once);
```

**Why each piece matters (say this):**

- **`Drupal.behaviors.<name>.attach(context)`** runs on initial page load **and** every time Drupal injects new markup (AJAX, BigPipe) — `context` is the new chunk, so you don't re-scan the whole page.
- **`once('id', selector, context)`** guarantees each element is initialised exactly once — the fix for "my handler fired twice."
- Contrast with `$(document).ready()`: that runs once on load and never sees AJAX-added content → the classic bug behaviors solve.

> 🔎 **Test it:** `drush cr`, add a `.recipe-card__toggle` button to your recipe card (Day 8), load a recipe, click it → the card toggles. Then confirm it still works on AJAX-loaded cards (step 3).

---

## 2. `drupalSettings` — pass PHP data to JS

**🧠 In plain terms:** the bridge from server to browser.

From PHP (a block/controller/preprocess), attach settings:

```php
$build['#attached']['library'][] = 'flavourful/recipe-interactions';
$build['#attached']['drupalSettings']['flavourful']['apiBase'] = '/api/recipes';
```

In JS, read them:

```js
const base = drupalSettings.flavourful.apiBase;  // '/api/recipes'
```

> **Why not hard-code the URL in JS?** Different environments/paths; `drupalSettings` keeps config server-side and testable.

---

## 3. The AJAX API — two ways

**🧠 In plain terms:** Drupal has a built-in AJAX framework where the **server returns commands** (not just data). You can also do plain `fetch`.

**Way A — Drupal's `#ajax` (Form/Render API).** Add `#ajax` to a form element; a callback returns an `AjaxResponse` of commands:

```php
// In a form: reload a "results" area when a select changes.
$form['cuisine'] = [
  '#type' => 'select',
  '#options' => $options,
  '#ajax' => [
    'callback' => '::updateResults',   // returns the #results element or commands
    'wrapper' => 'recipe-results',
  ],
];
$form['results'] = ['#type' => 'container', '#attributes' => ['id' => 'recipe-results']];
```

```php
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

public function updateResults(array &$form, FormStateInterface $fs): AjaxResponse {
  $response = new AjaxResponse();
  $response->addCommand(new ReplaceCommand('#recipe-results', $this->buildResults($fs)));
  return $response;
}
```

**Way B — plain `fetch` in a behavior** (for a decoupled widget hitting your Day-10 REST/JSON:API endpoint):

```js
const res = await fetch(drupalSettings.flavourful.apiBase + '?_format=json');
const recipes = await res.json();
```

**When to use which:** `#ajax` when Drupal owns the markup (forms, Views exposed filters already use it); `fetch` + JSON:API/REST when a JS/React component owns the UI.

> 🔎 **Test it:** the built-in exposed-filter AJAX on your Day-7 search view is `#ajax` in action — enable "Use AJAX" on the view and watch results update without a full reload.

---

## 4. A React island (ties to the Site Studio §2 question)

**🧠 In plain terms:** Drupal renders the page and a **mount point**; React takes over just that node.

1. Render a mount point (from a block, template, or Site Studio custom element):

```html
<div id="recipe-finder" data-api="/api/recipes"></div>
```

2. Attach a library whose JS boots React into it:

📄 **File:** `docroot/themes/custom/flavourful/js/recipe-finder.js`

```js
((Drupal, once) => {
  Drupal.behaviors.recipeFinder = {
    attach(context) {
      once('recipe-finder', '#recipe-finder', context).forEach((el) => {
        const root = ReactDOM.createRoot(el);
        root.render(React.createElement(RecipeFinder, { api: el.dataset.api }));
      });
    },
  };
})(Drupal, once);
```

3. `RecipeFinder` fetches from `el.dataset.api` (your Day-10 REST export) and renders results — a self-contained interactive island inside a Drupal/Site Studio page.

**Say this (the Site Studio hybrid answer):** "Site Studio (or a custom element/block) owns the page and renders a mount point with a data attribute; I attach a Drupal library that boots a React app into that node, and it pulls data from JSON:API/REST. Structure stays editor-controlled; the interactive part is a proper React component."

> 🙋 **Honesty:** "React is working knowledge for me — I can build and mount components like this; I'd go deeper on state management for a large app."

---

## 5. End-of-day verification (say these out loud)

1. Why you use **`Drupal.behaviors`** not `$(document).ready()` (AJAX-added content).
2. What **`once()`** solves (double-binding) and its signature.
3. How JS is delivered in Drupal (**libraries** + attach) and how **`drupalSettings`** passes data.
4. The two AJAX approaches (**`#ajax`** command-based vs **`fetch`** + JSON:API) and when to use each.
5. How you'd embed **React** in a Drupal/Site Studio page (mount point + library + endpoint).

## Interview Q&A

| Question | Answer shape |
|---|---|
| "How does JS work in Drupal?" | Declare a library (JS/CSS + deps), attach it; run code in `Drupal.behaviors`, not `ready()`. |
| "Why behaviors + once()?" | Behaviors re-run on AJAX-added content; `once()` prevents binding the same element twice. |
| "Pass PHP data to JS?" | `#attached['drupalSettings']` → read `drupalSettings.…` in JS. |
| "Drupal AJAX vs fetch?" | `#ajax` returns server commands (great for forms/Views); `fetch`+JSON:API for JS/React-owned UI. |
| "React inside Site Studio?" | Mount point + data attribute rendered by Site Studio; a library boots React into it; data via JSON:API. |

---

### Sources

- [JavaScript API / behaviors (Drupal.org)](https://www.drupal.org/docs/drupal-apis/javascript-api/javascript-api-overview)
- [once() library (Drupal.org)](https://www.drupal.org/docs/drupal-apis/javascript-api/javascript-api-overview#s-behaviors)
- [AJAX API (Drupal.org)](https://www.drupal.org/docs/drupal-apis/ajax-api/core-ajax-callback-commands)
