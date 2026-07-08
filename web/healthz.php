<?php

// Deliberately doesn't bootstrap WordPress, so this reflects nginx<->php-fpm
// health, not backend availability.
http_response_code(200);
header('Content-Type: text/plain');
echo 'ok';
