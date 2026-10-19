<?php

if (PHP_SAPI === 'cli' || !defined('WP_CONTENT_DIR')) {
    return;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    return;
}

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';
if (preg_match('#^/(wp-admin|wp-login\\.php|wp-json|wp-cron\\.php)#', $path)) {
    return;
}

$query = parse_url($uri, PHP_URL_QUERY) ?? '';
if ($query !== '' && preg_match('/(^|&)(preview|nocache)=/', $query)) {
    return;
}

foreach ($_COOKIE ?? [] as $name => $value) {
    if (strpos($name, 'wordpress_logged_in_') === 0 || strpos($name, 'wp-postpass_') === 0) {
        return;
    }
}

$cache_dir = WP_CONTENT_DIR . '/cache/acervo';
$cache_file = $cache_dir . '/' . md5($uri) . '.html';
$ttl = 180;

if (is_file($cache_file) && (time() - filemtime($cache_file)) < $ttl) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Acervo-Cache: HIT');
    readfile($cache_file);
    exit;
}

ob_start(function ($html) use ($cache_file, $cache_dir) {
    if (!function_exists('http_response_code') || http_response_code() !== 200) {
        return $html;
    }
    if (strlen($html) < 800 || stripos($html, '<html') === false) {
        return $html;
    }
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    file_put_contents($cache_file, $html, LOCK_EX);
    header('X-Acervo-Cache: MISS');
    return $html;
});
