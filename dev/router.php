<?php
/** Loopback-only development router. Run with -t <isolated site>. */
$peer = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($peer, ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Local preview only.'); }
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(?:^|/)\.{1,2}(?:/|$)|/(?:\.git|\.local|\.work|vendor|node_modules)(?:/|$)|^/site/(?:config[^/]*|assets/(?:Mailbox|tickets|sessions|cache|logs))(?:/|$)|^/wire/core(?:/|$)~i', $path)) {
    http_response_code(404); exit('Not found.');
}
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$file = $root . $path;
if (is_file($file) && preg_match('~\.(?:css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|otf)$~i', $path)) return false;
if (preg_match('~\.[a-z0-9]{1,8}$~i', $path) && !str_ends_with($path, '/index.php')) { http_response_code(404); exit('Not found.'); }
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') { $_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = 443; }
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
