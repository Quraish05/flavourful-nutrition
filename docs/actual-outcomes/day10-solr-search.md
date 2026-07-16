# Build Outcomes — Day 10 (Search: Solr bring-up slice)

> Branch: `feat/sdc-component-library` (uncommitted working tree) · Last updated: 2026-07-16
>
> What actually happened bringing up the search stack from the [Day 10 lab](../objectives/day10-search-solr-searchstax.md). This is the **outcome** companion to the plan — it does not re-teach the Search API → Solr → SearchStax architecture or repeat the step listings; read [Day 10](../objectives/day10-search-solr-searchstax.md) first, then this. Cross-cutting infra gotchas hit along the way are logged once in [`lessons-learned.md`](lessons-learned.md) §8.
>
> **Status: partial — infrastructure only.** Solr is running and the Search API server connects to a `flavourful` core, and the Facets modules are installed. The **index, search view, facet config, boosts and SearchStax swap ([§4–§8](../objectives/day10-search-solr-searchstax.md)) are not built yet** — the core holds 0 documents. This is an early checkpoint, not a finished slice.

---

## Objective → outcome map

| Objective | What shipped | Status |
|---|---|---|
| [§1](../objectives/day10-search-solr-searchstax.md) — run Solr in DDEV | `ddev/ddev-drupal-solr` add-on; Solr **8.11.4** container healthy. Core named **`flavourful`**, not the add-on default `dev` (see deviations 1–2). | Done |
| [§2](../objectives/day10-search-solr-searchstax.md) — install search modules | `search_api` + `search_api_solr` (4.3.10) already enabled from an earlier session. | Done (pre-existing) |
| [§3](../objectives/day10-search-solr-searchstax.md) — Search API server connection | `search_api.server.solr` now reaches Solr and the `flavourful` core (server + core ping OK, version 8.11.4) after fixing the host (deviation 3). | Done |
| [§4](../objectives/day10-search-solr-searchstax.md) — index over Recipes | Not built — core `numDocs` = 0. | Not started |
| [§5](../objectives/day10-search-solr-searchstax.md) — search page view | Not built. | Not started |
| [§6](../objectives/day10-search-solr-searchstax.md) — facets | `facets` (3.0.3) + `facets_range_widget` enabled, plus their jQuery-UI dependencies (deviation 4). **No facet configured yet.** | Partial — modules only |
| [§7](../objectives/day10-search-solr-searchstax.md) — relevance boosts | Not started. | Not started |
| [§8](../objectives/day10-search-solr-searchstax.md) — SearchStax connector | Not started. | Not started |

Supporting churn: requiring the Facets/jQuery-UI packages re-triggered `drupal/core-composer-scaffold`, which rewrote several scaffold-managed files (deviation 6). The Solr add-on's files (`.ddev/docker-compose.solr.yaml`, `.ddev/solr/`) plus the corename override are untracked; the server config change lives in **active config only** and is not yet exported (deviation 3).

---

## Deltas-only walkthrough

Only what changed against the objective's baseline — concepts and full steps live in [Day 10](../objectives/day10-search-solr-searchstax.md).

**1. Core name forced to `flavourful` via an override file.** The lab's §3 assumes "the core the add-on created, e.g. `flavourful`", but `ddev/ddev-drupal-solr` defaults `SOLR_CORENAME` to `dev`. Rather than edit the `#ddev-generated` `docker-compose.solr.yaml`, a merge-in override sets the name:

```yaml
# .ddev/docker-compose.solr_corename.yaml
services:
  solr:
    environment:
      - SOLR_CORENAME=flavourful
```

`ddev` merges all `docker-compose.*.yaml` files, so this appends the env var without touching the generated file (survives add-on updates). The empty `dev` volume was dropped and the project restarted so `solr-precreate` built a fresh `flavourful` core.

**2. Server host was `localhost`, corrected to `solr`.** The `search_api.server.solr` entity pre-existed with `connector_config.host: localhost` — which, from inside the web container, points at the web container itself, not Solr. One active-config change fixed it:

```bash
ddev drush cset search_api.server.solr backend_config.connector_config.host solr -y
```

Port `8983` and path `/` were already correct.

**3. Facets needed two extra contrib packages.** `drush en facets facets_range_widget` failed twice — `facets_range_widget` depends on `jquery_ui_slider` **and** `jquery_ui_touch_punch`, which are separate contrib projects since jQuery UI left Drupal core (only the base `jquery_ui` was present). Fixed by requiring both, then enabling:

```bash
ddev composer require drupal/jquery_ui_slider drupal/jquery_ui_touch_punch -W
ddev drush en facets facets_range_widget -y   # auto-enables both jquery_ui_* submodules
```

---

## Deviation log

Where the build departed from the plan, and why. Format matches [`lessons-learned.md`](lessons-learned.md): what happened → why → what we did.

| # | Divergence from objective | Why it happened | What we did / open item |
|---|---|---|---|
| 1 | **Add-on core is `dev`, not `flavourful`** as §3 assumes. | `ddev/ddev-drupal-solr` hard-defaults `SOLR_CORENAME=dev`; the name is not derived from the project. | Added an override compose file setting `SOLR_CORENAME=flavourful`, dropped the empty `dev` volume, restarted so the `flavourful` core was precreated. |
| 2 | **Override file instead of editing the add-on file.** | `docker-compose.solr.yaml` is `#ddev-generated` and can be regenerated on add-on update. | Put the env var in `.ddev/docker-compose.solr_corename.yaml`; `ddev` merges it in. Both files are untracked (Day 10 infra). |
| 3 | **Server host was `localhost`** → "server could not be reached / core could not be accessed". | The server entity was configured earlier with `host: localhost`, invalid inside the web container (that host is the web container itself). | Set host → `solr`; server + core now ping OK (Solr 8.11.4). **Open item:** the server lives in active config only, not `config/sync` — run `drush cex` to persist it. |
| 4 | **`facets_range_widget` blocked on missing deps** (`jquery_ui_slider`, then `jquery_ui_touch_punch`). | jQuery UI was removed from core in D9+, so its slider pieces are separate contrib packages; only base `jquery_ui` was installed. Drush enables code, it can't fetch it. | `composer require drupal/jquery_ui_slider drupal/jquery_ui_touch_punch`, then enabled facets. **Open item:** `drush cex` to export the four newly-enabled modules to `core.extension.yml`. |
| 5 | **DDEV router outage blocked project start** during Solr bring-up. | The shared router (Traefik) had 12 dangling routes from other **paused** projects (`prm-stack`, `PRK-Magento`) referencing entrypoints the recreated router no longer defines. | `ddev poweroff && ddev start` regenerated a clean router config. Logged in [`lessons-learned.md`](lessons-learned.md) §8; not a project fault. |
| 6 | **Scaffold-managed files changed** after `composer require` (`.htaccess`, `.editorconfig`, `.gitattributes`, `.csslintrc`, `.eslintignore`, `example.gitignore`, `sites/development.services.yml`). | `drupal/core-composer-scaffold` re-applies its file mapping on any composer operation. | Expected churn, not from our edits. **Open item:** review the diffs and decide keep/revert before committing the Day 10 work. |

---

*Add to this file as the rest of Day 10 (index, search view, facets config, boosts, SearchStax) lands, so the objective and outcome stay in step.*
