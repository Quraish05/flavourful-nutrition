/**
 * @file
 * Writes today's date into the utility bar, in the visitor's browser.
 *
 * The page cache keeps anonymous pages indefinitely, so a date rendered on the
 * server would still be showing the next day. Writing it here keeps the cached
 * page date-free.
 */
((Drupal, once) => {
  Drupal.behaviors.utilityDate = {
    attach(context) {
      once("utility-date", "[data-utility-date]", context).forEach((el) => {
        const now = new Date();
        // "Friday, 16 October 2026", the same shape PHP's 'l, j F Y' gave.
        const parts = Object.fromEntries(
          new Intl.DateTimeFormat("en-GB", {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric",
          })
            .formatToParts(now)
            .map(({ type, value }) => [type, value]),
        );
        el.textContent = `${parts.weekday}, ${parts.day} ${parts.month} ${parts.year}`;
        // The machine-readable date for the <time> element, e.g. 2026-10-16.
        el.dateTime = [
          now.getFullYear(),
          String(now.getMonth() + 1).padStart(2, "0"),
          String(now.getDate()).padStart(2, "0"),
        ].join("-");
        el.hidden = false;
      });
    },
  };
})(Drupal, once);
