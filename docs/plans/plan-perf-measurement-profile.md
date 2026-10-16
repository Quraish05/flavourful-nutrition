# Plan — make the site measurable (Phase 3, Week 8–10 items 2 and 3)

> Branch: `perf/measurement-profile` (created from `master` 2026-10-09) · Created: 2026-10-08 · Last updated: 2026-10-16
>
> **Status: Complete — all 7 steps done, merged in PR #53 (`a829d0d`, 2026-10-12).** Notion items 2 and 3 are ticked.

**Goal:** one command switches DDEV between the development profile and a
measurement profile. Pantheon Dev is clean enough to measure. The baseline doc
names both targets.

This file is the working checklist between the plan in Notion (*Phase 3 —
Performance & Core Web Vitals → Week 8–10*, items 2 and 3) and the record in
[`docs/actual-outcomes/performance-baseline.md`](../actual-outcomes/performance-baseline.md).
It is neither the plan nor the outcome.

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

A baseline taken against the DDEV development profile measures that profile, not
the site. On `/articles` today: page cache `MISS` on every request, 37
unaggregated stylesheets, and 48 `THEME DEBUG` blocks. The fix is a measurement
profile you can switch on and off.

**Decided:**

- **One command, `ddev measure on|off`.** It flips a gitignored flag file, and
  `settings.local.php` skips the performance overrides while the flag is set.
- **Twig debug is switched through the database,** because that is where it
  lives (the `development_settings` key-value store, read by core's
  `DevelopmentSettingsPass`). `sites/development.services.yml` only sets
  `http.response.debug_cacheability_headers`. A settings-file flag alone cannot
  turn Twig debug off.
- **DDEV is the baseline target. Pantheon Dev is a separate, production-like
  series.** It has a CDN and is public. The two series are never compared with
  each other.

**Rejected. Don't re-propose these:**

- **`drush theme:dev on` to restore dev mode.** Besides the Twig settings, it
  writes `css.preprocess` and `js.preprocess: false` to *active config*, so
  `config:status` drifts from `config/sync`. `theme:dev off` is safe, because it
  writes `true`, which matches the export.
- **Putting the env var in `.ddev/config.yaml`.** That file is tracked, so every
  toggle would leave a repo diff.
- **Five manual edits to `settings.local.php`.** Easy to half-undo, and nothing
  records which profile a number was taken under.

---

## Before you start

```bash
git checkout master && git pull
git checkout -b perf/measurement-profile
```

`master` currently carries two uncommitted files from the Pantheon work,
`docs/actual-outcomes/pantheon-deployment-guide.md` and the pointer line in
`docs/objectives/README.md`. They are unrelated. Commit them on their own branch
first, or leave them out of this branch's commits.

---

## Steps

| # | Phase | Action | Where | Status |
|---|---|---|---|---|
| 1 | Pantheon | Wake Dev, then turn its copied dev settings off | Pantheon `the-cookbook.dev` | Done |
| 2 | Flag | Gitignore the flag file | `.gitignore` | Done |
| 3 | Settings | Wrap the performance overrides in one `if` and correct the comments | `web/sites/default/settings.local.php` (gitignored) | Done |
| 4 | Command | Add the `ddev measure` host command | `.ddev/commands/host/measure` | Done |
| 5 | Verify | Run the on/off checks below | DDEV | Done |
| 6 | Doc | Name both targets and the profile switch in the baseline doc | `docs/actual-outcomes/performance-baseline.md` | Done |
| 7 | Notion | Tick items 2 and 3 with evidence | Notion Phase 3 page | Done |

Phase 1 is independent and can run first. Steps 3 and 4 depend on step 2. Step 5
needs 3 and 4. Step 6 needs step 5, so that it describes a switch that works.

### Step 1 — Pantheon: turn off the copied dev settings

```bash
terminus env:wake the-cookbook.dev
terminus drush the-cookbook.dev -- theme:dev off
```

`theme:dev off` writes `twig_debug`, `twig_cache_disable` and
`disable_rendered_output_cache_bins` as `FALSE`, and sets CSS/JS `preprocess`
to `true`. Pantheon's active config already has `true`, so nothing drifts. It
flushes all caches itself.

