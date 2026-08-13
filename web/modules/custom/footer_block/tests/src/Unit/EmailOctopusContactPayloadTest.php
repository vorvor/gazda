<?php

declare(strict_types=1);

use Drupal\footer_block\EmailOctopusContactPayload;

require dirname(__DIR__, 3) . '/src/EmailOctopusContactPayload.php';

$payload = EmailOctopusContactPayload::create('secret', 'subscriber@example.com');

assert($payload === [
  'api_key' => 'secret',
  'email_address' => 'subscriber@example.com',
  'status' => 'SUBSCRIBED',
  'tags' => ['setaljbe'],
]);

print "PASS: subscription payload is subscribed and tagged setaljbe.\n";
