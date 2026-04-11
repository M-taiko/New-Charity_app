<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'department',
        'job_title',
        'hire_date',
        'salary',
        'status',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function kpiMetrics(): HasMany
    {
        return $this->hasMany(KpiMetric::class);
    }

    /**
     * Get attendance status for today
     */
    public function getTodayStatus()
    {
        return $this->attendanceRecords()
            ->whereDate('date', today())
            ->first();
    }

    /**
     * Get attendance percentage for given month
     */
    public function getAttendancePercentage($month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $records = $this->attendanceRecords()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        if ($records->isEmpty()) {
            return 0;
        }

        $presentCount = $records->where('status', 'present')->count();
        return round(($presentCount / $records->count()) * 100, 2);
    }
}
