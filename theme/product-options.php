<?php
defined('ABSPATH') || exit;

function mv_option_text(string $value): string {
    $value = wp_strip_all_tags(html_entity_decode(rawurldecode($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $value = strtr($value, ["\u{200C}" => ' ', 'ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', "\u{0654}" => '']);
    return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $value) ?? $value);
}

function mv_option_digits(string $value, bool $persian = false): string {
    $ascii = str_split('0123456789');
    $farsi = preg_split('//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY);
    $arabic = preg_split('//u', '٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY);
    $value = strtr($value, array_combine($farsi, $ascii) + array_combine($arabic, $ascii));
    return $persian ? strtr($value, array_combine($ascii, $farsi) + ['.' => '٫']) : $value;
}

function mv_option_custom_print(string $value): bool {
    return (bool) preg_match('/(?:اسم|نام)[\s-]*و[\s-]*شماره[\s-]*دلخواه/u', mv_option_text($value));
}

function mv_option_contains_size(string $attribute): bool {
    return (bool) preg_match('/سایز|(?:^|[_-])size(?:$|[_-])/iu', mv_option_text($attribute));
}

function mv_option_size(string $value): ?array {
    $value = mv_option_digits(mv_option_text($value));
    $standard_code = 'XXXS|XXS|XS|S|M|L|(?:[2-9]X|X{1,4})L';
    $size = ['number' => '', 'standard' => '', 'color' => '', 'dimensions' => '', 'print' => ''];
    if (preg_match('/^قد\s+(\d+)\s+و\s+عرض\s+(\d+)\s+سانت(?:ی\s*متر)?(?:\s*\+?\s*(.*))?$/u', $value, $match)) {
        $size['dimensions'] = 'قد ' . mv_option_digits($match[1], true) . ' و عرض ' . mv_option_digits($match[2], true) . ' سانتی‌متر';
        $size['print'] = trim($match[3] ?? '');
    } elseif (preg_match('/^(مشکی|سفید|آبی|قرمز)\s*\((' . $standard_code . ')\)$/iu', $value, $match)) {
        $size['color'] = $match[1];
        $size['standard'] = strtoupper($match[2]);
    } elseif (preg_match('/^(?:سایز\s+)?(?:(\d+(?:[.\/]\d+)?)(?:\s*\((' . $standard_code . '|\d+)\))?|(' . $standard_code . '))(?=\s|$)\s*(.*)$/iu', $value, $match)) {
        $size['number'] = str_replace('/', '.', $match[1]);
        $size['standard'] = strtoupper($match[2] ?: $match[3]);
        $size['print'] = trim($match[4]);
    } else {
        return null;
    }
    if (preg_match('/^(X{2,4})L$/D', $size['standard'], $multiples)) {
        $size['standard'] = strlen($multiples[1]) . 'XL';
    }
    if (mv_option_custom_print($size['print'])) {
        $size['print'] = 'چاپ نام و شمارهٔ دلخواه';
    } elseif ($size['print'] !== '' && $size['print'] !== 'بدون چاپ') {
        if (preg_match('/^(?:آبی|بنفش|زرد|سبز|سفید|قرمز|مشکی|نارنجی|صورتی)$/u', $size['print'])) {
            $size['color'] = $size['print'];
            $size['print'] = '';
        } elseif (preg_match('/^(?:چاپ\s|یامال$|امباپه$|رافینیا$|رونالدو$|مسی$)/u', $size['print'])) {
            $size['print'] = str_starts_with($size['print'], 'چاپ ') ? $size['print'] : 'چاپ ' . $size['print'];
        } else {
            return null;
        }
    }
    return $size;
}

function mv_option_size_label(array $size): string {
    if ($size['dimensions'] !== '') {
        return $size['dimensions'];
    }
    $number = mv_option_digits($size['number'], true);
    $standard = ctype_digit($size['standard']) ? mv_option_digits($size['standard'], true) : $size['standard'];
    $label = $number === '' ? $standard : $number . ($standard === '' ? '' : ' (' . $standard . ')');
    return 'سایز ' . $label;
}

function mv_option_size_rank(array $size): float {
    if ($size['number'] !== '') {
        return (float) $size['number'];
    }
    $ranks = ['XXXS' => -2, 'XXS' => -1, 'XS' => 0, 'S' => 1, 'M' => 2, 'L' => 3, 'XL' => 4, '2XL' => 5, '3XL' => 6, '4XL' => 7];
    return $ranks[$size['standard']] ?? PHP_FLOAT_MAX;
}

function mv_option_display(string $value): string {
    return mv_option_digits(str_ireplace(
        ['سورمه ای', 'سرمه ای', 'XX_Heavy', 'X_Heavy', 'Mediom', 'Very Light', 'Light'],
        ['سرمه‌ای', 'سرمه‌ای', 'فوق‌سنگین', 'خیلی سنگین', 'متوسط', 'خیلی سبک', 'سبک'],
        mv_option_text($value)
    ), true);
}

function mv_option_label(string $attribute, array $values): string {
    $label = mv_option_text($attribute);
    $colors = '/(?:آبی|بنفش|زرد|سبز|سفید|سرمه|سورمه|قرمز|مشکی|نارنجی|صورتی|خاکستری|کرمی|طلایی)/u';
    $is_color = static fn(string $value): bool => (bool) preg_match($colors, $value);
    $has_color = (bool) array_filter($values, $is_color);
    $has_resistance = $has_color && preg_grep('/\b(?:Mediom|Medium|Light|X{1,2}_Heavy)\b/i', $values);
    if (mv_option_contains_size($label)) {
        $sizes = array_map('mv_option_size', $values);
        if ($values && !in_array(null, $sizes, true)) {
            if (array_filter(array_column($sizes, 'color'))) {
                return 'سایز و رنگ';
            }
            if (array_filter(array_column($sizes, 'print'))) {
                return 'سایز و نوع چاپ';
            }
            return array_filter(array_column($sizes, 'dimensions')) ? 'ابعاد لباس' : 'سایز';
        }
        if ($values && count(preg_grep('/^بسته\s+/u', $values)) === count($values)) {
            return 'تعداد در بسته';
        }
        if ($has_resistance) {
            return 'رنگ و مقاومت';
        }
        $plain_color = '/^(?:آبی|بنفش|زرد|سبز|سفید|سرمه‌ای|قرمز|مشکی|نارنجی|صورتی)(?:[-، ]+(?:آبی|بنفش|زرد|سبز|سفید|قرمز|مشکی|نارنجی|صورتی))*$/u';
        if ($has_color) {
            return count(preg_grep($plain_color, $values)) === count($values) ? 'رنگ' : 'طرح و رنگ';
        }
    }
    if ($label === 'رنگ') {
        if ($has_resistance) {
            return 'رنگ و مقاومت';
        }
        $designs = preg_grep('/آرژانتین|استقلال|النصر|ایران|اینتر|بارسا|بارسلونا|برزیل|پاریسن|پرتغال|پرسپولیس|تراکتور|رئال|رونالدو|لیورپول|منچستر|نایک/u', $values);
        if ($designs) {
            return $has_color ? 'طرح و رنگ' : 'طرح';
        }
        if (preg_grep('/با چاپ/u', $values)) {
            return 'رنگ و نوع چاپ';
        }
    }
    if ($label === 'برند' && $values && preg_grep('/رئال|بارسلونا|منچستر|النصر/u', $values)) {
        return $has_color ? 'طرح و رنگ' : 'طرح';
    }
    if ($label === 'برند' && $has_color) {
        return 'برند و رنگ';
    }
    return $label;
}

function mv_option_size_groups(array $sizes): array {
    $by_number = $by_standard = [];
    foreach ($sizes as $size) {
        if ($size['number'] !== '' && $size['standard'] !== '') {
            $by_number[$size['number']][$size['standard']] = $size['standard'];
            if (!ctype_digit($size['standard'])) {
                $by_standard[$size['standard']][$size['number']] = $size['number'];
            }
        }
    }
    foreach ($sizes as &$size) {
        $number = $size['number'];
        $standard = $size['standard'];
        if ($number === '' && count($by_standard[$standard] ?? []) === 1) {
            $size['number'] = reset($by_standard[$standard]);
        } elseif ($standard === '' && count($by_number[$number] ?? []) === 1) {
            $size['standard'] = reset($by_number[$number]);
        }
    }
    unset($size);
    usort($sizes, static fn(array $left, array $right): int => mv_option_size_rank($left) <=> mv_option_size_rank($right));
    $labels = $prints = $colors = [];
    foreach ($sizes as $size) {
        $label = mv_option_size_label($size);
        $labels[$label] = $label;
        if ($size['print'] !== '') {
            $prints[$size['print']][$label] = $label;
        }
        if ($size['color'] !== '') {
            $colors[$size['color']] = $size['color'];
        }
    }
    $print_priority = ['بدون چاپ' => 0, 'چاپ نام و شمارهٔ دلخواه' => 2];
    uksort($prints, static fn(string $left, string $right): int =>
        ($print_priority[$left] ?? 1) <=> ($print_priority[$right] ?? 1));
    return ['sizes' => array_values($labels), 'prints' => $prints, 'colors' => array_values($colors)];
}

function mv_product_spec_rows(array $rows, WC_Product $product): array {
    $result = [];
    $attributes = $product->get_attributes();
    foreach ($rows as $key => $row) {
        $attribute = str_starts_with($key, 'attribute_') ? ($attributes[substr($key, 10)] ?? null) : null;
        if (!$attribute instanceof WC_Product_Attribute) {
            $result[] = $row;
            continue;
        }
        $values = $attribute->get_options();
        $urls = [];
        if ($attribute->is_taxonomy()) {
            $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'all']);
            $values = wp_list_pluck($terms, 'name');
            if ($attribute->get_taxonomy_object()?->attribute_public) {
                foreach ($terms as $term) {
                    $url = get_term_link($term, $attribute->get_name());
                    if (is_string($url)) $urls[mv_option_display($term->name)] = $url;
                }
            }
        }
        $values = array_values(array_filter(array_map('mv_option_text', $values), static fn(string $value): bool =>
            !preg_match('/^(?:null|undefined|[-–—])?$/iu', $value)));
        if (!$values) {
            continue;
        }
        $label = mv_option_label($row['label'], $values);
        if (mv_option_contains_size($attribute->get_name()) && !in_array(null, $sizes = array_map('mv_option_size', $values), true)) {
            $groups = mv_option_size_groups($sizes);
            $result[] = ['label' => $sizes[0]['dimensions'] !== '' ? 'ابعاد لباس' : 'سایزها', 'options' => $groups['sizes']];
            if ($groups['colors']) $result[] = ['label' => 'رنگ', 'options' => $groups['colors']];
            if ($groups['prints']) $result[] = ['label' => 'گزینه‌های چاپ', 'groups' => $groups['prints']];
            continue;
        }
        $result[] = ['label' => $label, 'options' => array_values(array_unique(array_map('mv_option_display', $values))), 'links' => $urls];
    }
    return $result;
}

add_filter('woocommerce_dropdown_variation_attribute_options_args', static function (array $args): array {
    $name = $args['attribute'] ?? '';
    $product = $args['product'] ?? null;
    if (!empty($args['options']) && mv_option_contains_size($name) && $product instanceof WC_Product
        && !(($product->get_attributes()[sanitize_title($name)] ?? null)?->is_taxonomy())) {
        $sizes = array_map('mv_option_size', $args['options']);
        if (!in_array(null, $sizes, true)) {
            $options = array_combine($args['options'], $sizes);
            uasort($options, static fn(array $left, array $right): int => mv_option_size_rank($left) <=> mv_option_size_rank($right));
            $args['options'] = array_map('strval', array_keys($options));
        }
    }
    return $args;
});

add_filter('woocommerce_variation_option_name', static function (string $value, $term, string $attribute, $product): string {
    if (!function_exists('is_product') || !is_product()) {
        return $value;
    }
    $size = mv_option_contains_size($attribute) ? mv_option_size($value) : null;
    return $size ? implode('، ', array_filter([mv_option_size_label($size), $size['color'], $size['print']])) : mv_option_display($value);
}, 10, 4);

add_filter('woocommerce_attribute_label', static function (string $label, string $name, $product): string {
    if (!function_exists('is_product') || !is_product()) {
        return $label;
    }
    if (!$product instanceof WC_Product) {
        $product = wc_get_product(get_queried_object_id());
    }
    if (!$product instanceof WC_Product) {
        return $label;
    }
    $attribute = $product->get_attributes()[sanitize_title($name)] ?? null;
    if (!$attribute instanceof WC_Product_Attribute || $attribute->is_taxonomy()) {
        return $label;
    }
    return mv_option_label($label, array_map('mv_option_text', $attribute->get_options()));
}, 10, 3);
