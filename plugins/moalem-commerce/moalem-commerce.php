<?php
/**
 * Plugin Name: Moalem Commerce
 * Description: ورود با موبایل و ارسال پیامک‌های فروشگاه معلم ورزش
 * Version: 1.1.0
 * Requires PHP: 8.1
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/state.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/manager.php';
require_once __DIR__ . '/account.php';

register_activation_hook(__FILE__, 'mv_commerce_install');
