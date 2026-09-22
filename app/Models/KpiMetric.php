<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiMetric extends Model
{
    protected $fillable = [
        'employee_id',
        'metric_name',
        'target_value',
        'actual_value',
        'period_start',
        'period_end',
        'score',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'score' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Calculate score if not set
     */
    public function calculateScore()
    {
        if ($this->target_value == 0) {
            return 0;
        }
        return round(($this->actual_value / $this->target_value) * 100, 2);
    }
}
