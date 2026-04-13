<?php
/**
 * Time formatting helpers for consistent API timestamp output.
 */

function toUtcIso8601(?string $value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $value = trim($value);
    $utc = new DateTimeZone('UTC');

    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $utc);
    if (!$dt) {
        try {
            $dt = new DateTimeImmutable($value, $utc);
        } catch (Throwable $e) {
            return null;
        }
    }

    return $dt->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
}
