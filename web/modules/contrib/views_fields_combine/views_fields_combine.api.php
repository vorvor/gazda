<?php

/**
 * @file
 * Hooks provided by the Views Fields Combine module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the list of allowed tags used as field separator.
 *
 * @param array<string, array<int, string>> $allowed_tags
 *   List of tags allowed to use as field separator.
 */
function hook_views_fields_combine_seperator_allowed_tags_alter(array &$allowed_tags): void {
  // Add tag to make fields italic.
  $allowed_tags['default'][] = 'i';
}

/**
 * @} End of "addtogroup hooks".
 */
