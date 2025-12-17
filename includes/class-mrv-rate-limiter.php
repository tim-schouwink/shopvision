<?php
/**
 * Rate Limiter - Prevents API abuse by limiting requests per user/IP
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Rate_Limiter {

    /**
     * Transient prefix for rate limit counters
     */
    private const TRANSIENT_PREFIX = 'mrv_rate_';

    /**
     * Check if the current user/IP is rate limited
     *
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_in' => int]
     */
    public static function check(): array {
        $limit = self::get_limit();

        // 0 = no rate limiting
        if ($limit <= 0) {
            return [
                'allowed'   => true,
                'remaining' => -1, // unlimited
                'reset_in'  => 0,
            ];
        }

        $key = self::get_rate_key();
        $data = get_transient($key);

        if (false === $data) {
            // First request, initialize counter
            return [
                'allowed'   => true,
                'remaining' => $limit - 1,
                'reset_in'  => HOUR_IN_SECONDS,
            ];
        }

        $count = (int) $data['count'];
        $started = (int) $data['started'];
        $elapsed = time() - $started;
        $reset_in = max(0, HOUR_IN_SECONDS - $elapsed);

        if ($count >= $limit) {
            return [
                'allowed'   => false,
                'remaining' => 0,
                'reset_in'  => $reset_in,
            ];
        }

        return [
            'allowed'   => true,
            'remaining' => $limit - $count - 1,
            'reset_in'  => $reset_in,
        ];
    }

    /**
     * Increment the rate limit counter
     * Call this after a successful API request
     */
    public static function increment(): void {
        $limit = self::get_limit();

        // 0 = no rate limiting
        if ($limit <= 0) {
            return;
        }

        $key = self::get_rate_key();
        $data = get_transient($key);

        if (false === $data) {
            // First request
            $data = [
                'count'   => 1,
                'started' => time(),
            ];
            set_transient($key, $data, HOUR_IN_SECONDS);
        } else {
            // Increment existing counter
            $data['count'] = (int) $data['count'] + 1;
            $elapsed = time() - (int) $data['started'];
            $remaining_ttl = max(1, HOUR_IN_SECONDS - $elapsed);
            set_transient($key, $data, $remaining_ttl);
        }
    }

    /**
     * Get the rate limit for current user
     *
     * @return int Requests per hour (0 = unlimited)
     */
    private static function get_limit(): int {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            // Logged in users get their own limit (can be higher)
            $limit = (int) get_option('mrv_rate_limit_logged_in', 20);
        } else {
            // Guests
            $limit = (int) get_option('mrv_rate_limit_guest', 10);
        }

        return $limit;
    }

    /**
     * Get the transient key for current user/IP
     *
     * @return string Transient key
     */
    private static function get_rate_key(): string {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            // Use user ID for logged in users
            return self::TRANSIENT_PREFIX . 'user_' . $user_id;
        }

        // Use hashed IP for guests
        $ip = self::get_client_ip();
        return self::TRANSIENT_PREFIX . 'ip_' . md5($ip);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip(): string {
        $ip = '';

        // Check for proxy headers (in order of trust)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Most proxies
            'HTTP_X_REAL_IP',            // Nginx proxy
            'REMOTE_ADDR',               // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can contain multiple IPs, take the first
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                break;
            }
        }

        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Fallback
        return '0.0.0.0';
    }

    /**
     * Get human-readable time until reset
     *
     * @param int $seconds Seconds until reset
     * @return string Formatted time (e.g., "45 minutes")
     */
    public static function format_reset_time(int $seconds): string {
        if ($seconds <= 60) {
            return sprintf(
                _n('%d second', '%d seconds', $seconds, 'shopvision'),
                $seconds
            );
        }

        $minutes = ceil($seconds / 60);
        return sprintf(
            _n('%d minute', '%d minutes', $minutes, 'shopvision'),
            $minutes
        );
    }
}
