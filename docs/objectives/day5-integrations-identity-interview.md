# Flavorful — Day 5 Lab: Integrations, Identity & Profile Mapping, the Quality Bar, and Interview Day

> Companion to the 5-day Drupal/Acquia vetting prep guide. Written as if we're sitting together — the reasoning comes before every step, because in the vetting call it's the reasoning that earns the role, not the clicks.
>
> **How to read this:** each part opens with *Why this matters* so you understand the shape of the problem first. If you're newer to Drupal, do Parts 1–2 hands-on and treat Parts 3–5 as read-and-rehearse. Today's goal is to **connect the week together** and walk in able to talk about a real thing you built.

**Build target:** your `flavorful_nutrition` module calls a **real external API** and shows nutrition on a recipe; Chefs become **real users with profile fields** (the identity/profile-mapping story); a quick **performance / security / SEO / accessibility** pass; and you can run the **interview conversation** with a rehearsed talk-track.

---

## 0. Where Day 5 fits in the vetting goal

- **Integrations & identity** — the JD says *"experience supporting integrations with external APIs, user identity flows, or profile/content mapping."* Note the word **supporting**: this is a **softer** requirement. A clear conceptual answer plus one real thing you built (Part 1) is plenty.
- **Non-functional quality** — performance, security, SEO, accessibility (WCAG) are expectations. Name the Drupal tools and show you think about them. Your **frontend background** makes accessibility and performance a genuine strength — lead with it.
- **Communication** — the JD ends on cross-functional teamwork and code reviews. Part 5 rehearses how you actually talk in the call.

> **Keep the honesty rule front-of-mind:** today gives you real, small wins to point to. When a question goes past what you've done, say so plainly and reason out loud — *"I haven't shipped that in production, but here's how I'd approach it…"* Drupal leads trust that far more than bluffing.

---

## 1. Wire a real external API into your module

> **Why this matters:** "Integrate an external API" sounds big, but in Drupal it's a small, well-worn pattern: a **service** uses Drupal's HTTP client (Guzzle) to fetch data, you **map** the response onto your entities, you **cache** it so you're not hammering the API, and you **handle failures** so a slow API never breaks your page.

### 1.1 Pick an API and get a free key

We'll use the **Open Food Facts** API — free, genuinely about food/nutrition, clean JSON, and — importantly — **no API key and no signup** to read data. (It was the standout among the no-auth options; USDA FoodData Central also has real data but makes you register for a key, and TheMealDB is recipes, not nutrition.)

1. Nothing to sign up for. Try it in a browser to see the data shape: `https://world.openfoodfacts.org/cgi/search.pl?search_terms=tomato&search_simple=1&action=process&json=1&page_size=1&fields=product_name,nutriments`. You get a `products` array; each product has a `nutriments` object with keys like `energy-kcal_100g` and `proteins_100g` (values per 100 g).

### 1.2 A note on secrets (the Day-3 lesson still matters)

Open Food Facts needs **no key**, so there's nothing secret to store here — one less thing to leak. But the moment you use an API that *does* need a key (most do), it's a **secret**: it goes in `settings.php` / an environment variable, **never** in `config/sync`.

```php
// Example only — Open Food Facts doesn't need this. When an API does:
$settings['flavorful_nutrition.api_key'] = 'PASTE-KEY-HERE';
```

> **Why not config?** Anything in `config/sync` lands in Git history and travels to every environment. Secrets belong in `settings.php` / environment variables so each environment supplies its own.
>
> **Be a good API citizen:** Open Food Facts asks callers to send a descriptive `User-Agent`, and we cache aggressively (below) so we're not hammering a free, community-run service.

### 1.3 Update the service to call the API

Add the cache service to `flavorful_nutrition.services.yml`:

```yaml
services:
  flavorful_nutrition.client:
    class: Drupal\flavorful_nutrition\NutritionClient
    arguments: ['@http_client', '@logger.factory', '@cache.default']
```

Then `src/NutritionClient.php`:

