((Drupal, once) => {
  Drupal.behaviors.recipeInteractions = {
    attach(context) {
      // once() returns only elements not yet processed in this context.
      once("recipe-toggle", ".recipe-card", context).forEach((card) => {
        const btn = card.querySelector(".recipe-card__toggle");
        if (!btn) return;
        btn.addEventListener("click", () => {
          card.classList.toggle("is-expanded");
        });
      });
    },
  };
})(Drupal, once);
