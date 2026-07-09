<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Polyfill;

class StringFunctions
{
    /**
     * Check if a string starts with a given substring.
     *
     * @param string $haystack The string to search in
     * @param string $needle The substring to search for
     * @return bool True if haystack starts with needle
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    /**
     * Check if a string ends with a given substring.
     *
     * @param string $haystack The string to search in
     * @param string $needle The substring to search for
     * @return bool True if haystack ends with needle
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * Check if a string contains a given substring.
     *
     * @param string $haystack The string to search in
     * @param string $needle The substring to search for
     * @return bool True if haystack contains needle
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }
}
