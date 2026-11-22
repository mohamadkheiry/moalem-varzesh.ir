<?php
defined('ABSPATH') || exit;

function mv_description_spec_table(string $table): string {
    $document = new DOMDocument();
    if (!$document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $table . '</body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        return $table;
    }
    $xpath = new DOMXPath($document);
    $root = $document->getElementsByTagName('table');
    if ($root->length !== 1 || $xpath->query('//table/caption')->length) {
        return $table;
    }
    $rows = [];
    $table_rows = $xpath->query('./tr | ./*[self::thead or self::tbody or self::tfoot]/tr', $root->item(0));
    foreach ($table_rows as $index => $row) {
        $cells = $xpath->query('./td | ./th', $row);
        if ($cells->length !== 2) {
            return $table;
        }
        [$key, $value] = iterator_to_array($cells, false);
        if ($key->hasAttribute('colspan') || $key->hasAttribute('rowspan') || $value->hasAttribute('colspan') || $value->hasAttribute('rowspan')) {
            return $table;
        }
        $label = mv_option_text($key->textContent);
        if ($index === 0) {
            if (!in_array($label, ['ویژگی', 'مشخصه'], true)
                || !in_array(mv_option_text($value->textContent), ['توضیحات', 'مشخصات'], true)) {
                return $table;
            }
            continue;
        }
        if ($label === '') {
            return $table;
        }
        $html = '';
        foreach ($value->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }
        $rows[] = ['label' => esc_html($label), 'value' => $html];
    }
    if (!$rows) {
        return $table;
    }
    ob_start();
    get_template_part('template-parts/product-specifications', null, ['rows' => $rows]);
    return ob_get_clean();
}

function mv_description_specifications(string $content): string {
    if (stripos($content, '<table') === false || !class_exists(DOMDocument::class)) {
        return $content;
    }
    return preg_replace_callback('~<table\b[^>]*>.*?</table\s*>~is', static fn(array $match): string =>
        mv_description_spec_table($match[0]), $content) ?? $content;
}

add_filter('the_content', static function (string $content): string {
    return function_exists('is_product') && is_product() && get_post_type() === 'product'
        ? mv_description_specifications($content)
        : $content;
}, 20);
