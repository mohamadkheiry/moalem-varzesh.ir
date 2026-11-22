<?php
defined('ABSPATH') || exit;

function mv_commerce_is_manager(int $user_id): bool {
    return $user_id > 0 && (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce'));
}

function mv_commerce_manager_identity_key(): string {
    return hash('sha256', 'moalem-commerce|manager-identities');
}

function mv_commerce_manager_identities(?array $row): array {
    return $row ? (json_decode($row['payload'], true) ?: []) : [];
}

function mv_commerce_manager_identity(int $user_id): ?array {
    $row = mv_commerce_read(mv_commerce_manager_identity_key());
    return mv_commerce_manager_identities($row)[$user_id] ?? null;
}

function mv_commerce_manager_for_phone(string $phone): ?array {
    if (!$phone) {
        return null;
    }
    $row = mv_commerce_read(mv_commerce_manager_identity_key());
    foreach (mv_commerce_manager_identities($row) as $user_id => $identity) {
        if ($identity['phone'] === $phone && mv_commerce_is_manager((int) $user_id)) {
            return ['user_id' => (int) $user_id, 'version' => $identity['version']];
        }
    }
    return null;
}

/** This is called after authenticated enrollment or when a binding is revoked. */
function mv_commerce_set_manager_identity(int $user_id, string $phone): bool {
    if ($phone !== '' && (mv_commerce_phone($phone) !== $phone || !mv_commerce_is_manager($user_id))) {
        return false;
    }
    $key = mv_commerce_manager_identity_key();
    for ($attempt = 0; $attempt < 4; ++$attempt) {
        $row = mv_commerce_read($key);
        $identities = mv_commerce_manager_identities($row);
        if ($phone === '') {
            if (!isset($identities[$user_id])) {
                return true;
            }
            unset($identities[$user_id]);
        } else {
            foreach ($identities as $id => $identity) {
                if ((int) $id !== $user_id && $identity['phone'] === $phone) {
                    return false;
                }
            }
            $identities[$user_id] = ['phone' => $phone, 'version' => bin2hex(random_bytes(16))];
        }
        if (mv_commerce_write($key, $row, 0, $identities)) {
            return true;
        }
    }
    return false;
}

add_action('deleted_user', static function (int $user_id): void {
    mv_commerce_set_manager_identity($user_id, '');
});

function mv_commerce_clear_demoted_manager(int $user_id): void {
    if (!mv_commerce_is_manager($user_id) && mv_commerce_manager_identity($user_id)) {
        mv_commerce_set_manager_identity($user_id, '');
    }
}

add_action('set_user_role', 'mv_commerce_clear_demoted_manager');
add_action('remove_user_role', 'mv_commerce_clear_demoted_manager');

function mv_commerce_manager_enrollment_scope(int $user_id, string $session): string {
    return 'manager-enroll-' . mv_commerce_key((string) $user_id, $session);
}

function mv_commerce_verify_manager_enrollment(int $user_id, string $session, string $phone, string $code): bool {
    return mv_commerce_is_manager($user_id) && $session !== '' && $phone !== ''
        && mv_commerce_otp_verify($phone, $code, mv_commerce_manager_enrollment_scope($user_id, $session))
        && mv_commerce_set_manager_identity($user_id, $phone);
}

function mv_commerce_manager_login_scope(array $identity): string {
    return 'manager-login-' . $identity['user_id'] . '-' . $identity['version'];
}

function mv_commerce_request_manager_otp(string $phone, string $ip): void {
    if (!mv_commerce_limit(mv_commerce_key('manager-request-ip', $ip), 12, 15 * MINUTE_IN_SECONDS)) {
        return;
    }
    $identity = mv_commerce_manager_for_phone($phone);
    if ($identity) {
        mv_commerce_otp_request($phone, $ip, mv_commerce_manager_login_scope($identity), 'کد ورود مدیر');
    }
}

function mv_commerce_verify_manager_otp(string $phone, string $code, string $ip): int {
    if (!mv_commerce_limit(mv_commerce_key('manager-verify-ip', $ip), 30, 15 * MINUTE_IN_SECONDS)) {
        return 0;
    }
    $identity = mv_commerce_manager_for_phone($phone);
    if (!$identity || !preg_match('/^[0-9]{6}$/D', $code)
        || !mv_commerce_otp_verify($phone, $code, mv_commerce_manager_login_scope($identity))
        || mv_commerce_manager_for_phone($phone) !== $identity) {
        return 0;
    }
    return $identity['user_id'];
}
