<?php

use App\Helpers\StorageHelper;

if (!function_exists('dt_span')) {
    /**
     * عرض تاريخ/وقت يتحول ل التوقيت المحلي للمستخدم في المتصفح (T20)
     * النص الخادمي fallback فقط؛ السكبت المشترك في الـ layout يستبدله
     * mode: datetime (افتراضي) | date | time
     */
    function dt_span($date, string $mode = 'datetime'): string
    {
        if (!$date instanceof \DateTimeInterface) {
            return e((string) $date ?: '-');
        }

        $fallbackFormats = [
            'datetime' => 'Y-m-d H:i',
            'date' => 'Y-m-d',
            'time' => 'H:i',
        ];
        $attr = $mode === 'date' ? 'data-utc-date' : ($mode === 'time' ? 'data-utc-time' : 'data-utc-datetime');

        return '<span ' . $attr . '="' . e($date->format('c')) . '">' . e($date->format($fallbackFormats[$mode] ?? 'Y-m-d H:i')) . '</span>';
    }
}

if (!function_exists('storage_url')) {
    /**
     * Get the URL for a storage file
     *
     * @param string $path The file path relative to storage/app/public
     * @return string|null The full URL to the file
     */
    function storage_url($path)
    {
        return StorageHelper::url($path);
    }
}

if (!function_exists('storage_exists')) {
    /**
     * Check if a file exists in storage
     *
     * @param string $path The file path relative to storage/app/public
     * @return bool
     */
    function storage_exists($path)
    {
        return StorageHelper::exists($path);
    }
}

if (!function_exists('storage_delete')) {
    /**
     * Delete a file from storage
     *
     * @param string $path The file path relative to storage/app/public
     * @return bool
     */
    function storage_delete($path)
    {
        return StorageHelper::delete($path);
    }
}
