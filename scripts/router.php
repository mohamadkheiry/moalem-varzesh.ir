<?php
// Router for the loopback-only PHP development server.
$root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$request = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_contains($request, "\0")) {
    http_response_code(400);
    return true;
}
$file = realpath($root . '/' . ltrim($request, '/'));
if ($file !== false) {
    $file = str_replace('\\', '/', $file);
    if (str_starts_with($file, $root . '/') && is_file($file)) {
        $types = [
            'webp' => 'image/webp', 'avif' => 'image/avif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif', 'ico' => 'image/x-icon', 'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2', 'css' => 'text/css; charset=UTF-8', 'js' => 'text/javascript; charset=UTF-8',
        ];
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (isset($types[$extension]) && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)
            && preg_match('~^/(?:wp-content/(?:themes|plugins|uploads)|wp-includes/(?:css|js|fonts|images))/~', $request)) {
            $text = in_array($extension, ['css', 'js', 'svg'], true);
            $length = filesize($file);
            $etag = 'W/"' . dechex(filemtime($file)) . '-' . dechex($length) . '"';
            $versioned = in_array($extension, ['css', 'js'], true) && !empty($_GET['ver']);
            header('Content-Type: ' . $types[$extension]);
            header('Cache-Control: ' . ($versioned ? 'public, max-age=31536000, immutable' : 'public, max-age=3600'));
            header('ETag: ' . $etag);
            if ($text) header('Vary: Accept-Encoding');
            if (in_array($etag, array_map('trim', explode(',', $_SERVER['HTTP_IF_NONE_MATCH'] ?? '')), true)) {
                http_response_code(304);
                return true;
            }
            if (!$text) header('Content-Length: ' . $length);
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if ($text) ob_start('ob_gzhandler');
                readfile($file);
            }
            return true;
        }
        return false;
    }
}
if (str_starts_with($request, '/wp-admin/') && is_dir($root . $request) && is_file($root . $request . '/index.php')) {
    require $root . $request . '/index.php';
    return true;
}
require $root . '/index.php';
