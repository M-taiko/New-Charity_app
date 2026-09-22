<?php

use App\Helpers\StorageHelper;

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
