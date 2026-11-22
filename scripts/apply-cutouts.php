<?php
defined('WP_CLI') && WP_CLI || exit;

$cache = isset($args[0]) ? realpath($args[0]) : false;
if (!$cache || !is_file($cache . '/manifest.json')) {
    WP_CLI::error('Usage: wp eval-file scripts/apply-cutouts.php /path/to/cutout-cache [-- --verify]');
}

try {
    $read = static fn(string $path): array => json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $media = $read(__DIR__ . '/../catalog/media.json');
    $manifest = $read($cache . '/manifest.json');
    $sizes = ['thumbnail' => 150, 'woocommerce_gallery_thumbnail' => 100, 'woocommerce_thumbnail' => 440];
    $entries = $manifest['files'];
    $sources = array_unique(array_values($media));
    if ($manifest['failures'] || count($entries) !== count($sources) || array_diff($sources, array_keys($entries))) {
        throw new RuntimeException('The cutout manifest must cover every catalog image.');
    }

    global $wpdb;
    $attachments = [];
    $rows = $wpdb->get_results("SELECT p.ID, m.meta_value, p.post_mime_type FROM $wpdb->posts p
        INNER JOIN $wpdb->postmeta m ON p.ID=m.post_id WHERE p.post_type='attachment' AND m.meta_key='_mv_source_media'");
    foreach ($rows as $row) {
        if (!isset($media[$row->meta_value])) {
            continue;
        }
        if (isset($attachments[$row->meta_value])) {
            throw new RuntimeException('Duplicate source attachment ' . $row->ID);
        }
        $attachments[$row->meta_value] = $row;
    }
    if (count($attachments) !== count($media)) {
        throw new RuntimeException('Import the complete WooCommerce catalog before publishing cutouts.');
    }

    $uploads = wp_upload_dir();
    $relative = 'mv-cutouts/v1';
    $destination = $uploads['basedir'] . '/' . $relative;
    $verify = in_array('--verify', $args, true) || in_array('verify', $args, true);
    $renditions = 0;

    foreach ($entries as $name => $entry) {
        $stem = pathinfo($name, PATHINFO_FILENAME);
        if (!preg_match('/^[a-f0-9]{24}$/D', $stem) || $entry['full'] !== $stem . '.webp'
            || array_keys($entry['sizes']) !== array_keys($sizes)) {
            throw new RuntimeException('Invalid manifest entry ' . $name);
        }
        $shapes = [$entry['full'] => 800];
        foreach ($sizes as $variant => $width) {
            $file = $entry['sizes'][$variant];
            if ($file !== "$stem-{$width}x{$width}.webp") {
                throw new RuntimeException('Invalid rendition for ' . $name);
            }
            $shapes[$file] = $width;
        }
        foreach ($shapes as $file => $width) {
            $path = $cache . '/' . $file;
            $image = is_file($path) ? getimagesize($path) : false;
            if (!$image || $image[0] !== $width || $image[1] !== $width || $image['mime'] !== 'image/webp') {
                throw new RuntimeException('Missing or invalid image ' . $file);
            }
            $target = "$destination/$file";
            if (is_file($target) && hash_file('sha256', $path) === hash_file('sha256', $target)) {
                ++$renditions;
                continue;
            }
            if ($verify || !wp_mkdir_p($destination) || !copy($path, "$target.tmp") || !rename("$target.tmp", $target)) {
                throw new RuntimeException('Could not validate or stage ' . $file);
            }
            ++$renditions;
        }
    }

    foreach ($media as $url => $name) {
        $row = $attachments[$url];
        $id = (int) $row->ID;
        $entry = $entries[$name];
        $file = $relative . '/' . $entry['full'];
        $metadata = [
            'width' => 800, 'height' => 800, 'file' => $file,
            'filesize' => filesize($uploads['basedir'] . '/' . $file), 'sizes' => [],
            'image_meta' => wp_get_attachment_metadata($id)['image_meta'] ?? [],
        ];
        foreach ($sizes as $variant => $width) {
            $rendition = $entry['sizes'][$variant];
            $metadata['sizes'][$variant] = [
                'file' => $rendition, 'width' => $width, 'height' => $width,
                'mime-type' => 'image/webp', 'filesize' => filesize("$destination/$rendition"),
            ];
        }
        $published = get_post_meta($id, '_wp_attached_file', true) === $file
            && $row->post_mime_type === 'image/webp'
            && wp_get_attachment_metadata($id) == $metadata
            && wp_attachment_is_image($id)
            && strpos((string) wp_get_attachment_url($id), '/' . $file) !== false;
        if ($published) {
            continue;
        }
        if ($verify) {
            throw new RuntimeException('WooCommerce attachment ' . $id . ' does not use its cutout.');
        }
        if (!get_post_meta($id, '_mv_original_attachment', true)) {
            add_post_meta($id, '_mv_original_attachment', [
                'file' => get_post_meta($id, '_wp_attached_file', true),
                'mime' => $row->post_mime_type,
                'metadata' => wp_get_attachment_metadata($id),
            ], true);
        }
        if ($row->post_mime_type !== 'image/webp') {
            $result = wp_update_post(['ID' => $id, 'post_mime_type' => 'image/webp'], true);
            if (is_wp_error($result)) {
                throw new RuntimeException($result->get_error_message());
            }
        }
        update_attached_file($id, "$destination/" . $entry['full']);
        wp_update_attachment_metadata($id, $metadata);
        if (get_post_meta($id, '_wp_attached_file', true) !== $file || wp_get_attachment_metadata($id) != $metadata) {
            throw new RuntimeException('Could not update the media record ' . $id);
        }
    }

    WP_CLI::success(sprintf('%s %d attachments, %d WebP files.', $verify ? 'Verified' : 'Published', count($attachments), $renditions));
} catch (Throwable $error) {
    WP_CLI::error($error->getMessage());
}
