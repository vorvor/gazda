<?php
// Initialize/synchronize CSV registry without demo seeding.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/sync_names_db.php';
