# Lessons Learned — Hands-On Mistakes & Applied Solutions

> A running log of the things that *actually* tripped us up while building and deploying this project, and exactly how each was fixed. Unlike the step-by-step labs, this is organised by **problem → root cause → fix** so it's fast to search when you hit the same wall — and it doubles as a set of concrete war-stories for the vetting call.
>
> The Acquia deployment issues are the richest source; the full narrative lives in [`acquia-deployment-guide.md`](acquia-deployment-guide.md). This file consolidates those plus the smaller gotchas from the site-building, theming, and Views work.

---

## 1. Acquia Cloud deployment

| # | Symptom | Root cause | Fix |
|---|---|---|---|
| 1 | `The supplied public key type is unsupported: it must be RSA` | Acquia's Git/SSH endpoints reject **ed25519** keys | Generate an RSA key: `ssh-keygen -t rsa -b 4096 -f ~/.ssh/acquia_key` |
| 2 | `Permission denied (publickey)` even after adding the key | A `Host *` (e.g. 1Password agent) block answered first; the per-env SSH domain wasn't covered | Dedicated `~/.ssh/config` block for `*.hosting.acquia.com` **and** `*.acquia-sites.com`, with `IdentityAgent none` and `IdentitiesOnly yes`, placed **before** any `Host *` |
| 3 | `ParseError: unexpected identifier "…"` in a Composer plugin | Host PHP too old (8.2) to *parse* Drupal 11 deps written in 8.3 syntax; Composer loads plugins at runtime | Build under PHP 8.3+ (`PATH=/opt/homebrew/opt/php/bin:$PATH`) |
| 4 | `Allowed memory size … exhausted` during artifact build | Default `memory_limit=128M` too small to scan a full Drupal + `vendor/` tree | `php -d memory_limit=-1 acli push:artifact …` |
| 5 | `The required binary "composer" does not exist` | Project only had `ddev composer`; no host Composer | `brew install composer` |
| 6 | `local Git repository has uncommitted changes` | `acli push:artifact` demands a clean tree | Commit, or `git stash --include-untracked`, before building |
| 7 | Homepage 200s to "Welcome to Acquia Cloud", every path 404s | Acquia serves `docroot/`; the project used `web/` | Relocate the Composer web root `web/` → `docroot/` (web-root + installer-paths, `git mv`, update `.gitignore`/`.ddev`/CI, `composer install`) |
| 8 | `settings.php` missing on the server after deploy | `acli` honors `.gitignore`, and the default Drupal `.gitignore` ignores `sites/*/settings.php` — so even a committed file is filtered out of the artifact | Un-ignore `settings.php` (keep `settings.local.php` / `settings.ddev.php` / `services.yml` ignored) |
| 9 | Pushed to `dist` but the site is unchanged | Push ≠ deploy on Acquia | `acli api:environments:code-switch <ENV_ID> dist`, then poll `acli api:notifications:find <UUID>` |
| 10 | `/user/login` still 404 after everything else worked | Varnish had cached the earlier broken 404 (`X-Cache: HIT`, high `Age`) | `acli api:environments:domains-clear-varnish <ENV_ID> <DOMAIN>` |

**Meta-lessons from the deployment**

- **Artifact, not source.** Anything gitignored (dependencies *and* `settings.php`) is invisible to Acquia unless it's in the artifact. Know what `acli push:artifact` includes/excludes.
- **Two-phase deploy.** `push:artifact` (updates Git) and `code-switch` (activates it) are separate steps.
- **Build-host PHP matters twice** — it must *parse* your plugins, and you should *pin* the resolution target (`config.platform.php`) to the Cloud runtime.
- **Docroot is the platform's decision.** Confirm `$DOCROOT` on the server early and align the repo.
- **When something "should work but doesn't," check Varnish** before re-deploying (`curl -I` → `X-Cache`/`Age`).

---

## 2. Configuration management

| Symptom | Root cause | Fix |
|---|---|---|
| `drush cim` fails on a schema/field mismatch during a release | DB updates that change config schema hadn't run yet | Always run `drush updb` **before** `drush cim` (`updb → cim → cr`) |
| A dev-only module (Devel) keeps trying to enable on prod during `cim` | Single shared config set with no per-environment split | Use **Config Split**: put Devel in a `dev` split, activate it only via the dev environment's `settings.php` |
| Secrets nearly committed into `config/sync` | API keys treated as config | Keep secrets in `settings.php` / env vars; never in exported YAML |
| "Why isn't my structural change on the other environment?" | Config only exists in the DB until exported | `drush cex` → commit the YAML → deploy → `drush cim` on the target |

---

## 3. Views (site-building)

