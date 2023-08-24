<?php
namespace Drupal\populate_image_field\Commands;

use Drush\Commands\DrushCommands;

/**
 * Custom Drush command for getting the first image from body and placing it
 * in the image field.
 *
 * @DrushCommand
 */
class PopulateImageFieldCommand extends DrushCommands {


  /**
   * Gets the first image. Puts it in Image Field
   *
   * @command populate-image-field
   * @aliases pif
   */
  public function populateImageField() {
    // Get the EntityTypeManager service.
    $entity_type_manager = \Drupal::service('entity_type.manager');

    // Get all nodes of the "article" content type.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'article')
      ->accessCheck(FALSE); // Bypass access checks.
    $nids = $query->execute();

    foreach ($nids as $nid) {
      $node = \Drupal\node\Entity\Node::load($nid);

      // Check if the node has a body field and it's not empty.
      if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
        $body_value = $node->get('body')->value;

        // Get the media UUID from the body field.
        $media_uuid = populate_image_field_extract_media_uuid($body_value);

        if ($media_uuid) {
          // Load the media entity using the UUID.
          $media = $entity_type_manager->getStorage('media')->loadByProperties(
            ['uuid' => $media_uuid]
          );
          if (!empty($media)) {
            $media = reset($media);

            // Check if the media is valid and belongs to the media bundle you're expecting.
            if ($media && $media->bundle() == 'image') {
              // Set field_image_media and save node.
              $node->set('field_image_media', $media->id());
              $node->save();

              // Replace only the first <drupal-media> tag with an empty string.
              $body_value = preg_replace(
                '/<drupal-media[^>]*>.*?<\/drupal-media>/',
                '',
                $body_value,
                1
              );

              // Update the node's body value.
              $node->set('body', [
                'value' => $body_value,
                'format' => 'basic_html', // Set the desired format here
              ]);
              $node->save();
            }
          }
        }
      }
    }
  }

  /**
   * Helper function to extract the media UUID from the body text.
   */
  function populate_image_field_extract_media_uuid($body_value) {
    // Use a regular expression to find the data-entity-uuid attribute.
    if (preg_match('/data-entity-uuid="([^"]+)"/', $body_value, $matches)) {
      return $matches[1];
    }
    return NULL;
  }

}

