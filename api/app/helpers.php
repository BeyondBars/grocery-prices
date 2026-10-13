<?php

if (!function_exists('email_subject')) {
    /**
     * Formats the subject for an email.
     */
    function email_subject(string $subject): string
    {
        return sprintf('%s | %s', $subject, config('app.name'));
    }
}
