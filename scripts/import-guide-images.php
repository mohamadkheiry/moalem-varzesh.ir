<?php
defined('WP_CLI') && WP_CLI || exit;

require_once ABSPATH . 'wp-admin/includes/image.php';
if (!wp_image_editor_supports(['mime_type' => 'image/webp'])) {
    WP_CLI::error('Enable GD or Imagick with WebP support in the PHP instance running WP-CLI.');
}
$catalog = dirname(__DIR__) . '/catalog';
$manifest = json_decode(file_get_contents($catalog . '/guide-visuals.json'), true, 512, JSON_THROW_ON_ERROR);
$legacy = json_decode(file_get_contents($catalog . '/editorial.json'), true, 512, JSON_THROW_ON_ERROR)['posts'];
$legacy_slugs = [];
foreach ($legacy as $record) {
    $legacy_slugs['legacy-' . $record['id']] = $record['slug'];
}

$posts = [];
foreach ($manifest['covers'] as $key => $alt) {
    $post = get_page_by_path($legacy_slugs[$key] ?? $key, OBJECT, 'post');
    if (!$post || $post->post_status !== 'publish'
        || (!isset($legacy_slugs[$key]) && get_post_meta($post->ID, '_mv_guide_key', true) !== $key)) {
        WP_CLI::error('Import the published guide first: ' . $key);
    }
    $posts[$key] = $post;
}

$alts = $manifest['covers'];
foreach ($manifest['inline'] as $key => $entry) {
    if (!isset($posts[$entry['article']]) || !preg_match('/^[a-f0-9]{24}\.webp$/D', $entry['replaces'])) {
        WP_CLI::error('Invalid inline image reference: ' . $key);
    }
    $body = $posts[$entry['article']]->post_content;
    if (!str_contains($body, $entry['replaces']) && !str_contains($body, '/mv-guide-visuals/' . $key . '-')) {
        WP_CLI::error('Run the source editorial repair before importing this inline photo: ' . $key);
    }
    $alts[$key] = $entry['alt'];
}
$hashes = [];
foreach ($alts as $key => $alt) {
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key) || !is_string($alt) || trim($alt) === '') {
        WP_CLI::error('Invalid editorial image entry: ' . $key);
    }
    $source = "$catalog/guide-images/$key.webp";
    $dimensions = is_file($source) ? wp_getimagesize($source) : false;
    if (!$dimensions || $dimensions[0] !== 1200 || $dimensions[1] !== 800 || $dimensions['mime'] !== 'image/webp') {
        WP_CLI::error('Expected a 1200 × 800 WebP image: ' . $key);
    }
    $hashes[$key] = hash_file('sha256', $source);
}
if (count(array_unique($hashes)) !== count($hashes)) {
    WP_CLI::error('Two editorial images have identical content.');
}

$uploads = wp_upload_dir();
$directory = $uploads['basedir'] . '/mv-guide-visuals';
if ($uploads['error'] || !wp_mkdir_p($directory)) {
    WP_CLI::error('Could not prepare the editorial uploads folder.');
}

