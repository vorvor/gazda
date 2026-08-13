<?php

declare(strict_types=1);

namespace Drupal\footer_block;

/**
 * Builds EmailOctopus contact payloads for footer subscriptions.
 */
final class EmailOctopusContactPayload {

  /**
   * Builds a subscribed contact payload with the campaign tag.
   */
  public static function create(string $apiKey, string $email): array {
    return [
      'api_key' => $apiKey,
      'email_address' => $email,
      'status' => 'SUBSCRIBED',
      'tags' => ['setaljbe'],
    ];
  }

}
