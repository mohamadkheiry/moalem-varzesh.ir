<?php
defined('WP_CLI') && WP_CLI || exit;

$file = dirname(__DIR__) . '/catalog/editorial.json';
$data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$categories = [];
foreach ($data['categories'] as $source) {
    $term = get_term_by('slug', $source['slug'], 'category');
    if ($term) {
        $categories[$source['id']] = $term->term_id;
    } else {
        $result = wp_insert_term($source['name'], 'category', ['slug' => $source['slug']]);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        $categories[$source['id']] = $result['term_id'];
    }
}
foreach ($data['posts'] as $row) {
    $existing = get_page_by_path($row['slug'], OBJECT, 'post');
    $content = preg_replace_callback(
        '~\b(href)=(["\x27])https?://(?:www\.)?moalem-varzesh\.ir(/[^"\x27]*)\2~i',
        static fn(array $match): string => $match[1] . $match[2] . esc_url(home_url($match[3])) . $match[2],
        $row['content']
    );
    $post = wp_insert_post([
        'ID' => $existing ? $existing->ID : 0,
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_name' => $row['slug'],
        'post_title' => $row['title'],
        'post_date' => str_replace('T', ' ', $row['date']),
        'post_content' => wp_kses_post($content),
        'post_excerpt' => wp_strip_all_tags($row['excerpt']),
        'post_category' => array_values(array_intersect_key($categories, array_flip($row['categories']))),
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ], true);
    if (is_wp_error($post)) {
        WP_CLI::error($post->get_error_message());
    }
}
$blog = get_page_by_path('blog');
$blog_id = $blog ? $blog->ID : wp_insert_post([
    'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'blog', 'post_title' => 'راهنمای خرید و مطالب ورزشی',
], true);
if (is_wp_error($blog_id)) {
    WP_CLI::error($blog_id->get_error_message());
}
update_option('page_for_posts', $blog_id);
update_option('show_on_front', 'page');
$home = get_page_by_path('home');
$home_id = $home ? $home->ID : wp_insert_post([
    'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'home', 'post_title' => 'معلم ورزش',
], true);
if (is_wp_error($home_id)) {
    WP_CLI::error($home_id->get_error_message());
}
update_option('page_on_front', $home_id);
if (wp_get_environment_type() === 'local') {
    $sample = get_post(1);
    if ($sample && $sample->post_type === 'post' && $sample->post_name === 'hello-world') {
        wp_update_post(['ID' => $sample->ID, 'post_status' => 'draft']);
    }
}
WP_CLI::success(sprintf('Imported %d publicly listed articles.', count($data['posts'])));