| Symptom | Root cause | Fix |
|---|---|---|
| FIELDS box says *"The selected style or row format does not use fields"* | The view's **Show** row format is set to **Content** (rendered teaser), which has no separate fields | FORMAT section → next to **Show:** click **Content** → choose **Fields** → Apply. (Field rewriting only works in Fields mode.) |
| Can't find the node-ID filter when searching "Content ID" | The entry is labelled just **ID** (machine name `nid`), and the search matches the label | Search `ID` with Category set to **Content** / **- All -**; pick the row named **ID** under Content |
| Related-recipes block shows nothing / shows the current recipe | Contextual filter not reading the current node, or current node not excluded | Contextual filter **Has taxonomy term ID** → default **Taxonomy term ID from URL** → tick **Load default filter from node page** + **Limit terms by vocabulary → Cuisine**; add a second contextual filter **Content: ID** (default **Content ID from URL**) with **Exclude** ticked |
| A rewrite token (e.g. `{{ nid }}`) renders blank | Views only exposes a token from fields listed **above** the field using it | Reorder so the source field (e.g. `Content: ID`, excluded from display) sits **above** the field doing the rewrite |

---

## 4. Theming & Twig

| Symptom | Root cause | Fix |
|---|---|---|
| Template/CSS change doesn't appear | Render cache | `drush cr`; confirm which template loaded via Twig debug comments |
| Not sure which template Drupal is using | Twig debug off | Enable `twig.config: debug: true` in `sites/default/services.yml`, then read the `FILE NAME SUGGESTIONS` comments in page source (turn off for prod) |
| Custom recipe template not picked up | Wrong template-suggestion filename | Use the suggestion Drupal lists, e.g. `node--recipe.html.twig` (double dash, bundle machine name) |
| Escaped HTML showing as text — or worried about XSS | Twig autoescapes by default; `|raw` bypasses it | Render fields via `{{ content.field_x }}`; only use `|raw` on trusted markup |

---

## 5. Custom module & integration

| Symptom | Root cause | Fix |
|---|---|---|
| Class not found after adding a file under `src/` | PSR-4 path/namespace mismatch | Folder path after `src/` must equal the namespace after `Drupal\<module>\`; `drush cr` |
| Service can't reach another service | Used `\Drupal::service()` inside a class | Inject the dependency via the constructor (DI); for plugins, use `ContainerFactoryPluginInterface::create()` |
| A slow external API hangs the page | No timeout / no failure handling | Set a Guzzle `timeout`; wrap in try/catch; log errors and return a safe default |
| API called on every page load | No caching on the result | Cache the response (e.g. `cache.default` with a TTL); cache the render output with the node's **cache tags**, not `max-age: 0` |
| API key at risk of being committed | Key placed in config | Store in `settings.php` / env var; read with `Settings::get()` |

---

## 6. Site Studio

| Symptom | Root cause | Fix |
|---|---|---|
| Styles/templates missing or stale on a fresh environment | Site Studio config compiles into generated assets that don't exist yet on the target | After `drush cim`, run `drush cohesion:import` then `drush cohesion:rebuild` (add to the Cloud Hook) |
| Component renders but shows no editor content | Elements not bound to component form fields | Bind each element's content to the component's form-field token (`[field.x]`) |

---

## 7. Local tooling (build host)

| Symptom | Root cause | Fix |
|---|---|---|
| `acli` refuses to build | Uncommitted changes / not a Drupal-shaped `docroot/` | Commit or stash first; ensure `docroot/` looks like a Drupal app |
| Composer resolves against the wrong PHP | Host PHP differs from Acquia's 8.3 | Pin `config.platform.php` to `8.3.0`; refresh with `ddev composer update --lock --no-install` |

---

## 8. Search (Solr / DDEV services)

| Symptom | Root cause | Fix |
|---|---|---|
| `ddev start` fails: router "unhealthy, Detected N configuration error(s)" | Shared DDEV router (Traefik) has dangling routes from other **paused** projects pointing at entrypoints the recreated router no longer defines | `ddev poweroff && ddev start`; to prevent recurrence, `ddev stop <other-project>` (fully) rather than leaving projects paused |
| Search API: "Solr server could not be reached / core could not be accessed" | Server `host` set to `localhost` — inside the web container that is the web container, not Solr | Set host to the service name `solr` (`drush cset search_api.server.<id> backend_config.connector_config.host solr`) |
| DDEV Solr core name isn't what config expects | `ddev/ddev-drupal-solr` defaults `SOLR_CORENAME=dev`, not the project name | Add a merge-in `.ddev/docker-compose.solr_*.yaml` setting `SOLR_CORENAME`; drop the old solr volume and restart so the core is precreated |
| `drush en facets_range_widget` — "missing its dependency module jquery_ui_slider / jquery_ui_touch_punch" | jQuery UI left Drupal core in D9+; its slider pieces are separate contrib projects, not shipped with base `jquery_ui` | `composer require drupal/jquery_ui_slider drupal/jquery_ui_touch_punch`, then enable |
| Scaffold files (`.htaccess`, `.editorconfig`, `.gitattributes`, `development.services.yml`…) show as modified after `composer require` | `drupal/core-composer-scaffold` re-applies its file mapping on any composer op | Expected; review the diffs and keep/revert deliberately before committing |

---

*Add to this file as you hit new issues — a good lessons-learned log is the fastest onboarding doc a team can have, and the source of the most credible interview stories.*