// Add a 480px candidate for editorial cards without creating derivatives for product uploads.
$sizes = static function (array $sizes): array {
    $sizes = array_intersect_key($sizes, array_flip(['thumbnail', 'medium', 'medium_large', 'large']));
    $sizes['mv_guide_card'] = ['width' => 480, 'height' => 320, 'crop' => false];
    return $sizes;
};
add_filter('intermediate_image_sizes_advanced', $sizes);
$attachments = [];
foreach ($alts as $key => $alt) {
    $filename = $key . '-' . substr($hashes[$key], 0, 12) . '.webp';
    $file = "$directory/$filename";
    if ((!is_file($file) || hash_file('sha256', $file) !== $hashes[$key])
        && !copy("$catalog/guide-images/$key.webp", $file)) {
        WP_CLI::error('Could not store the image: ' . $key);
    }
    $matches = get_posts([
        'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids',
        'meta_key' => '_wp_attached_file', 'meta_value' => 'mv-guide-visuals/' . $filename,
    ]);
    $id = (int) ($matches[0] ?? 0);
    if (!$id) {
        $post = $posts[$key] ?? $posts[$manifest['inline'][$key]['article']];
        $id = wp_insert_attachment([
            'post_mime_type' => 'image/webp', 'post_title' => $alt, 'post_status' => 'inherit',
        ], $file, $post->ID, true);
        if (is_wp_error($id)) {
            WP_CLI::error($id->get_error_message());
        }
    }
    $metadata = wp_get_attachment_metadata($id);
    if (empty($metadata['sizes']['mv_guide_card']) || empty($metadata['sizes']['medium_large']) || empty($metadata['sizes']['large'])) {
        $metadata = wp_generate_attachment_metadata($id, $file);
        if (empty($metadata['sizes']['mv_guide_card']) || empty($metadata['sizes']['medium_large']) || empty($metadata['sizes']['large'])) {
            WP_CLI::error('WordPress could not generate responsive sizes: ' . $key);
        }
        wp_update_attachment_metadata($id, $metadata);
    }
    update_post_meta($id, '_wp_attachment_image_alt', $alt);
    update_post_meta($id, '_mv_guide_visual_key', $key);
    $attachments[$key] = $id;
}
remove_filter('intermediate_image_sizes_advanced', $sizes);

$replace = [];
$inline_by_article = [];
foreach ($manifest['inline'] as $key => $entry) {
    $replace[$entry['article']][$entry['replaces']] = $attachments[$key];
    $inline_by_article[$entry['article']][$key] = $attachments[$key];
}
$retired = [
    'b0ff5a247de61efab75c3710.webp', 'd08764e6aaf5fb17003c1828.webp', '0be4730628e69313f055a222.webp',
    '5131d3edc7fb2a39bd72c04a.webp', '92d84dd234ccb32b3408b57b.webp',
];
foreach ($posts as $key => $post) {
    if (get_post_thumbnail_id($post->ID) !== $attachments[$key] && !set_post_thumbnail($post->ID, $attachments[$key])) {
        WP_CLI::error('Could not set the featured image: ' . $key);
    }
    update_post_meta($post->ID, '_mv_editorial_image_alt', $manifest['covers'][$key]);
    if (empty($replace[$key])) {
        continue;
    }
    $footwear = $key === 'legacy-12377';
    $original_images = $replace[$key];
    $published_images = $inline_by_article[$key];
    $content = preg_replace_callback('~<img\b[^>]*>~i', static function (array $image) use ($original_images, $published_images, $footwear, $retired): string {
        $tag = new WP_HTML_Tag_Processor($image[0]);
        $tag->next_tag('IMG');
        $src = $tag->get_attribute('src');
        $path = is_string($src) ? (string) wp_parse_url($src, PHP_URL_PATH) : '';
        $name = wp_basename($path);
        if ($footwear && (!$name || in_array($name, $retired, true))) {
            return '';
        }
        $attachment = $original_images[$name] ?? 0;
        if (str_contains($path, '/mv-guide-visuals/') && preg_match('/^(.+)-[a-f0-9]{12}(?:-\d+x\d+)?\.webp$/D', $name, $asset)) {
            $attachment = $published_images[$asset[1]] ?? 0;
        }
        return $attachment ? wp_get_attachment_image($attachment, 'large', false, [
            'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 768px) 680px, 92vw',
        ]) : $image[0];
    }, $post->post_content);
    if ($footwear) {
        $content = preg_replace('~<(figure|h2|p)\b[^>]*>\s*</\1>~i', '', $content);
    }
    if ($content !== $post->post_content) {
        $saved = wp_update_post(['ID' => $post->ID, 'post_content' => wp_slash($content)], true);
        if (is_wp_error($saved)) {
            WP_CLI::error($saved->get_error_message());
        }
    }
}
if (function_exists('wp_cache_clear_cache')) {
    wp_cache_clear_cache(is_multisite() ? get_current_blog_id() : 0);
}
WP_CLI::success(sprintf('Set %d original featured photos and %d in-article photos.', count($posts), count($manifest['inline'])));
