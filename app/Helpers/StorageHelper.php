<?php

namespace App\Helpers;

class StorageHelper
{
    /**
     * Get the URL for a storage file
     *
     * @param string $path The file path relative to storage/app/public
     * @return string The full URL to the file
     */
    public static function url($path)
    {
        if (empty($path)) {
            return null;
        }

        // If path already starts with storage, return as is
        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        // Otherwise prepend storage/
        return asset('storage/' . $path);
    }

    /**
     * Check if a file exists in storage
     *
     * @param string $path The file path relative to storage/app/public
     * @return bool
     */
    public static function exists($path)
    {
        return \Storage::disk('public')->exists($path);
    }

    /**
     * Delete a file from storage
     *
     * @param string $path The file path relative to storage/app/public
     * @return bool
     */
    public static function delete($path)
    {
        if (empty($path)) {
            return false;
        }

        return \Storage::disk('public')->delete($path);
    }
}