```php
<?php

namespace Drupal\flavorful_nutrition;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

class NutritionClient {

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected CacheBackendInterface $cache,
  ) {}

  /**
   * Returns rough per-100g nutrition for an ingredient (calories, protein).
   */
  public function getNutritionForIngredient(string $ingredient): array {
    $cid = 'flavorful_nutrition:' . md5(strtolower($ingredient));
    if ($hit = $this->cache->get($cid)) {
      return $hit->data;                       // served from cache
    }

    $result = ['ingredient' => $ingredient, 'calories' => 0, 'protein' => 0];

    try {
      $response = $this->httpClient->request('GET',
        'https://world.openfoodfacts.org/cgi/search.pl', [
          'query' => [
            'search_terms' => $ingredient,
            'search_simple' => 1,
            'action' => 'process',
            'json' => 1,
            'page_size' => 1,
            'fields' => 'product_name,nutriments',
          ],
          'headers' => ['User-Agent' => 'Flavorful/1.0 (learning project)'],
          'timeout' => 5,
        ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      $nutriments = $data['products'][0]['nutriments'] ?? [];
      $result['calories'] = (int) round($nutriments['energy-kcal_100g'] ?? 0);
      $result['protein']  = (float) ($nutriments['proteins_100g'] ?? 0);

      // Cache for 24h so we don't call the API on every page load.
      $this->cache->set($cid, $result, time() + 86400);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('flavorful_nutrition')->error('Nutrition API: @m', ['@m' => $e->getMessage()]);
    }
    return $result;
  }

}
```

> **Read the code like an architect:** every production integration has these same moving parts — **inject** the HTTP client (never `new GuzzleClient()`), a **timeout** so a slow API can't hang your site, a **try/catch + log** so failures degrade gracefully, and a **cache** so you respect the API and stay fast. If you can point at these six lines in an interview, you've demonstrated real integration judgement.

### 1.4 Show the current recipe's nutrition (map API → entity)

This code lives in the **block plugin you created on Day 2**:

```
web/modules/custom/flavorful_nutrition/src/Plugin/Block/NutritionFactsBlock.php
```

You're doing two things to that class: (1) injecting one more dependency — `current_route_match`, so the block knows which recipe page it's on — and (2) replacing the placeholder `build()`.

First, add the route-match service alongside the `NutritionClient` you already inject (the `use` line, the constructor argument, and the matching `create()` line):

```php
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

public function __construct(
  array $configuration, $plugin_id, $plugin_definition,
  protected NutritionClient $client,
  protected RouteMatchInterface $routeMatch,
) {
  parent::__construct($configuration, $plugin_id, $plugin_definition);
}

public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
  return new static(
    $configuration, $plugin_id, $plugin_definition,
    $container->get('flavorful_nutrition.client'),
    $container->get('current_route_match'),
  );
}
```

Then replace the placeholder `build()` with:

```php
public function build(): array {
  $node = $this->routeMatch->getParameter('node');
  if (!$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'recipe') {
    return [];
  }
  $calories = 0; $protein = 0;
  foreach ($node->get('field_ingredients')->referencedEntities() as $term) {
    $n = $this->client->getNutritionForIngredient($term->label());
    $calories += $n['calories'];
    $protein  += $n['protein'];
  }
  return [
    '#theme' => 'item_list',
    '#title' => $this->t('Estimated nutrition'),
    '#items' => [
      $this->t('Calories: @c kcal', ['@c' => $calories]),
      $this->t('Protein: @p g', ['@p' => round($protein, 1)]),
    ],
    // Correct caching: rebuild when THIS node changes, not on every request.
    '#cache' => ['tags' => $node->getCacheTags()],
  ];
}
```

> **Notice the caching upgrade:** on Day 2 the placeholder used `max-age => 0` (never cache). Here we use the node's **cache tags** so the block is cached and only rebuilds when that recipe is edited. Understanding cache **tags vs. max-age** is a frequent interview probe — this is a concrete example you now own.

**Test and frame it**

- `drush cr`, open a recipe with ingredients that exist in the food database (tomato, chicken, basil), and confirm the block shows totals.
- **Interview line:** *"I consumed an external REST API through Drupal's injected HTTP client, mapped the JSON onto my recipe's ingredient entities, cached results with the node's cache tags, and logged failures so a slow API never breaks the page."*

---

## 2. Identity & profile mapping: turn Chefs into real users

> **Why this matters:** the JD mentions *"user identity flows"* and *"profile/content mapping."* In Drupal that means: people log in as **User** entities, users have **profile fields**, and when they sign in through an external system (SSO) you **map** the external profile onto those fields. On Day 1 we made Chef a content type to learn site-building; today we discuss why a Chef is really a **User** — that modelling judgement is exactly what a lead wants to hear.

### 2.1 The architecture decision (a teaching moment)

