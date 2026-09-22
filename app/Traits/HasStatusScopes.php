<?php

namespace App\Traits;

trait HasStatusScopes
{
    /**
     * Filter by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Filter by multiple statuses
     */
    public function scopeWithStatuses($query, array $statuses)
    {
        return $query->whereIn('status', $statuses);
    }

    /**
     * Filter active (not closed/rejected)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['closed', 'rejected', 'canceled']);
    }

    /**
     * Filter pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filter recent (last 30 days)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * Filter by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
