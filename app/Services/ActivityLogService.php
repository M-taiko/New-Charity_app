<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogService
{
    /**
     * Record an activity
     */
    public static function log(
        string $event,
        string $description,
        ?object $subject = null,
        ?array $properties = null,
        ?int $userId = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id'      => $userId ?? auth()->id(),
            'event'        => $event,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->id,
            'description'  => $description,
            'properties'   => $properties,
            'ip_address'   => request()?->ip(),
        ]);
    }

    // ── Shortcuts ──────────────────────────────────────────────

    public static function created($subject, string $description, ?array $props = null): ActivityLog
    {
        return self::log('created', $description, $subject, $props);
    }

    public static function updated($subject, string $description, ?array $props = null): ActivityLog
    {
        return self::log('updated', $description, $subject, $props);
    }

    public static function deleted($subject, string $description, ?array $props = null): ActivityLog
    {
        return self::log('deleted', $description, $subject, $props);
    }

    public static function approved($subject, string $description, ?array $props = null): ActivityLog
    {
        return self::log('approved', $description, $subject, $props);
    }

    public static function rejected($subject, string $description, ?array $props = null): ActivityLog
    {
        return self::log('rejected', $description, $subject, $props);
    }

    public static function reviewed($subject, string $description, ?array $props = null): ActivityLog
    {
        return self::log('reviewed', $description, $subject, $props);
    }

    public static function returned($subject, string $description, ?array $props = null): ActivityLog
    {
        return self::log('returned', $description, $subject, $props);
    }

    public static function login(?int $userId = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id'      => $userId ?? auth()->id(),
            'event'        => 'login',
            'subject_type' => 'App\Models\User',
            'subject_id'   => $userId ?? auth()->id(),
            'description'  => 'تسجيل دخول',
            'ip_address'   => request()?->ip(),
        ]);
    }
}
