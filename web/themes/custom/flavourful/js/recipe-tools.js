/**
 * @file
 * Recipe tools: a servings scaler and a Cook Mode overlay.
 *
 * - Scaler: multiplies every [data-qty] amount by target/base servings.
 * - Cook Mode: full-screen step-by-step walk-through that keeps the screen
 *   awake via the Wake Lock API (feature-detected; degrades silently).
 */
((Drupal, once) => {
  /**
   * Formats a scaled amount: max 2 decimals, no trailing zeros.
   */
  const format = (value) => {
    const rounded = Math.round(value * 100) / 100;
    return String(rounded);
  };

  const initScaler = (root) => {
    const base = parseInt(root.dataset.baseServings, 10) || 4;
    const output = root.querySelector('[data-servings]');
    const note = root.querySelector('[data-scale-note]');
    const amounts = root.querySelectorAll('[data-qty]');
    let current = base;

    const render = () => {
      const factor = current / base;
      if (output) {
        output.textContent = current;
      }
      amounts.forEach((el) => {
        const baseQty = parseFloat(el.dataset.qty);
        const unit = el.dataset.unit || '';
        // Rebuild the text exactly as the ingredient-list component rendered it
        // server-side: "<qty> <unit>", space only when there is a unit. Without
        // the space the first click would reflow every row from "40 cloves" to
        // "40cloves".
        el.textContent = unit ? `${format(baseQty * factor)} ${unit}` : format(baseQty * factor);
        // Marks the amounts the stepper has changed, so it is visible which
        // numbers are no longer the ones the recipe was written for.
        el.classList.toggle('is-scaled', factor !== 1);
      });
      if (note) {
        note.hidden = factor === 1;
        note.textContent = Drupal.t('Amounts scaled ×@f (written for @b servings).', {
          '@f': format(factor),
          '@b': base,
        });
      }
    };

    root.querySelector('[data-servings-dec]')?.addEventListener('click', () => {
      current = Math.max(1, current - 1);
      render();
    });
    root.querySelector('[data-servings-inc]')?.addEventListener('click', () => {
      current = Math.min(99, current + 1);
      render();
    });
    render();
  };

  const initCookMode = (root) => {
    const startBtn = root.querySelector('[data-cook-start]');
    const panel = root.querySelector('[data-cook-panel]');
    if (!startBtn || !panel) {
      return;
    }
    // Reveal the trigger only now that JS is running.
    startBtn.hidden = false;

    const steps = Array.from(panel.querySelectorAll('[data-step]'));
    const progress = panel.querySelector('[data-cook-progress]');
    const prev = panel.querySelector('[data-cook-prev]');
    const next = panel.querySelector('[data-cook-next]');
    let index = 0;
    let wakeLock = null;

    const paint = () => {
      steps.forEach((step, i) => step.classList.toggle('is-active', i === index));
      if (progress) {
        progress.textContent = Drupal.t('Step @n of @t', { '@n': index + 1, '@t': steps.length });
      }
      if (prev) {
        prev.disabled = index === 0;
      }
      if (next) {
        next.disabled = index === steps.length - 1;
      }
    };

    const acquireWakeLock = async () => {
      if (!('wakeLock' in navigator)) {
        return;
      }
      try {
        wakeLock = await navigator.wakeLock.request('screen');
      }
      catch (e) {
        // Denied or unsupported context — Cook Mode still works, screen may dim.
      }
    };
    const releaseWakeLock = () => {
      wakeLock?.release?.();
      wakeLock = null;
    };

    const open = () => {
      index = 0;
      panel.hidden = false;
      document.documentElement.classList.add('cook-mode-active');
      paint();
      acquireWakeLock();
    };
    const close = () => {
      panel.hidden = true;
      document.documentElement.classList.remove('cook-mode-active');
      releaseWakeLock();
    };
    const go = (delta) => {
      index = Math.min(steps.length - 1, Math.max(0, index + delta));
      paint();
    };

    startBtn.addEventListener('click', open);
    panel.querySelector('[data-cook-end]')?.addEventListener('click', close);
    prev?.addEventListener('click', () => go(-1));
    next?.addEventListener('click', () => go(1));

    document.addEventListener('keydown', (e) => {
      if (panel.hidden) {
        return;
      }
      if (e.key === 'Escape') {
        close();
      }
      else if (e.key === 'ArrowRight') {
        go(1);
      }
      else if (e.key === 'ArrowLeft') {
        go(-1);
      }
    });
    // Browsers drop the wake lock when the tab is backgrounded; re-acquire it.
    document.addEventListener('visibilitychange', () => {
      if (!panel.hidden && document.visibilityState === 'visible') {
        acquireWakeLock();
      }
    });
  };

  Drupal.behaviors.recipeTools = {
    attach(context) {
      once('recipe-tools', '[data-recipe-tools]', context).forEach((root) => {
        initScaler(root);
        initCookMode(root);
      });
    },
  };
})(Drupal, once);
