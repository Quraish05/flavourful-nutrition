<?php

namespace Drupal\flavourful\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Gives the exposed filter form a name taken from the view it filters.
 *
 * Two hooks rather than one because the view is only reachable while the form
 * is being built: it lives on the FormState, which preprocess never sees. The
 * alter stashes the label on the form's render array, which is the one thing
 * both halves do see.
 */
class ExposedFormHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $view = $form_state->get('view');
    if ($view) {
      $form['#view_label'] = $view->getTitle() ?: $view->storage->label();
    }
  }

  /**
   * Implements hook_preprocess_views_exposed_form().
   *
   * Lifts the stashed label into a variable so the template can name its own
   * fieldset after the listing it filters.
   */
  #[Hook('preprocess_views_exposed_form')]
  public function preprocessViewsExposedForm(array &$variables): void {
    $variables['filter_label'] = $variables['form']['#view_label'] ?? NULL;
  }

}
