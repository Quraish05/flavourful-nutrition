# Flavorful — Day 4 Hands-On Lab (Acquia Site Studio, click-by-click)

*Companion to the 5-day prep guide. Site Studio is your highest-risk vetting area, so this lab is deliberately concrete. You'll build a reusable **Recipe Card** component, style it, and lay out a grid of cards — mirroring the Views listing you built on Day 1 so you can compare "core Views vs. Site Studio" from real experience.*

> **Site Studio needs an Acquia licence** (API key + agency key). If you don't have one, jump to **Part 0B — the no-licence path** and follow the same build using Acquia's free docs/tutorials + screenshots. You still get the vocabulary and the story, which is what the call tests.

---

## Part 0A — Install & connect Site Studio (if you have a licence)

1. Add the packages with Composer:
   ```bash
   composer require acquia/cohesion acquia/cohesion-theme
   ```
2. Enable the modules (this is the standard set):
   ```bash
   drush pm:enable cohesion cohesion_base_styles cohesion_custom_styles \
     cohesion_elements cohesion_style_helpers cohesion_sync \
     cohesion_templates cohesion_website_settings -y
   drush cr
   ```
3. Enter your keys: **Site Studio → Configuration → Account settings** (`/admin/cohesion/account_settings`) → paste your **API key** and **Agency key** → **Save**. Site Studio validates them online.
4. **Import assets and build** (this fetches Site Studio's element/style definitions and compiles them):
   ```bash
   drush cohesion:import      # import elements/assets from Acquia
   drush cohesion:rebuild     # re-save & compile all Site Studio styles/templates
   ```
5. *(Optional but recommended)* Import a **UI Kit** — a starter library of 50+ ready components — via **Site Studio → Sync packages → Import** using the UI Kit package file. It gives you components to learn from.

*Talk track:* the two-command pair (`cohesion:import` then `cohesion:rebuild`) is exactly the sequence you run **on every deploy** after `drush cim`, because Site Studio config compiles into generated styles/templates that must be regenerated per environment. Naming this signals real exposure.

## Part 0B — The no-licence path

No key? Do the build "on paper" and it still lands in the interview:
- Follow **Acquia's official docs** (docs.acquia.com/site-studio and sitestudiodocs.acquia.com) and the free **Acquia Academy** Site Studio tutorials, narrating each screen out loud.
- Reproduce the Recipe Card **build plan** in Parts 1–4 below as a written spec + annotated screenshots from the tutorials.
- Memorize the **vocabulary box** at the end. In the call, say plainly: *"I've built the mental model and done tutorial-level component and style work; I understand the component → form field → element binding → styles → template flow and I ramp fast."* Honest and credible.

---

## The Site Studio mental model (read before clicking)

- **Component** = a reusable "mini-template." It has two halves: a **component form** (the fields an editor fills in) and a **layout/canvas** (the visual markup, built by dragging **elements**). You **bind** each element to a form field via a **token**.
- **Elements** = the building blocks you drag onto the canvas (Container, Image, Heading, WYSIWYG, Link…).
- **Styles** = visual styling defined in the UI, not hand-written CSS: **base styles** (defaults for an element type) and **custom styles** (reusable named classes).
- **Templates** = how content renders: **content templates** (per content type), **master templates** (page shell), **view templates**, **menu templates**.
- **Helpers** = saved groups of components/elements you reuse to build faster.
- **Layout Canvas** = the drag-and-drop field (on a page/content type) where editors assemble components into a page.

Keep this straight and most Site Studio questions answer themselves.

---

## Part 1 — Create the Recipe Card component (shell + form fields)

**Goal:** an editor-facing component with fields: image, title, total time, difficulty, dietary tags.

1. Go to **Site Studio → Components → Add component** (`/admin/cohesion/components/components/add`).
2. **Title:** `Recipe Card`. Assign a **Category** (create one called "Flavorful" if prompted). Save the shell.
3. Open the component's **Edit** → you'll see two areas: the **component builder canvas** (center) and the **Add form field** panel. First build the **form** (what editors fill in). Click **Add form field** and add these, one at a time:
   - **Image / media** field → label **Image** (an image picker for the card photo).
   - **Text field** → label **Title**.
   - **Text field** (or Number) → label **Total time**.
   - **Dropdown / Select** → label **Difficulty**, options Easy / Medium / Hard.
   - **Text field** → label **Dietary tags**.
4. Each field gets a **machine name / token** (e.g. `[field.image]`, `[field.title]`). You'll reference these when binding elements. Note them.

*Talk track:* the **component form** is the low-code magic — it's the reusable editor UI, so a content author fills in fields instead of touching markup. This is why editors and business users can build pages themselves (a JD responsibility: "support content editors through effective site-building").

---

## Part 2 — Build the card layout (drag elements + bind to fields)

Now build the visual side on the **component canvas**.

1. From the **elements** list, drag a **Container** onto the canvas — this is the card wrapper.
2. Inside it, drag an **Image** element. In its settings, set the image **source** to the component form token **`[field.image]`** (use the token/field picker). Now the editor's uploaded image drives it.
3. Drag a **Heading** element inside the container → bind its text to **`[field.title]`**.
4. Drag a **Text / plain text** element for **Total time** → bind to **`[field.total_time]`**, prefix the output with "⏱ " or "min".
5. Add another **Text** element for **Difficulty** bound to **`[field.difficulty]`**, and one for **Dietary tags** bound to **`[field.dietary_tags]`**.
6. **Save** the component.

*Talk track:* binding an element's content to a **form field token** is the core Site Studio skill — it's how the reusable layout gets per-instance content. It's the low-code equivalent of `{{ content.field_x }}` in Twig (a nice comparison to draw for a technical lead).

---

## Part 3 — Style the card (base + custom styles)

1. Select the **Container** element → open its **Styles** tab in the element settings.
2. Apply a **custom style class** — create one called **`recipe-card`**: set padding, a border, `border-radius`, a subtle box-shadow, background. Save it as a reusable class so other components can use it.
3. To manage reusable styling centrally, go to **Site Studio → Styles → Style builder** — this is where **base styles** (defaults per element, e.g. all Headings) and **custom styles** (named classes like `recipe-card`) live. Define a `recipe-card__title` custom style for the heading.
4. Set the image to a fixed aspect ratio and the card to a sensible max width. **Save**, then **Site Studio → rebuild** (or `drush cohesion:rebuild`) so styles compile.

*Talk track:* Site Studio **styles replace hand-writing most CSS** — base styles = element defaults, custom styles = reusable classes. This is the "styles, layouts, templates" the JD explicitly names.

---

## Part 4 — Lay out a grid of cards

Two ways — do whichever your setup allows; know both exist.

**Option A — manual layout on a page:**
1. Create/edit a page that has a **Layout Canvas** field (e.g. add a Layout Canvas field to a "Landing page" content type, or use a Site Studio-enabled node).
2. On the Layout Canvas, drag a **Container** styled as a **CSS grid** (set display: grid, 3 columns, gap via the container's styles).
3. Drag several **Recipe Card** components inside it and fill each component's form. You now have a styled grid — the Site Studio equivalent of your Day 1 Views grid.

**Option B — dynamic, driven by content:** bind the card to real Recipe nodes using Site Studio's **Views/content templating** (a content template that renders each Recipe with the Recipe Card component). This is closer to production but more involved — understand that it's possible even if you build Option A by hand.

---

## Part 5 — Deploy & config awareness (interview gold)

- Site Studio configuration **exports to Drupal config** like everything else, so it moves with `drush cex` / `drush cim`.
- **But** Site Studio also compiles config into generated styles/templates, so the deploy sequence has an extra step:
  ```
  git deploy → drush updb → drush cim → drush cohesion:import → drush cohesion:rebuild → drush cr
  ```
- On Acquia Cloud this whole sequence typically runs in a **Cloud Hook** on deploy.

*Say this in the call verbatim if Site Studio deployment comes up* — "Site Studio needs an import + rebuild after config import" is the detail that separates people who've shipped it from people who've only read about it.

---

## Part 6 — The comparisons you WILL be asked

- **Site Studio vs. core Layout Builder:** Layout Builder is Drupal core, developer-oriented, styling comes from the theme. Site Studio is a proprietary Acquia **low-code** product with a full visual style system and editor-first UX — faster for editors, but an Acquia dependency with added complexity/lock-in.
- **Site Studio vs. Paragraphs:** Paragraphs = structured content chunks (contrib); Site Studio components are more design/layout-oriented and visually built with their own style system.
- **Site Studio vs. classic Twig theming:** classic theming = full code control, bespoke; Site Studio = speed and editor empowerment, less custom code. In this role you'll do **both** — translate the FE dev's assets into Site Studio components/styles, and drop to Twig when something needs bespoke markup.

---

## End-of-day verification (say out loud)

1. Define **component, element, style, template, helper, Layout Canvas** in one sentence each.
2. Walk through building the Recipe Card: form fields → drag elements → **bind to tokens** → apply styles → place on a Layout Canvas.
3. Why deploying Site Studio needs **`cohesion:import` + `cohesion:rebuild`** after `drush cim`.
4. Site Studio vs. Layout Builder vs. Paragraphs vs. classic theming — when you'd choose each.
5. How your **frontend background** helps: you translate the FE dev's HTML/CSS/JS into components + styles, and understand the assets you're given.

---

## Vocabulary box (memorize)

```
Component        reusable mini-template: form (editor fields) + layout (elements)
Element          draggable building block (Container, Image, Heading, WYSIWYG…)
Form field/token editor input, referenced in the layout as [field.x]
Base style       default styling for an element type
Custom style     reusable named class (e.g. recipe-card)
Style builder    central UI for base + custom styles
Templates        content / master / view / menu templates
Helper           saved reusable group of components/elements
Layout Canvas    drag-and-drop field where components are assembled into a page
UI Kit           starter library of 50+ prebuilt components
Deploy           drush cim → cohesion:import → cohesion:rebuild → cr
```

---

### Sources
- [Install Site Studio (Cohesion) modules with Composer — Acquia docs](https://docs.acquia.com/drupal-starter-kits/add-ons/site-studio/step-1a-install-acquia-cohesion-modules-composer)
- [Site Studio Drush commands (cohesion:import / cohesion:rebuild) — Acquia docs](https://cohesiondocs.acquia.com/6.0/user-guide/acquia-cohesion-drush-commands)
- [Deploying your Site Studio website — Acquia docs](https://sitestudiodocs.acquia.com/6.4/user-guide/deploying-your-website)
- [Components — Acquia Site Studio docs](https://sitestudiodocs.acquia.com/6.0/user-guide/components)
