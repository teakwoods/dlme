<?php
/**
 * Plugin Name: DLme Marketplace
 * Description: Custom functionality for the DLme consulting marketplace.
 * Author: You
 * Version: 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Autoload plugin classes (via Composer)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Bootstrap your plugin code here.
