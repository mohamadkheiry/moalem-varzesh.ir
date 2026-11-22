<?php
defined('ABSPATH') || exit;

function mv_commerce_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'mv_commerce_state';
}

function mv_commerce_install(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = mv_commerce_table();
    $collation = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        state_key char(64) NOT NULL,
        token char(32) NOT NULL,
        expires bigint unsigned NOT NULL,
        payload text NOT NULL,
        PRIMARY KEY  (state_key),
        KEY expires (expires)
    ) $collation;");
}

function mv_commerce_key(string $scope, string $value): string {
    return hash_hmac('sha256', $scope . '|' . $value, wp_salt('auth'));
}

function mv_commerce_read(string $key): ?array {
    global $wpdb;
    $table = mv_commerce_table();
    return $wpdb->get_row($wpdb->prepare("SELECT token, expires, payload FROM $table WHERE state_key = %s", $key), ARRAY_A) ?: null;
}

function mv_commerce_write(string $key, ?array $previous, int $expires, array $data): bool {
    global $wpdb;
    $table = mv_commerce_table();
    $token = bin2hex(random_bytes(16));
    $payload = wp_json_encode($data);
    if ($previous === null) {
        return 1 === $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table (state_key, token, expires, payload) VALUES (%s, %s, %d, %s)",
            $key, $token, $expires, $payload
        ));
    }
    return 1 === $wpdb->query($wpdb->prepare(
        "UPDATE $table SET token = %s, expires = %d, payload = %s WHERE state_key = %s AND token = %s",
        $token, $expires, $payload, $key, $previous['token']
    ));
}

function mv_commerce_prune(): void {
    global $wpdb;
    $table = mv_commerce_table();
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires > 0 AND expires < %d LIMIT 500", time() - DAY_IN_SECONDS));
}

function mv_commerce_limit(string $key, int $limit, int $window): bool {
    $now = time();
    for ($attempt = 0; $attempt < 4; ++$attempt) {
        $row = mv_commerce_read($key);
        $active = $row && (int) $row['expires'] > $now;
        $count = $active ? (int) (json_decode($row['payload'], true)['count'] ?? 0) : 0;
        if ($count >= $limit) {
            return false;
        }
        $expiry = $active ? (int) $row['expires'] : $now + $window;
        if (mv_commerce_write($key, $row, $expiry, ['count' => $count + 1])) {
            return true;
        }
    }
    return false;
}
