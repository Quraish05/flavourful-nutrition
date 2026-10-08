/**
 * @file
 * Exposes the facet soft-limit toggle's disclosure state.
 *
 * facets/soft-limit builds its "Show more" control in JavaScript — there is no
 * template to override — and never sets aria-expanded, so the five hidden
 * options appear with nothing announcing that anything changed. The control's
 * accessible name flips between "Show more" and "Show less", but a name is not
 * a state.
 *
 * The module signals open/closed by toggling an `open` class on the link, so
 * that class is mirrored rather than the click intercepted. Adding a second
 * click handler would make correctness depend on which handler jQuery happens
 * to run first; watching the attribute does not.
 *
 * Deliberately left as a link. role="button" would promise Space-key
 * activation that an <a> does not honour.
 */
((Drupal, once) => {
  const sync = (link) => {
    link.setAttribute(
      "aria-expanded",
      link.classList.contains("open") ? "true" : "false",
    );
  };

  Drupal.behaviors.flavourfulFacetSoftLimit = {
    attach(context) {
      once("facet-soft-limit-a11y", ".facets-soft-limit-link", context).forEach(
        (link) => {
          sync(link);
          new MutationObserver(() => sync(link)).observe(link, {
            attributes: true,
            attributeFilter: ["class"],
          });
        },
      );
    },
  };
})(Drupal, once);
