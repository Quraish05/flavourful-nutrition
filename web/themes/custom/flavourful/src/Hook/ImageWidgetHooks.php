<?php

namespace Drupal\flavourful\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Keeps an inaccessible image preview out of the widget's markup.
 *
 * Carried over verbatim from core's StarterkitThemeHooks, and kept because it
 * still fires here. Node forms go to the admin theme via node.settings, but the
 * user form follows the usual rule: the admin theme only for roles holding
 * "view the administration theme", which neither `authenticated` nor `chef`
 * does. user_picture is an image field on that form, so /user/N/edit renders
 * this widget in this theme for every non-administrator editing their profile.
 * Verified: as uid 2 the form comes back with 119 flavourful asset references
 * and an image-widget; as uid 1 it is Claro.
 */
class ImageWidgetHooks {

  /**
   * Implements hook_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

}
