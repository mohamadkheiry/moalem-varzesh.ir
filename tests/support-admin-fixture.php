<?php
defined('WP_CLI') && WP_CLI && wp_get_environment_type() === 'local' || exit;
$file = $args[1] ?? '';
if (!$file) WP_CLI::error('A private fixture path is required.');

if (($args[0] ?? '') === 'reset') {
    if (!is_readable($file)) WP_CLI::error('Fixture file is missing.');
    $fixture = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $user = get_user_by('login', $fixture['user']);
    if (!$user || !str_starts_with($user->user_login, 'mv-support-qa-')
        || get_user_meta($user->ID, '_mv_support_fixture', true) !== $fixture['tag']) {
        WP_CLI::error('Fixture account could not be verified.');
    }
    if ($fixture['settings'] === null) {
        delete_option('mv_support_design');
    } else {
        update_option('mv_support_design', $fixture['settings']);
    }
    $saved = wp_update_post(['ID' => $fixture['item'], 'post_title' => wp_slash($fixture['title'])], true);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($user->ID);
    if (is_wp_error($saved)) WP_CLI::error($saved->get_error_message());
    unlink($file);
    WP_CLI::success('Restored the footer settings and removed the test administrator.');
    return;
}

if (($args[0] ?? '') !== 'create' || file_exists($file)) WP_CLI::error('Use create with a new fixture path.');
$menu = get_nav_menu_locations()['mv_footer_help'] ?? 0;
$items = $menu ? wp_get_nav_menu_items($menu) : [];
if (!$items) WP_CLI::error('Install the footer menu before creating the fixture.');
$tag = bin2hex(random_bytes(10));
$login = 'mv-support-qa-' . $tag;
$password = wp_generate_password(32, true, false);
$id = wp_insert_user([
    'user_login' => $login, 'user_pass' => $password, 'role' => 'administrator', 'display_name' => 'آزمون پیشخوان',
    'meta_input' => ['_mv_support_fixture' => $tag],
]);
if (is_wp_error($id)) WP_CLI::error($id->get_error_message());
$fixture = [
    'user' => $login, 'password' => $password, 'tag' => $tag,
    'item' => $items[0]->ID, 'title' => $items[0]->post_title, 'settings' => get_option('mv_support_design', null),
];
if (file_put_contents($file, wp_json_encode($fixture), LOCK_EX) === false) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($id);
    WP_CLI::error('Could not store a private test fixture.');
}
WP_CLI::success('Created a local test administrator with a private fixture.');
