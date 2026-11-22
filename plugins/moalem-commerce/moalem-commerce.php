<?php
/**
 * Plugin Name: Moalem Commerce
 * Description: ورود با موبایل، پیامک فروشگاه و انتشار در کانال بله
 * Version: 1.5.1
 * Requires PHP: 8.1
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/state.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/manager.php';
require_once __DIR__ . '/account.php';
require_once __DIR__ . '/purchase-gate.php';
require_once __DIR__ . '/manager-otp.php';
require_once __DIR__ . '/templates/manager-otp-pages.php';
require_once __DIR__ . '/bale.php';

register_activation_hook(__FILE__, 'mv_commerce_install');
