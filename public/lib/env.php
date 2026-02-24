<?php
/**
 * Environment Variables Loader
 * Loads .env file from private directory and provides env() function
 */

class EnvLoader {
    private static $variables = [];
    private static $loaded = false;

    /**
     * Load environment variables from .env file
     */
    public static function load($envPath = null) {
        if (self::$loaded) {
            return;
        }

        if ($envPath === null) {
            $envPath = dirname(dirname(dirname(__FILE__))) . '/private/.env';
        }

        if (!file_exists($envPath)) {
            throw new Exception("Environment file not found: {$envPath}");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }

                self::$variables[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$variables[$key]) ? self::$variables[$key] : $default;
    }
}

// Load environment variables on include
EnvLoader::load();

/**
 * Helper function to access environment variables
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}
