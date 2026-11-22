<?php
defined('WP_CLI') && WP_CLI || exit;

if (!class_exists('WooCommerce')) {
    WP_CLI::error('Activate WooCommerce first.');
}
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

final class MV_Catalog_Import {
    private array $products;
    private array $variations;
    private array $categories;
    private array $media;
    private array $attachments = [];
    private array $prepared_images = [];
    private array $terms = [];
    private array $source_to_local = [];
    private int $attachment_count = 0;

    public function __construct(private string $catalog, private string $cache) {
        $this->products = $this->read('products');
        $this->variations = array_column($this->read('variations'), null, 'id');
        $this->categories = array_column($this->read('categories'), null, 'id');
        $this->media = $this->read('media');
        $metadata = $this->cache . '/image-metadata.json';
        if (is_file($metadata)) {
            $this->prepared_images = json_decode(file_get_contents($metadata), true, 512, JSON_THROW_ON_ERROR);
        }
    }

    private function read(string $name): array {
        return json_decode(file_get_contents($this->catalog . '/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function run(): void {
        $missing = array_filter($this->media, fn(string $name): bool => !is_file($this->cache . '/' . basename($name)));
        if ($missing) {
            throw new RuntimeException(count($missing) . ' cached media files are missing. Run fetch-media.py first.');
        }
        foreach ($this->products as $row) {
            foreach ($row['variations'] as $variation) {
                if (!isset($this->variations[$variation['id']])) {
                    throw new RuntimeException('Missing variation ' . $variation['id']);
                }
            }
        }
        wp_defer_term_counting(true);
        foreach ($this->categories as $category) {
            $this->term($category['id']);
        }

        foreach (array_reverse($this->products) as $count => $row) {
            $this->product($row, count($this->products) - $count);
            if (($count + 1) % 10 === 0) {
                WP_CLI::log(sprintf('Products %d/%d', $count + 1, count($this->products)));
            }
        }
        foreach ($this->products as $row) {
            if (!empty($row['grouped_products'])) {
                $product = wc_get_product($this->source_to_local[$row['id']]);
                $children = array_intersect_key($this->source_to_local, array_flip($row['grouped_products']));
                $product->set_children(array_values($children));
                $product->save();
            }
        }
        foreach ($this->categories as $category) {
            if (!empty($category['image']['src'])) {
                continue;
            }
            foreach ($this->products as $row) {
                if (!in_array($category['id'], array_column($row['categories'], 'id'), true) || !$row['images']) {
                    continue;
                }
                update_term_meta($this->term($category['id']), 'thumbnail_id', $this->image($row['images'][0], $row['name']));
                break;
            }
        }
        wp_defer_term_counting(false);
        wc_delete_product_transients();
        WP_CLI::success(sprintf('%d products, %d variations, %d categories, %d new media attachments.', count($this->products), count($this->variations), count($this->terms), $this->attachment_count));
    }

    private function source_id(string $type, int|string $source, string $key = '_mv_source_id'): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM $wpdb->posts p JOIN $wpdb->postmeta m ON p.ID = m.post_id WHERE p.post_type = %s AND p.post_status <> 'trash' AND m.meta_key = %s AND m.meta_value = %s ORDER BY p.ID LIMIT 1",
            $type, $key, (string) $source
        ));
    }

    private function term(int $source): int {
        if (isset($this->terms[$source])) {
            return $this->terms[$source];
        }
        $row = $this->categories[$source];
        $parent = $row['parent'] ? $this->term($row['parent']) : 0;
        $slug = sanitize_title(rawurldecode($row['slug']));
        $data = [
            'slug' => $slug,
            'parent' => $parent,
            'description' => wp_kses_post($this->decode($row['description'] ?? '')),
        ];
        $term = get_term_by('slug', $slug, 'product_cat');
        $result = $term
            ? wp_update_term($term->term_id, 'product_cat', $data + ['name' => $this->decode($row['name'])])
            : wp_insert_term($this->decode($row['name']), 'product_cat', $data);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
        $id = $this->terms[$source] = (int) $result['term_id'];
        if (!empty($row['image']['src'])) {
            update_term_meta($id, 'thumbnail_id', $this->image($row['image'], $row['name']));
        }
        return $id;
    }

    private function decode(string $value): string {
        return html_entity_decode(str_replace('\u200c', "\u{200C}", $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function image(array $image, string $fallback): int {
        $url = $image['src'];
        if (isset($this->attachments[$url])) {
            return $this->attachments[$url];
        }
        $existing = $this->source_id('attachment', $url, '_mv_source_media');
        if ($existing && is_file(get_attached_file($existing))) {
            if (!wp_get_attachment_metadata($existing)) {
                $this->save_metadata($existing, get_attached_file($existing), basename($this->media[$url]));
            }
            return $this->attachments[$url] = $existing;
        }
        $name = basename($this->media[$url] ?? '');
        $path = $this->cache . '/' . $name;
        $mime = wp_get_image_mime($path);
        if (!$name || !$mime) {
            throw new RuntimeException('Unrecognized image: ' . $url);
        }
        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            throw new RuntimeException($upload['error']);
        }
        $file = $upload['path'] . '/' . wp_unique_filename($upload['path'], $name);
        if (!copy($path, $file)) {
            throw new RuntimeException('Could not copy ' . $name);
        }
        $id = wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title' => sanitize_text_field($image['name'] ?? $fallback),
            'post_status' => 'inherit',
            'meta_input' => ['_mv_source_media' => $url],
        ], $file, 0, true);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }
        update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field(($image['alt'] ?? '') ?: $fallback));
        $this->save_metadata($id, $file, $name);
        ++$this->attachment_count;
        return $this->attachments[$url] = (int) $id;
    }

    private function save_metadata(int $id, string $file, string $name): void {
        if (!isset($this->prepared_images[$name])) {
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
            return;
        }
        $metadata = $this->prepared_images[$name];
        $metadata['file'] = _wp_relative_upload_path($file);
        $metadata['image_meta'] = wp_read_image_metadata($file) ?: [];
        $stem = pathinfo($file, PATHINFO_FILENAME);
        foreach ($metadata['sizes'] as &$size) {
            $original = $this->cache . '/' . basename($size['file']);
            $size['file'] = sprintf('%s-%dx%d.webp', $stem, $size['width'], $size['height']);
            if (!copy($original, dirname($file) . '/' . $size['file'])) {
                throw new RuntimeException('Could not copy thumbnail ' . $size['file']);
            }
        }
        unset($size);
        wp_update_attachment_metadata($id, $metadata);
    }

    private function content(string $content): string {
        $content = preg_replace_callback(
            '~\b(href|src)=(["\x27])(https?://(?:www\.)?moalem-varzesh\.ir)(/[^"\x27]*)\2~i',
            static function (array $match): string {
                $path = $match[4];
                if (preg_match('~^/(product/|product-category/|shop/?$|contact-us/?$|about-us/?$|terms-and-conditions/?$|refund_returns-2/?$)~', $path)) {
                    return $match[1] . $match[2] . esc_url(home_url($path)) . $match[2];
                }
                return $match[0];
            },
            $content
        );
        return wp_kses_post($content);
    }

    private function attributes(array $row): array {
        $attributes = [];
        foreach ($row['attributes'] as $position => $value) {
            $name = $this->decode($value['name']);
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($name);
            $attribute->set_options(array_map(fn(array $term): string => $this->decode($term['name']), $value['terms']));
            $attribute->set_position($position);
            $attribute->set_visible(true);
            $attribute->set_variation((bool) $value['has_variations']);
            $attributes[sanitize_title($name)] = $attribute;
        }
        return $attributes;
    }

    private function commercial(WC_Product $product, array $row): void {
        $prices = $row['prices'];
        if (($prices['currency_code'] ?? '') !== 'IRT' || (int) $prices['currency_minor_unit'] !== 0) {
            throw new RuntimeException('Unexpected currency for source product ' . $row['id']);
        }
        $product->set_regular_price($prices['regular_price']);
        $product->set_sale_price($row['on_sale'] ? $prices['sale_price'] : '');
        $product->set_price($prices['price']);
        $quantity = $row['low_stock_remaining'] ?? null;
        $status = $row['is_on_backorder'] ? 'onbackorder' : ($row['is_in_stock'] ? 'instock' : 'outofstock');
        $product->set_manage_stock($quantity !== null);
        $product->set_stock_quantity($quantity);
        $product->set_stock_status($status);
        $product->set_backorders($row['is_on_backorder'] ? 'notify' : 'no');
        $product->set_weight($row['weight']);
        $product->set_length($row['dimensions']['length']);
        $product->set_width($row['dimensions']['width']);
        $product->set_height($row['dimensions']['height']);
        $product->set_sold_individually((bool) $row['sold_individually']);
        if ($row['sku'] !== '') {
            $product->set_sku($row['sku']);
        }
        $product->update_meta_data('_mv_source_id', $row['id']);
    }

    private function product(array $row, int $position): void {
        $id = $this->source_id('product', $row['id']);
        $product = match ($row['type']) {
            'variable' => new WC_Product_Variable($id),
            'grouped' => new WC_Product_Grouped($id),
            'simple' => new WC_Product_Simple($id),
            default => throw new RuntimeException('Unsupported product type ' . $row['type']),
        };
        $name = $this->decode($row['name']);
        $product->set_name($name);
        $product->set_slug(rawurldecode($row['slug']));
        $product->set_status('publish');
        $product->set_description($this->content($row['description']));
        $product->set_short_description($this->content($row['short_description']));
        $product->set_catalog_visibility('visible');
        $product->set_category_ids(array_map(fn(array $term): int => $this->term($term['id']), $row['categories']));
        $product->set_menu_order($position);
        $attributes = $this->attributes($row);
        $product->set_attributes($attributes);
        $this->commercial($product, $row);

        $images = array_map(fn(array $image): int => $this->image($image, $name), $row['images']);
        $product->set_image_id(array_shift($images) ?: 0);
        $product->set_gallery_image_ids($images);
        $product->update_meta_data('_mv_source_url', $row['permalink']);
        $product->update_meta_data('_mv_source_description', sanitize_text_field(wp_strip_all_tags($row['short_description'] ?: $row['description'])));
        $id = $this->source_to_local[$row['id']] = $product->save();
        if (!$id) {
            throw new RuntimeException('Product save failed: ' . $row['id']);
        }
        if ($row['tags']) {
            wp_set_object_terms($id, array_map(fn(array $tag): string => $this->decode($tag['name']), $row['tags']), 'product_tag');
        }
        if ($row['brands'] && taxonomy_exists('product_brand')) {
            wp_set_object_terms($id, array_map(fn(array $brand): string => $this->decode($brand['name']), $row['brands']), 'product_brand');
        }

        if ($product->is_type('variable')) {
            foreach ($row['variations'] as $index => $listed) {
                $this->variation($id, $row['attributes'], $listed, $index);
            }
            WC_Product_Variable::sync($id);
        }
    }

    private function variation(int $parent, array $attributes, array $listed, int $position): void {
        $row = $this->variations[$listed['id']];
        $id = $this->source_id('product_variation', $row['id']);
        $variation = new WC_Product_Variation($id);
        $variation->set_parent_id($parent);
        $variation->set_status('publish');
        $variation->set_menu_order($position);
        $opts = [];
        foreach ($listed['attributes'] as $option) {
            $name = $this->decode($option['name']);
            $value = $this->decode((string) $option['value']);
            $matches = array_values(array_filter($attributes, fn(array $a): bool => $this->decode($a['name']) === $name));
            if (!$matches) {
                throw new RuntimeException('Unmatched variation attribute: ' . $name);
            }
            if ($value !== '' && !empty($matches[0]['taxonomy'])) {
                $terms = array_values(array_filter(
                    $matches[0]['terms'],
                    static fn(array $term): bool => rawurldecode($term['slug']) === rawurldecode($value)
                ));
                if (!$terms) {
                    throw new RuntimeException('Unmatched option ' . $value . ' for source variation ' . $row['id']);
                }
                $value = $this->decode($terms[0]['name']);
            }
            $opts[sanitize_title($name)] = $value;
        }
        $variation->set_attributes($opts);
        $variation->set_description($this->content($row['description']));
        $this->commercial($variation, $row);
        $variation->set_image_id(!empty($row['images'][0]) ? $this->image($row['images'][0], $row['name']) : 0);
        $variation->save();
    }
}

try {
    $catalog = getenv('MV_CATALOG_DIR') ?: dirname(__DIR__) . '/catalog';
    $cache = getenv('MV_MEDIA_CACHE');
    if (!$cache) {
        WP_CLI::error('Set MV_MEDIA_CACHE to the directory populated by fetch-media.py.');
    }
    (new MV_Catalog_Import($catalog, $cache))->run();
} catch (Throwable $error) {
    WP_CLI::error($error->getMessage());
}
