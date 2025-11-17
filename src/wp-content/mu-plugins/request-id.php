<?php
/**
 * Plugin Name: Request ID Bootstrap
 * Description: Ensure every request has a REQUEST_ID and echo it back in the response.
 */

declare(strict_types=1);

add_action('init', function () {
    $id = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;

    if ($id === null || $id === '') {
        $id = bin2hex(random_bytes(16));
    }

    if (!defined('REQUEST_ID')) {
        define('REQUEST_ID', $id);
    }

    if (!headers_sent()) {
        header('X-Request-ID: ' . $id);
    }
});
