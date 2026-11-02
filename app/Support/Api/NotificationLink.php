<?php

declare(strict_types=1);

namespace App\Support\Api;

/**
 * SPA path for a notification's related record. Used by both the
 * notifications list and the bell poll so every surface deep-links
 * to the same page. related_type is stored with mixed casing across
 * history ("Expense" vs "expense") — normalize before matching.
 */
final class NotificationLink
{
    /** @return string|null SPA route like "/custodies/12", or null when unrelated */
    public static function spa(?string $relatedType, ?int $relatedId): ?string
    {
        if ($relatedId === null || $relatedId <= 0) {
            return null;
        }

        return match (strtolower((string) $relatedType)) {
            'custody' => "/custodies/{$relatedId}",
            'expense' => "/expenses/{$relatedId}",
            'custody_transfer' => "/transfers/{$relatedId}",
            'social_case' => "/cases/{$relatedId}",
            'task' => "/tasks/{$relatedId}",
            'purchase_request' => "/purchase/{$relatedId}",
            'maintenance_request' => "/maintenance/{$relatedId}",
            default => null,
        };
    }
}
