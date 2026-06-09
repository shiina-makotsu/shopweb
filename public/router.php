<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = __DIR__.$uri;

if ($uri !== '/' && is_file($path)) {
    return false;
}

require_once __DIR__.'/index.php';
