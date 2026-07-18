<?php

require __DIR__ . '/helpers.php';

api_headers();

json_response([
    'ok' => true,
    'message' => 'API NominaBioQTime activa.',
]);

