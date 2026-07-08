<?php

// Readiness/liveness probe target. Intentionally does not bootstrap
// WordPress (no DB/Redis round-trip) so it reflects nginx<->php-fpm health,
// not backend availability.
http_response_code(200);
header('Content-Type: text/plain');
echo 'ok';
