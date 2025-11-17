<?php
/**
 * Plugin Name: JSON Logging
 * Description: Simple JSON logger with request ID context.
 */

declare(strict_types=1);

if (!function_exists('dlme_log')) {
    /**
     * Log a structured JSON line to PHP error_log (Docker picks it up).
     *
     * @param string $level   e.g. 'info', 'warning', 'error'
     * @param string $message Human-readable message
     * @param array  $context Extra context data
     */
    function dlme_log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'ts'         => gmdate('c'),
            'level'      => $level,
            'request_id' => defined('REQUEST_ID') ? REQUEST_ID : null,
            'message'    => $message,
            'context'    => $context,
        ];

        error_log(json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
