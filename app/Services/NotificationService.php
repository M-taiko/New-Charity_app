<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Notify a single user
     */
    public static function notifyUser(
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        int|null $relatedId = null,
        string|null $relatedType = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
        ]);
    }

    /**
     * Notify all managers
     */
    public static function notifyManagers(
        string $title,
        string $message,
        string $type = 'info',
        int|null $relatedId = null,
        string|null $relatedType = null
    ): void {
        $managers = User::role('مدير')->get();

        foreach ($managers as $manager) {
            self::notifyUser($manager->id, $title, $message, $type, $relatedId, $relatedType);
        }
    }

    /**
     * Notify all researchers
     */
    public static function notifyResearchers(
        string $title,
        string $message,
        string $type = 'info',
        int|null $relatedId = null,
        string|null $relatedType = null
    ): void {
        $researchers = User::role('باحث')->get();

        foreach ($researchers as $researcher) {
            self::notifyUser($researcher->id, $title, $message, $type, $relatedId, $relatedType);
        }
    }

    /**
     * Notify all users with a specific role
     */
    public static function notifyByRole(
        string $role,
        string $title,
        string $message,
        string $type = 'info',
        int|null $relatedId = null,
        string|null $relatedType = null
    ): void {
        $users = User::role($role)->get();

        foreach ($users as $user) {
            self::notifyUser($user->id, $title, $message, $type, $relatedId, $relatedType);
        }
    }

    /**
     * Notify multiple users
     */
    public static function notifyMultiple(
        array $userIds,
        string $title,
        string $message,
        string $type = 'info',
        int|null $relatedId = null,
        string|null $relatedType = null
    ): void {
        foreach ($userIds as $userId) {
            self::notifyUser($userId, $title, $message, $type, $relatedId, $relatedType);
        }
    }
}
