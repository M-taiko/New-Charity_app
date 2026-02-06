<?php

namespace App\Helpers;

/**
 * Polyfill for mbstring functions
 * Used as a workaround when mbstring extension is not available
 */
class MbstringPolyfill
{
    /**
     * Polyfill for mb_split()
     * Splits a string by a regular expression pattern
     */
    public static function mb_split($pattern, $string, $limit = -1)
    {
        // If mbstring is available, use the native function
        if (function_exists('mb_split')) {
            return mb_split($pattern, $string, $limit);
        }

        // Fallback to preg_split
        if ($limit === -1) {
            return preg_split('/' . preg_quote($pattern, '/') . '/', $string);
        }

        return preg_split('/' . preg_quote($pattern, '/') . '/', $string, $limit);
    }

    /**
     * Register polyfills
     */
    public static function register()
    {
        if (!function_exists('mb_split')) {
            // Add the function to the global namespace
            if (!function_exists('\mb_split')) {
                eval('function mb_split($pattern, $string, $limit = -1) {
                    return \App\Helpers\MbstringPolyfill::mb_split($pattern, $string, $limit);
                }');
            }
        }
    }
}
