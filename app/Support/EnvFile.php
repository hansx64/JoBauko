<?php

namespace App\Support;

/**
 * Sets values in a .env file, replacing existing keys and appending new ones.
 * Used by setup commands so secrets go straight into .env instead of being
 * printed on screen.
 */
class EnvFile
{
    /**
     * @param  array<string, string>  $values
     */
    public static function set(string $path, array $values): void
    {
        $contents = is_file($path) ? (string) file_get_contents($path) : '';
        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";

        foreach ($values as $key => $value) {
            $line = $key.'="'.addcslashes($value, '"\\').'"';
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents)) {
                $contents = preg_replace_callback($pattern, fn () => $line, $contents, 1);
            } else {
                $contents = rtrim($contents, "\r\n").$newline.$line.$newline;
            }
        }

        file_put_contents($path, $contents);
    }
}
