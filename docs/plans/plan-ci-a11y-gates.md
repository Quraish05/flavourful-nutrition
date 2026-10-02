# Plan — CI: make the suite able to fail, and add static accessibility gates

> Branch: `ci/a11y-gates` (off `master`) · Created: 2026-09-16 · Last updated: 2026-09-16
>
> **Status: not started — 0 of 9 done.**
>
> Week 5–6 of the Notion plan, scoped to what can actually run in this stack. Neither an objective nor an outcome — the transient middle state. It feeds [`docs/actual-outcomes/accessibility-audit.md`](../actual-outcomes/accessibility-audit.md).
>
> **Reference copies of every finished file** are in this session's scratchpad at `…/scratchpad/ci-backup/`, including `full.patch`. They are a convenience, not the source of truth — this plan carries the content, and the scratchpad does not survive the session.

---

## Why this plan exists

Scoping the Week 5–6 accessibility gate turned up something more urgent: **the existing suite cannot fail.** Adding accessibility checks on top of a suite that reports green regardless would just add more green.

From the log of the most recent run:

```
Run vendor/bin/phpunit docroot/modules/custom || true
/home/runner/.../sh: line 1: vendor/bin/phpunit: No such file or directory
```

`phpunit` has never been installed — `require-dev` holds coder, devel and phpstan, but no `drupal/core-dev`. So the step ran a missing binary, `|| true` swallowed the exit code, and CI reported a green **Tests** step throughout an audit that found twenty-five defects.

**Decided:** fix the suite first, then add the two accessibility gates that need no running site.

**Rejected — do not re-propose:** bringing a Drupal site up in CI so axe-core and pa11y-ci can run. It is blocked by the stack, not by effort — see *Out of scope*.

**Decided, and worth keeping:** the contrast gate gets **no baseline file**. The palette was brought to 0 failing *before* the gate was added, so it can be zero-tolerance. The Notion plan assumed "fail on new violations rather than on the existing backlog"; fixing first and gating second removes the need. phpstan does get a baseline, because its three findings are false positives that cannot be fixed.

---

## Prerequisite — the push will be rejected without this

```bash
gh auth refresh -h github.com -s workflow
```

The current token has `gist, read:org, repo` and **no `workflow` scope**, so any push touching `.github/workflows/` is refused by the remote:

```
! [remote rejected] refusing to allow an OAuth App to create or update
  workflow `.github/workflows/ci.yml` without `workflow` scope
```

Do this first. It fails at the very end otherwise, after all the work.

---

## Phase 1 — make the existing suite able to fail

| # | Step | File | Status |
|---|---|---|---|
| 1 | Branch: `git checkout master && git pull && git checkout -b ci/a11y-gates` | — | Not started |
| 2 | Replace the workflow (full content in step 6) — `main` → `master`, phpstan config, delete the fake test step | `.github/workflows/ci.yml` | Not started |
| 3 | Add `phpstan.neon` | new file | Not started |
| 4 | Generate `phpstan-baseline.neon`, then prepend the header comment | new file | Not started |
| 5 | Add a `*.neon` rule to `.gitattributes` | `.gitattributes` | Not started |

### Step 3 — `phpstan.neon` (new file, repo root)

```neon
# Static analysis for the custom module and theme code.
#
# Level 2, not the implicit 0 this project ran at before. Level 0 checks little
# more than that the code parses, which is why CI reported it green throughout
# an audit that found two dozen defects.
#
# The baseline holds three pre-existing hits and nothing else, so anything new
# fails the build. Regenerate it deliberately, never to silence a fresh error:
#
#   ddev exec "vendor/bin/phpstan analyse --generate-baseline"
includes:
  - phpstan-baseline.neon

parameters:
  level: 2
  paths:
    - docroot/modules/custom
    - docroot/themes/custom
  # Compiled CSS, fixtures and vendor code inside the theme are not ours.
  excludePaths:
    analyseAndScan:
      - docroot/themes/custom/*/node_modules
```

### Step 4 — the baseline

**Generate it, do not hand-write it** — phpstan's message patterns are escaped and easy to get subtly wrong.

```bash
printf 'parameters:\n\tignoreErrors: []\n' > phpstan-baseline.neon
ddev exec "vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon --no-progress"
# expect: [OK] Baseline generated with 3 errors.
```

Run it **through ddev** — host PHP is 8.2 and `vendor/composer/platform_check.php` fatals.

Then prepend this to the generated file, above `parameters:`:

