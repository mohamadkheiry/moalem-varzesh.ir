<?php
// Router for the loopback-only PHP development server.
$root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$request = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = realpath($root . '/' . ltrim($request, '/'));
if ($file !== false) {
    $file = str_replace('\\', '/', $file);
    if (str_starts_with($file, $root . '/') && is_file($file)) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, ['webp', 'jpg', 'jpeg', 'avif', 'png', 'gif', 'woff2', 'css', 'js', 'ico', 'svg'], true)) {
            header('Cache-Control: public, max-age=3600');
        }
        return false;
    }
}
if (str_starts_with($request, '/wp-admin/') && is_dir($root . $request) && is_file($root . $request . '/index.php')) {
    require $root . $request . '/index.php';
    return true;
}
require $root . '/index.php';