Ask: does a Chef **log in, own content, and have an identity?** If yes, they're a **User**, not a node. Users are the entity Drupal ties authentication, permissions, and ownership to. Recipes are then **authored by** that user (the built-in node author), and the user carries the public profile.

> **Say this in the call:** *"I model an actor who authenticates as a User with profile fields, and I use the node's author relationship to tie their recipes to them — rather than duplicating people as content nodes."* That single sentence shows entity-modelling maturity.

### 2.2 Add profile fields to the User entity

1. Go to `Configuration → People → Account settings → Manage fields` (`/admin/config/people/accounts/fields`).
2. Add **Bio** (Text, long), **Photo** (Reference → Media / Image), and **Specialty** (Text, plain) — like adding fields to a content type, but on the User bundle.
3. Create a **Chef** role at `/admin/people/roles` and give it permission to create/edit its **own** recipes only.
4. Create a couple of Chef **user accounts** at `/admin/people → Add user`, fill their profile fields, assign the Chef role.

### 2.3 Point recipes at the author user

Use the node's built-in **Authored by** field: when a chef creates a recipe, they're the author. To list a chef's recipes (Day-1 View 3), filter/relate on the node **author (uid)** instead of the old Chef-node reference. If you'd rather keep an explicit link, add a `field_author` Reference → User on Recipe. Either is fine — be able to explain the trade-off (built-in author = simpler; explicit field = more control).

### 2.4 Single Sign-On / OpenID Connect (the mapping story)

> **Why this matters:** enterprises rarely want separate Drupal passwords — they log in via Google, Okta, Azure AD, etc. **OpenID Connect** is the standard, and Drupal's contrib module implements it. The **mapping** is the interesting part: the provider returns *claims* (email, name, picture), and you map those onto Drupal user fields on login. That's the JD's "profile/content mapping logic."