```neon
# Pre-existing phpstan findings, frozen so that anything NEW fails the build.
#
# All three are the same Drupal pattern and all three are false positives:
# FieldItemBase::__get() serves $item->entity and $item->value at runtime, but
# phpstan sees only the FieldItemInterface type hint and cannot resolve the
# concrete item class. The code is correct.
#
# Do not add to this file to make a new error go away. Regenerate it only when
# a listed finding is genuinely fixed:
#
#   ddev exec "vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon"
```

All three are in `docroot/themes/custom/flavourful/src/Hook/RecipeHooks.php`, lines 224, 300 and 312.

### Step 5 — `.gitattributes`

Find the `*.py` block added earlier and replace its comment and add one line:

```
# Not from Drupal's stock list. Added with scripts/*.py and phpstan.neon:
# this clone has core.autocrlf=true, which would otherwise check these out
# with CRLF — breaking the shebang line on the scripts, and leaving the
# tab-indented .neon files inconsistent with what phpstan regenerates.
*.py      text eol=lf whitespace=blank-at-eol,-blank-at-eof,-space-before-tab,tab-in-indent,tabwidth=4
*.neon    text eol=lf whitespace=blank-at-eol,-blank-at-eof,-space-before-tab,tabwidth=4
```

Note `*.neon` deliberately omits `tab-in-indent` — phpstan generates tab-indented baselines, and that rule would flag every line of them.

## Phase 2 — the two gates

| # | Step | File | Status |
|---|---|---|---|
| 6 | Write the new workflow | `.github/workflows/ci.yml` | Not started |

Replace the file entirely:

```yaml
name: CI

# `master` is this repository's default branch. This said `main` until
# 2026-09-16, so no push to the default branch had ever triggered a run —
# only pull requests did.
on:
  pull_request:
  push:
    branches: [ master ]

jobs:
  build-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer

      - name: Install dependencies
        run: composer install --no-interaction --no-progress

      - name: Coding standards (Drupal)
        run: vendor/bin/phpcs --standard=Drupal,DrupalPractice docroot/modules/custom docroot/themes/custom

      # Configured by phpstan.neon at level 2. This ran at the implicit level 0
      # until 2026-09-16, which checks little beyond "does it parse".
      - name: Static analysis
        run: vendor/bin/phpstan analyse --no-progress

      # There is deliberately no test step. There were no tests, phpunit was
      # not installed, and the step read:
      #
      #   vendor/bin/phpunit docroot/modules/custom || true
      #
      # so every run logged "vendor/bin/phpunit: No such file or directory" and
      # reported green. A step that cannot fail is worse than no step: it reads
      # as coverage. Restore it — without `|| true`, and with drupal/core-dev
      # in require-dev — on the day a test exists.

  accessibility:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      # Pure arithmetic over the design tokens: no site, no browser, no
      # dependencies. Exits non-zero on any pair below its threshold.
      #
      # There is no baseline file because there is no backlog — the palette was
      # brought to 19 pairs, 0 failing before this gate was added. Fixing first
      # and gating second is what lets the gate be zero-tolerance.
      - name: Colour contrast (WCAG 1.4.3, 1.4.11)
        run: python3 scripts/contrast.py

      - name: Set up Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'

      # The theme ships compiled CSS. An SCSS edit that is never built produces
      # a convincing diff and no change on the page — which happened during the
      # contrast work. Rebuilding and diffing is the only way to know the
      # artefact matches its source.
      - name: CSS artefacts are in sync with their source
        working-directory: docroot/themes/custom/flavourful
        run: |
          npm ci
          npm run build
          if ! git diff --quiet -- css/; then
            echo "::error::css/ is stale — run 'npm run build' in the theme and commit the result."
            git diff --stat -- css/
            exit 1
          fi

# Not here, and the reason is the stack rather than the effort:
#
#   * scripts/a11y-sweep.py needs the site running. Bringing one up means
#     `drush site-install --existing-config`, and this site's config declares
#     eleven Cohesion (Site Studio) modules, which need Acquia API credentials,
#     plus search_api_solr, which needs a Solr service.
#   * axe-core and pa11y-ci need the same running site, and then need content:
#     against an empty install /chefs has no rows and /glossary no letters, so
#     they would pass trivially and prove nothing.
#
# Until that is worth building, a11y-sweep.py is run by hand against DDEV and
# its result recorded in docs/actual-outcomes/accessibility-audit.md. The limit
# is written down there too, so the audit is not mistaken for a scan.
```

## Phase 3 — document the limit

