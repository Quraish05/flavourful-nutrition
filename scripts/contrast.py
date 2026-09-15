#!/usr/bin/env python3
"""WCAG contrast check for the Flavourful design tokens.

Computes contrast ratios with the WCAG relative-luminance formula and checks
each pair against the threshold for its criterion:

    1.4.3 Contrast (Minimum)   4.5:1 for normal text, 3:1 for large text
    1.4.11 Non-text Contrast   3:1 for UI component boundaries and states

The pairs below name the *actual painted background* for each use, not white.
That distinction is the whole point: an earlier pass measured the difficulty
badges against white and found two failures, because at the time the badge was
white text on a filled chip. The redesign made it coloured text on the dark
ground, and all four now pass -- against a different background. A contrast
figure without its background is not a measurement.

1.4.11 applies to the *boundaries of user interface components* -- a field's
border qualifies, a decorative rule between sections does not. The `criterion`
column records which is which, so raising a token does not quietly restyle
things that were never in scope.

Tokens come from docroot/themes/custom/flavourful/scss/abstracts/_variables.scss.
When that file changes, update PALETTE and re-run.

Usage:
    python3 scripts/contrast.py [--all]
"""

import argparse
import sys

PALETTE = {
    "ink": "#17140f",          # page ground
    "ink-raised": "#1e1a14",   # cards, callouts
    "ink-deep": "#0e0c09",     # form fields
    "brass": "#c9a24a",
    "brass-bright": "#e0bb68",
    "brass-dim": "#8a6f33",
    "bone": "#ede7da",
    "bone-muted": "#a9a294",
    "bone-faint": "#6f6a5f",
    "rule": "#302a21",
    "rule-strong": "#453d30",
    "difficulty-easy": "#7f9e5c",
    "difficulty-medium": "#c9a24a",
    "difficulty-hard": "#c2703c",
    "difficulty-expert": "#d4604a",
}

# (label, foreground token, background token, threshold, criterion)
PAIRS = [
    ("body text", "bone", "ink", 4.5, "1.4.3"),
    ("muted text", "bone-muted", "ink", 4.5, "1.4.3"),
    ("muted text on card", "bone-muted", "ink-raised", 4.5, "1.4.3"),
    ("faint text", "bone-faint", "ink", 4.5, "1.4.3"),
    ("faint text on card", "bone-faint", "ink-raised", 4.5, "1.4.3"),
    ("input placeholder", "bone-faint", "ink-deep", 4.5, "1.4.3"),
    ("input text", "bone", "ink-deep", 4.5, "1.4.3"),
    ("link", "brass", "ink", 4.5, "1.4.3"),
    ("link hover", "brass-bright", "ink", 4.5, "1.4.3"),
    ("link underline", "brass-dim", "ink", 3.0, "1.4.11"),
    ("focus outline", "brass", "ink", 3.0, "1.4.11"),
    ("control border vs field", "rule-strong", "ink-deep", 3.0, "1.4.11"),
    ("control border vs page", "rule-strong", "ink", 3.0, "1.4.11"),
    ("difficulty easy", "difficulty-easy", "ink", 4.5, "1.4.3"),
    ("difficulty medium", "difficulty-medium", "ink", 4.5, "1.4.3"),
    ("difficulty hard", "difficulty-hard", "ink", 4.5, "1.4.3"),
    ("difficulty expert", "difficulty-expert", "ink", 4.5, "1.4.3"),
]

# Decorative, so 1.4.11 does not apply. Reported only under --all, to keep the
# default output to things that are actually owed.
DECORATIVE = [
    ("section rule (decorative)", "rule", "ink", 3.0, "n/a"),
]


def luminance(hex_colour):
    """WCAG relative luminance for an #rrggbb string."""
    h = hex_colour.lstrip("#")
    channels = [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    linear = [
        c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4
        for c in channels
    ]
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]


def ratio(fg, bg):
    """Contrast ratio between two #rrggbb strings, lighter over darker."""
    lighter, darker = sorted((luminance(fg), luminance(bg)), reverse=True)
    return (lighter + 0.05) / (darker + 0.05)


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "--all",
        action="store_true",
        help="include decorative pairs that are not owed a threshold",
    )
    args = ap.parse_args()

    pairs = PAIRS + (DECORATIVE if args.all else [])
    failures = 0

    print(f"{'use':28} {'fg':16} {'on':12} {'ratio':>6} {'need':>5}  {'sc':7} verdict")
    print("-" * 82)
    for label, fg_key, bg_key, need, criterion in pairs:
        fg, bg = PALETTE[fg_key], PALETTE[bg_key]
        r = ratio(fg, bg)
        if criterion == "n/a":
            verdict = "n/a"
        elif r >= need:
            verdict = "PASS"
        else:
            verdict = "FAIL"
            failures += 1
        print(
            f"{label:28} {fg_key:16} {bg_key:12} "
            f"{r:6.2f} {need:5.1f}  {criterion:7} {verdict}"
        )

    print()
    print(f"{len(pairs)} pairs, {failures} failing")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