1. `composer require drupal/openid_connect` then `drush en openid_connect -y`.
2. At `/admin/config/services/openid-connect` add a client (a generic OIDC provider, or Google for a quick test) and paste the Client ID/secret.
3. Under the module's claim/field settings, **map claims to user fields** — e.g. `name` → the user's name, `picture` → your **Photo** field, custom claims → **Specialty**/**Bio**.
4. A chef signs in through the provider and their Drupal profile is populated automatically — no separate password.

> **Depth expectation:** this is the **softer** JD requirement — a clear conceptual walkthrough plus knowing the module name and the claim-to-field mapping idea is enough.

---

## 3. The quality bar: performance, security, SEO, accessibility

> **Why this matters:** these are enterprise expectations. You'll be asked *"how do you think about performance / security / accessibility in Drupal?"* Name the tools, tie them to Flavorful, and lead with accessibility/performance where your frontend background shines.

### 3.1 Performance

- **Caching** — enable Internal Page Cache + Dynamic Page Cache; understand cache **tags/contexts/max-age** (you used tags in Part 1); **BigPipe** streams slow parts after the fast page.
- **Assets** — turn on CSS/JS aggregation at `/admin/config/development/performance`.
- **Images** — use **image styles** and responsive images for the recipe hero; lazy-load below-the-fold images.

### 3.2 Security

- **Stay patched** — `composer audit` and Drupal's security advisories; apply core/contrib security releases promptly.
- **Trust nothing** — Twig **autoescapes** by default; never pipe untrusted input through `|raw`; use the entity/field render pipeline rather than printing raw values.
- **Least privilege** — review the permissions grid; editors and chefs never get `administer` permissions (your Day-1 roles).

### 3.3 SEO

- **Clean URLs** — **Pathauto** (added on Day 3): `/recipes/spaghetti-bolognese`.
- **Metadata** — the **Metatag** module for titles/descriptions/Open Graph; **Simple XML Sitemap**; the **Redirect** module for moved URLs.
- **Semantics** — your `node--recipe.html.twig` uses real headings and an `<article>` wrapper (Day 2) — good for SEO and accessibility at once.

### 3.4 Accessibility (WCAG) — your strong suit

- **Alt text** — every recipe hero image needs meaningful alt text (set on Media); decorative images get empty alt.
- **Structure & keyboard** — correct heading order, focus states, everything usable by keyboard; sufficient colour contrast in your theme.
- **Test** — run **axe** or Lighthouse on a recipe page; Drupal core targets WCAG 2.x AA.

---

## 4. Git & collaboration hygiene (quick recap)

- Feature branches + pull requests (Day 3), meaningful commits, and reviewing the `config/sync` diff so structural changes are visible to reviewers.
- Never commit `vendor/`, `node_modules/`, or secrets; resolve `composer.lock` conflicts by re-running Composer rather than hand-merging.
- Participate in code review as a two-way street — the JD explicitly values this and CMS governance.

---

## 5. Interview day: how to run the conversation

### 5.1 The talk-track (rehearse out loud)

- **Open with your bridge:** *"5 years of web development, strong frontend and hands-on Drupal theming and Twig; I've been deepening backend site-building, the Acquia deploy workflow, and Site Studio — and I ramp fast in a real codebase."*
- **Lead with the frontend strength** when Site Studio / theming / accessibility comes up — it's on their *nice-to-have* list and it's real.
- **Be honest about gaps:** *"I haven't shipped that in production; here's how I'd approach it, and my closest experience is…"*

### 5.2 Your two-minute project story

Have one crisp story ready. Structure: **context** (what the site/team was), **your role** (frontend + theming/Twig), **a concrete problem you solved**, and **the outcome**. Practise it until it's 90–120 seconds. If Flavorful is your freshest hands-on work, it's fair to describe it as a learning build you architected this week.

### 5.3 Questions to ask them (have three ready)

- "How mature is the Site Studio side of the codebase — lots of custom components, or mostly a UI Kit?"
- "What does your deploy pipeline look like — Cloud Hooks, Code Studio, GitHub Actions?"
- "How is work split between the internal front-end developer, the tech lead, and this role?"

### 5.4 Day-5 topic Q&A (model answers)

| Question | Answer shape |
|---|---|
| How would you integrate an external API? | Injected HTTP client (Guzzle) in a service; map JSON onto entities; cache with tags; timeout + try/catch + log; queue for bulk syncs. (Point at Part 1.) |
| Where do API keys / secrets go? | `settings.php` / environment variables — never in `config/sync`. Each environment supplies its own. |
| How would you implement SSO? | OpenID Connect module; configure the provider client; map claims (email, name, picture) onto user profile fields on login. |
| Make a Drupal page fast? | Page/Dynamic cache + correct cache tags, BigPipe, CSS/JS aggregation, image styles/responsive images, CDN. |
| Ensure accessibility? | Semantic HTML, heading order, alt text, contrast, keyboard nav; test with axe/Lighthouse; WCAG 2.x AA. |

---

## 6. The whole week, mapped to the job description

Your confidence table — every mandatory JD line and the concrete Flavorful work that backs it. Skim it the morning of the call.

| JD requirement | What you built in Flavorful |
|---|---|
| Content types, fields, taxonomy | Recipe + Chef, four vocabularies, entity references (Day 1) |
| Views | Listing with exposed filters, related-by-cuisine block, chef's-recipes page (Day 1) |
| Themes & Twig templating | flavorful_theme subtheme, `node--recipe.html.twig`, preprocess (Day 2) |
| Custom module & Drupal APIs | flavorful_nutrition: route, controller, service (DI), block plugin (Day 2) |
| Composer, Drush, config mgmt, deploys | Git + Composer, cex/cim, Config Split, GitHub CI, Acquia deploy (Day 3) |
| Acquia Cloud Platform | Trial app, RSA SSH key, acli, push:artifact, code-switch, environments (Day 3) |
| Site Studio / Cohesion | Recipe Card component, styles, Layout Canvas, rebuild-on-deploy (Day 4) |
| External API / identity / mapping | Open Food Facts nutrition API; Chef-as-User + OpenID Connect claim mapping (Day 5) |
| Performance / security / SEO / a11y | Caching, aggregation, Pathauto/Metatag, alt text + contrast (Day 5) |

> **Final reminder:** you now have a real, end-to-end build to talk about and an honest story about where you're strong and where you're growing. That combination — **competence plus candour** — is what passes a vetting call.

---

### Sources

- [Open Food Facts API](https://openfoodfacts.github.io/openfoodfacts-server/api/)
- [OpenID Connect (Drupal.org)](https://www.drupal.org/project/openid_connect)
- [Drupal HTTP client (Guzzle) docs](https://www.drupal.org/docs/develop/drupal-apis/httpclient)
- [Cacheability of render arrays](https://www.drupal.org/docs/develop/drupal-apis/cache-api/cacheability-of-render-arrays)