### Step 2 — gitignore the flag file

Add under "Environment-specific / sensitive settings" in the root `.gitignore`:

```gitignore
# Measurement-profile flag, created and removed by `ddev measure on|off`.
/.ddev/config.measure.yaml
```

Checked 2026-10-08: `git check-ignore .ddev/config.measure.yaml` printed
nothing, so the file is not ignored yet.

### Step 3 — `settings.local.php`

The two performance blocks are currently split by the `error_level` line
(lines 18, 23, 28–29 and 35–37). Target shape:

```php
/**
 * Show all error messages, with backtrace information.
 */
$config['system.logging']['error_level'] = 'verbose';

/**
 * Development profile: skipped when `ddev measure on` sets
 * FLAVOURFUL_PROFILE=measure, so numbers reflect config/sync rather than these
 * overrides. Twig debug is not here; `ddev measure` switches it in the database.
 */
if (getenv('FLAVOURFUL_PROFILE') !== 'measure') {
  // Cacheability debug headers (X-Drupal-Cache-Contexts, -Max-Age, -Tags).
  $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

  // Unaggregated, unminified CSS and JS.
  $config['system.performance']['css']['preprocess'] = FALSE;
  $config['system.performance']['js']['preprocess'] = FALSE;

  // No render, page or dynamic page cache, so template edits show at once.
  $settings['cache']['bins']['render'] = 'cache.backend.null';
  $settings['cache']['bins']['page'] = 'cache.backend.null';
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
}
```

- **Rewrite the file's `@file` docblock and the old `container_yamls` comment.**
  Both claim that `development.services.yml` turns on Twig debugging, which is
  false.
- **Leave `rebuild_access` and `skip_permissions_hardening` unchanged.**
- **This file is gitignored.** The change leaves no repo diff, so step 6 must
  describe it.

### Step 4 — `.ddev/commands/host/measure`

```bash
#!/usr/bin/env bash

## Description: Switch DDEV between the development and measurement profiles
## Usage: measure on|off
## Example: "ddev measure on" or "ddev measure off"

set -euo pipefail

flag="${DDEV_APPROOT}/.ddev/config.measure.yaml"

case "${1:-}" in
  on)
    printf 'web_environment:\n  - FLAVOURFUL_PROFILE=measure\n' > "$flag"
    ddev restart
    ddev drush theme:dev off
    ;;
  off)
    rm -f "$flag"
    ddev restart
    ddev drush php:eval '\Drupal::keyValue("development_settings")->setMultiple(["twig_debug" => TRUE, "twig_cache_disable" => TRUE]);'
    ddev drush cr
    ;;
  *)
    echo "Usage: ddev measure on|off" >&2
    exit 1
    ;;
esac
```

Make it executable: `chmod +x .ddev/commands/host/measure`.

- **`.ddev/config.yaml` has `web_environment: []`,** so there is no existing
  list for the flag file to collide with.
- **The flag reaches PHP the same way `IS_DDEV_PROJECT` does.** Checked
  2026-10-08: `getenv("IS_DDEV_PROJECT")` returns `'true'` in the web container.

---

## Verification

Use `curl -s -o /dev/null -D -`, never `curl -I`: HEAD responses are never
stored by the page cache. Request each URL twice, logged out, and read the
second response.

