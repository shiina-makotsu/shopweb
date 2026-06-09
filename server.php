<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = __DIR__.'/public'.$uri;

if ($uri !== '/' && is_file($path)) {
    return false;
}

require_once __DIR__.'/public/index.php';
