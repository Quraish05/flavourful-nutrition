/**
 * @file
 * Disclosure behaviour for the mega menu.
 *
 * Deliberately not hover-driven. Hover excludes keyboard users, touch users
 * and anyone using a screen magnifier, so the contract is click and keyboard
 * only; CSS may add hover later as an enhancement on top of a menu that
 * already works without it.
 *
 * Elements are found by data- attribute rather than class, so restyling the
 * component cannot silently detach the behaviour.
 */
((Drupal, once) => {
  "use strict";

  /** Closes a panel and, optionally, moves focus back to its trigger. */
  function close(trigger, panel, refocus) {
    trigger.setAttribute("aria-expanded", "false");
    panel.hidden = true;
    if (refocus) {
      trigger.focus();
    }
  }

  function open(trigger, panel) {
    trigger.setAttribute("aria-expanded", "true");
    panel.hidden = false;
  }

  Drupal.behaviors.flavourfulMegaMenu = {
    attach(context) {
      once("mega-menu", "[data-mega-menu-trigger]", context).forEach(
        (trigger) => {
          const panel = document.getElementById(
            trigger.getAttribute("aria-controls"),
          );
          if (!panel) {
            return;
          }

          const menu = trigger.closest(".mega-menu") || document;

          /** Closes every panel in this menu except the one passed in. */
          const closeOthers = (except) => {
            menu
              .querySelectorAll("[data-mega-menu-trigger]")
              .forEach((other) => {
                if (other === except) {
                  return;
                }
                const otherPanel = document.getElementById(
                  other.getAttribute("aria-controls"),
                );
                if (otherPanel) {
                  close(other, otherPanel, false);
                }
              });
          };

          trigger.addEventListener("click", () => {
            const isOpen = trigger.getAttribute("aria-expanded") === "true";
            closeOthers(trigger);
            if (isOpen) {
              close(trigger, panel, false);
            } else {
              open(trigger, panel);
            }
          });

          // Escape closes and returns focus to the trigger. Without the return,
          // focus is left orphaned in a hidden subtree — WCAG 2.1.2.
          const item = trigger.parentElement;
          item.addEventListener("keydown", (event) => {
            if (
              event.key === "Escape" &&
              trigger.getAttribute("aria-expanded") === "true"
            ) {
              event.stopPropagation();
              close(trigger, panel, true);
            }
          });

          // Clicking away closes, but only a click genuinely outside both the trigger and its panel.
          document.addEventListener("click", (event) => {
            if (trigger.getAttribute("aria-expanded") !== "true") {
              return;
            }
            if (
              !panel.contains(event.target) &&
              !trigger.contains(event.target)
            ) {
              close(trigger, panel, false);
            }
          });

          // Tabbing out of the panel closes it, so the menu does not sit open behind the rest of the page.
          panel.addEventListener("focusout", (event) => {
            if (
              !panel.contains(event.relatedTarget) &&
              event.relatedTarget !== trigger
            ) {
              close(trigger, panel, false);
            }
          });
        },
      );
    },
  };
})(Drupal, once);
