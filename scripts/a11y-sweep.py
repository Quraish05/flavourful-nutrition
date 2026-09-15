#!/usr/bin/env python3
"""Whole-site accessibility sweep for the Flavourful theme.

Fetches each route and reports the structural facts an audit needs: heading
outline and skipped levels, landmark inventory with accessible names, links
with no name, nested anchors, aria-current counts, table caption/scope/sort,
lang, and page title.

This reads *rendered source*, which is the right layer for everything it
checks -- and the wrong layer for two classes of defect it therefore cannot
see:

  * anything the HTML parser creates (nested anchors are split into siblings,
    so the empty link exists in the DOM and not in the source), and
  * anything JavaScript writes after load.

For those, drive a real browser. The nested-anchor count below is a proxy: it
finds the *cause* in source rather than the empty link in the DOM, which is
why it is reported separately from the empty-link count.

Two known false positives, both verified harmless:

  * `<a id="main-content" tabindex="-1"></a>` -- the skip-link target. No href,
    so it maps to `generic`, not `link`. Excluded below.
  * a `<header>` inside `<main>` -- not a `banner` landmark, because HTML-AAM
    scopes `<header>` to banner only outside article/aside/main/nav/section.
    The landmark list marks these rather than counting them.

Usage:
    python3 scripts/a11y-sweep.py [--base URL] [--json]
"""

import argparse
import json
import re
import subprocess
import sys

DEFAULT_BASE = "https://foodrecipes-drupal.ddev.site:33001"

ROUTES = [
    "/",
    "/recipes",
    "/recipe-search",
    "/glossary",
    "/glossary/s",
    "/chefs",
    "/reports/by-cuisine",
    "/node/67",
    "/node/999999",
]

# Elements that are landmarks, or that need checking for whether they are.
LANDMARK_TAGS = ("aside", "nav", "header", "footer", "main", "section", "form")

# HTML-AAM: <header>/<footer> are banner/contentinfo only outside these.
SECTIONING = ("article", "aside", "main", "nav", "section")


def fetch(base, route):
    """Return (body, status) for one route, or (None, error) on failure."""
    proc = subprocess.run(
        ["curl", "-sk", "-w", "\n%{http_code}", base + route],
        capture_output=True,
        text=True,
    )
    if proc.returncode != 0:
        return None, proc.stderr.strip() or f"curl exit {proc.returncode}"
    body, _, status = proc.stdout.rpartition("\n")
    return body, status.strip()


def text_of(html):
    """Strip tags and collapse whitespace."""
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", html)).strip()


def headings(body):
    """Return [(level, text)] in document order."""
    return [
        (lvl, text_of(inner))
        for lvl, inner in re.findall(r"<(h[1-6])[^>]*>(.*?)</\1>", body, re.S)
    ]


def skips(levels):
    """Return ['h2->h4', ...] for every jump of more than one level."""
    found, prev = [], 0
    for lvl in levels:
        n = int(lvl[1])
        if prev and n > prev + 1:
            found.append(f"h{prev}->h{n}")
        prev = n
    return found


def landmarks(body):
    """Return [(tag, name_or_None, in_main)] for landmark-capable elements."""
    out = []
    main_start = body.find("<main")
    main_end = body.find("</main>")
    for m in re.finditer(r"<(%s)\b[^>]*>" % "|".join(LANDMARK_TAGS), body):
        tag = m.group(1)
        attrs = m.group(0)
        label = re.search(r'aria-label="([^"]*)"', attrs)
        labelledby = re.search(r'aria-labelledby="([^"]*)"', attrs)
        name = label.group(1) if label else (
            "#" + labelledby.group(1) if labelledby else None
        )
        nested = -1 < main_start < m.start() < main_end
        out.append((tag, name, nested))
    return out


def links(body):
    """Return (total, unnamed) counting only elements that are really links.

    Three exclusions, each verified against a real false positive this script
    produced before they were added:

      * no `href` -- not a link at all. It maps to `generic`. This is the
        skip-link target, `<a id="main-content" tabindex="-1">`.
      * `aria-hidden="true"` -- removed from the accessibility tree, so it is
        not an unnamed link; it is not exposed. On a recipe card the media
        link is deliberately hidden this way (paired with `tabindex="-1"`)
        because it duplicates the title link.
      * `aria-label` -- has a name even when its content is empty.
    """
    total = unnamed = 0
    for tag, inner in re.findall(r"<a\b([^>]*)>(.*?)</a>", body, re.S):
        if "href=" not in tag:
            continue
        if 'aria-hidden="true"' in tag:
            continue
        total += 1
        if not text_of(inner) and "aria-label" not in tag:
            unnamed += 1
    return total, unnamed


