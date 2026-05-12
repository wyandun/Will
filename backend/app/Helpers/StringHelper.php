<?php

namespace App\Helpers;

final class StringHelper
{
    /**
     * Mask an email address to prevent enumeration.
     *
     * e.g., john@example.com -> j***@example.com
     * Always produces exactly 3 stars regardless of local-part length.
     *
     * @param  string  $email  The email address to mask
     * @return string The masked email address
     */
    public static function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email; // Fallback for invalid email strings
        }

        [$local, $domain] = explode('@', $email, 2);

        // Edge case: empty local part
        if (strlen($local) === 0) {
            return "***@{$domain}";
        }

        return substr($local, 0, 1).'***@'.$domain;
    }
}