| Check | Command | Before (dev profile, 2026-10-08) | Expect after `ddev measure on` | Expect after `ddev measure off` |
|---|---|---|---|---|
| Flag reached PHP | `ddev exec printenv FLAVOURFUL_PROFILE` | (empty) | `measure` | (empty) |
| Page cache | second `curl -sk -o /dev/null -D - https://foodrecipes-drupal.ddev.site:33001/articles \| grep -i x-drupal-cache:` | `MISS` | `HIT` | `MISS` |
| Dynamic page cache | same response, `x-drupal-dynamic-cache` | `MISS` | `HIT` (or `MISS` on the first warm-up) | `MISS` |
| Twig debug | `curl -sk …/articles \| grep -c 'THEME DEBUG'` | 48 | 0 | 48 |
| Stylesheets | `curl -sk …/articles \| grep -oc 'rel="stylesheet"'` | 37 | 2–4 aggregated `css_*.css` | 37 |
| Scripts | `curl -sk …/articles \| grep -o '<script[^>]*src=' \| wc -l` | 17 | a few aggregated `js_*.js` | 17 |
| Cacheability headers | `… -D - … \| grep -ci x-drupal-cache-contexts` | 1 | 0 | 1 |
| Config drift | `ddev drush config:status \| grep -c Different` | 4 (the `search_api_solr.solr_field_type.*` items) | 4 | 4 |
| Template edit shows at once | Edit any flavourful template, reload without `drush cr` | Shows | Doesn't show (Twig cache on) | Shows |

| Pantheon check (step 1) | Command | Before | Expect |
|---|---|---|---|
| Twig debug | `curl -s https://dev-the-cookbook.pantheonsite.io/articles \| grep -c 'THEME DEBUG'` | 48 | 0 |
| Config drift | `terminus drush the-cookbook.dev -- config:status` | No differences | No differences |
| Stylesheets | `curl -s …/articles \| grep -oc 'rel="stylesheet"'` | 2 | 2 |

**A check that can't fail isn't a check.** If `Different` jumps above 4 after
`off`, something called `theme:dev on` or wrote `preprocess` to active config.
If `THEME DEBUG` stays at 48 after `on`, the container was not rebuilt.
`theme:dev off` flushes caches, so suspect that it never ran.

---

## Step 6 — what the baseline doc needs

- **Amend the first paragraph** so it says baseline numbers come from DDEV
  **under the measurement profile (`ddev measure on`)**, and name the profile
  in every table header.
- **Add a "Targets" paragraph:**
  - DDEV is the baseline: no CDN, and Docker overhead in TTFB.
  - Pantheon Dev (`dev-the-cookbook.pantheonsite.io`) is a separate,
    production-like series behind Pantheon's CDN.
  - Never compare numbers across the two series.
  - Pantheon has no Solr, so `/recipe-search` can only be measured on DDEV.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 1 | `theme:dev off` would change only the Twig flags (active `preprocess` was already `true`) | As expected: `THEME DEBUG` 48 → 0, stylesheets 2 → 2, `config:status` clean, 5 routes 200, visible text within 2% of local. Took DB backup `the-cookbook_dev_2026-10-09T05-59-46_UTC_database.sql.gz` first. The cache flush logged one `security` error: "Couldn't write .htaccess file" in `private://` | Left alone. Pantheon serves through nginx, which ignores `.htaccess`, and `pantheon.yml`'s `protected_web_paths` already blocks `/sites/default/files/private/`. Worth a separate look at whether `private://` exists on Pantheon |
| 2 | 5 | Dynamic page cache `HIT` after `on` | `MISS` on the second request, while page cache was `HIT`. A page-cache hit replays the stored first response, headers included, so its `X-Drupal-Dynamic-Cache` is the first request's | Accepted. The dynamic cache can only be read directly on a request the page cache does not serve (logged in, or with the page cache bypassed). That is item 6's job, not this switch's |
| 3 | 5 | `off` restores `development_settings` exactly | It also leaves a `disable_rendered_output_cache_bins => false` key, which was absent before, because `theme:dev off` writes it | Accepted. `false` is the default, so behaviour is identical |
| 4 | 5 | Template-edit check (row 9 of the table) | Not run, to avoid editing a theme file. Checked the server state instead: `twig_cache_disable` is `false` under `on` and `true` after `off` | The browser-level check is left for the first real theming session |

---

## Out of scope

- **Taking any measurements** (item 6), and capturing the dev-profile
  cacheability headers (also item 6). The switch is reversible, so `ddev measure
  off` brings the headers back whenever item 6 needs them.
- **Solr on Pantheon.**
- **Thyme** (CrUX check, WebPageTest).
- **Committing anything** without an explicit go-ahead.