def tables(body):
    """Return caption / th / scope / aria-sort counts for the first table."""
    m = re.search(r"<table.*?</table>", body, re.S)
    if not m:
        return None
    t = m.group(0)
    cap = re.search(r"<caption[^>]*>(.*?)</caption>", t, re.S)
    # <th\s...> so the pattern cannot also match <thead>.
    ths = re.findall(r"<th\s[^>]*>", t)
    return {
        "caption": text_of(cap.group(1)) if cap else None,
        "th": len(ths),
        "scoped": sum(1 for x in ths if "scope=" in x),
        "aria_sort": sum(1 for x in ths if "aria-sort=" in x),
    }


def audit(base, route):
    body, status = fetch(base, route)
    if body is None:
        return {"route": route, "error": status}
    levels = [lvl for lvl, _ in headings(body)]
    total, unnamed = links(body)
    lang = re.search(r"<html[^>]*\blang=\"([^\"]*)\"", body)
    title = re.search(r"<title>(.*?)</title>", body, re.S)
    return {
        "route": route,
        "status": status,
        "title": text_of(title.group(1)) if title else None,
        "lang": lang.group(1) if lang else None,
        "h1": [t for lvl, t in headings(body) if lvl == "h1"],
        "outline": levels,
        "skips": skips(levels),
        "landmarks": landmarks(body),
        "links": total,
        "unnamed_links": unnamed,
        "nested_anchors": len(
            re.findall(r"<a\b[^>]*>(?:(?!</a>).)*<a\b", body, re.S)
        ),
        "aria_current": len(re.findall(r"aria-current=", body)),
        "skip_link": "class=\"visually-hidden focusable skip-link\"" in body,
        "table": tables(body),
    }


def report(results):
    """Print a human-readable summary and return a process exit code."""
    problems = 0
    for r in results:
        if "error" in r:
            print(f"{r['route']:24} ERROR {r['error']}")
            problems += 1
            continue

        flags = []
        if r["skips"]:
            flags.append("skips=" + ",".join(r["skips"]))
        if len(r["h1"]) != 1:
            flags.append(f"h1x{len(r['h1'])}")
        if r["unnamed_links"]:
            flags.append(f"unnamed_links={r['unnamed_links']}")
        if r["nested_anchors"]:
            flags.append(f"nested_a={r['nested_anchors']}")
        if not r["lang"]:
            flags.append("no_lang")
        if not r["skip_link"]:
            flags.append("no_skip_link")

        banners = [
            t for t, _, nested in r["landmarks"] if t == "header" and not nested
        ]
        if len(banners) > 1:
            flags.append(f"banner x{len(banners)}")

        unnamed_lm = [
            t
            for t, name, nested in r["landmarks"]
            if t in ("aside", "nav") and not name
        ]
        if unnamed_lm:
            flags.append("unnamed_landmark=" + ",".join(unnamed_lm))

        if r["table"] and not r["table"]["caption"]:
            flags.append("table_no_caption")
        if r["table"] and r["table"]["scoped"] != r["table"]["th"]:
            flags.append("th_missing_scope")

        problems += len(flags)
        mark = "FAIL" if flags else "ok  "
        print(f"{mark} {r['route']:24} {r['status']}  {r['title'] or ''}")
        if flags:
            for f in flags:
                print(f"         - {f}")

    print()
    print(f"{len(results)} routes, {problems} flagged")
    return 1 if problems else 0


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--base", default=DEFAULT_BASE, help="site base URL")
    ap.add_argument("--json", action="store_true", help="emit raw JSON")
    args = ap.parse_args()

    results = [audit(args.base, route) for route in ROUTES]

    if args.json:
        print(json.dumps(results, indent=1))
        return 0
    return report(results)


if __name__ == "__main__":
    sys.exit(main())