| # | Step | File | Status |
|---|---|---|---|
| 7 | Add a **What the CI gate does and does not cover** section immediately **before** `## Remediation record` | `docs/actual-outcomes/accessibility-audit.md` | Not started |
| 8 | In the *Limits of this audit* list, replace the bullet beginning *"Automated scanning is not yet in CI"* with: `- **Only part of this audit can be automated, and the reason is the stack.** See *What the CI gate does and does not cover* below.` | same | Not started |

The section text is the fourth Notion bullet — *"document explicitly what automation cannot catch"* — discharged. Full text is in `…/scratchpad/ci-backup/docs/actual-outcomes/accessibility-audit.md`; its substance is the table of four gates that run, the two that cannot (`a11y-sweep.py` and axe/pa11y, both needing a running site plus content), and the permanent gap: **these gates check structure, not truth.** `aria-current="page"` asserting the wrong location site-wide is valid, well-formed ARIA that every scanner accepts. Roughly six of the twenty-five defects were machine-detectable.

## Phase 4 — commit

| # | Step | Status |
|---|---|---|
| 9 | Commit, push, open the PR. **The PR exercises the new workflow on itself** | Not started |

---

## Verification

**Run each check before changing anything**, so the baseline is real.

```bash
# 1. phpstan: level 0 today (near no-op), level 2 with the config.
ddev exec "vendor/bin/phpstan analyse docroot/modules/custom docroot/themes/custom --level=2 --no-progress"
# before config: 3 errors     after config + baseline: [OK] No errors
ddev exec "vendor/bin/phpstan analyse --no-progress"

# 2. Contrast gate — must exit 0 and report 0 failing.
python3 scripts/contrast.py; echo "exit=$?"
# expect: 19 pairs, 0 failing / exit=0

# 3. Workflow YAML parses.
ddev exec "vendor/bin/yaml-lint .github/workflows/ci.yml"
# expect: [OK] All 1 YAML files contain valid syntax.
```

### Prove the CSS gate can fail

A check that cannot fail is not a check — which is the whole subject of this plan, so the gate must be tested in **both** directions.

```bash
cd docroot/themes/custom/flavourful

# Direction 1 — in sync
npm run build && git diff --quiet -- css/ && echo "PASS (correct)"

# Direction 2 — stale. Change a token VALUE, build, and expect a diff.
sed -i '' 's/\$color-rule-control: #7a7263;/$color-rule-control: #7a7264;/' scss/abstracts/_variables.scss
npm run build
git diff --quiet -- css/ || echo "FAILS correctly"
git diff --stat -- css/

# revert
cd - && git checkout -- docroot/themes/custom/flavourful/scss/abstracts/_variables.scss
cd docroot/themes/custom/flavourful && npm run build && cd -
git status --short docroot/themes/custom/flavourful   # expect empty
```

**Use a token value, not a comment.** A comment compiles away under `--style=compressed`, so the gate correctly reports no change and you learn nothing — that mistake was made once already (deviation 1).

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | Verification | Appending a comment to a SCSS file would make the CSS gate fail | It did not, twice over: the first attempt checked the diff **without running the build**, and a comment compiles away under `--style=compressed` anyway | Retested with a real token value and a build; the gate fails correctly. Written into the verification block above. **Testing a guard rail is not optional when the guard rail's whole purpose is to catch a silent no-op** |
| 2 | 9 | `git push` would work | Rejected: *"refusing to allow an OAuth App to create or update workflow `.github/workflows/ci.yml` without `workflow` scope"*. The token carries `gist, read:org, repo` | Added the `gh auth refresh` prerequisite at the top. It surfaces only at push time, after all the work is done |
| 3 | 9 | `git commit` would succeed | `error: 1Password: failed to fill whole buffer / fatal: failed to write commit object` — the signing agent needed unlocking | Retried; it succeeded. Transient, but worth knowing the failure mode reads like a git error rather than an auth prompt |

---

## Out of scope

- **axe-core and pa11y-ci in CI.** Blocked by the stack: a running site needs `site-install --existing-config`, and this config declares eleven Site Studio modules (Acquia API credentials) plus `search_api_solr` (a Solr service) — and then content, without which `/chefs` and `/glossary` are empty and a scan passes trivially.
- **`scripts/a11y-sweep.py` in CI** — same reason. It stays a hand-run check against DDEV, and the audit says so.
- **Playwright keyboard and focus-order tests** — the third Notion bullet. Same running-site problem.
- **Installing `drupal/core-dev` and writing tests.** A real gap, but a separate piece of work; this plan removes a fake test step rather than pretending to fill it.
- **The `button` atom (O-4) and the five block config exports** — tracked in [`plan-a11y-global-and-carried.md`](plan-a11y-global-and-carried.md).
