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

if (!function_exists('normalise_aliases')) {
    /**
     * Upper-cases, trims and de-duplicates receipt aliases ("woolies " => "WOOLIES").
     */
    function normalise_aliases(mixed $aliases): array
    {
        return collect(is_array($aliases) ? $aliases : [])
            ->filter(fn ($alias) => is_string($alias) && trim($alias) !== '')
            ->map(fn (string $alias) => mb_strtoupper(trim($alias)))
            ->unique()
            ->values()
            ->all();
    }
}
