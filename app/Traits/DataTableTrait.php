<?php

namespace App\Traits;

use Yajra\DataTables\DataTables;

trait DataTableTrait
{
    /**
     * Return standardized DataTables response
     */
    protected function dataTableResponse($query, array $columns = [], array $rawColumns = [])
    {
        $datatable = DataTables::of($query);

        // Add columns if provided
        foreach ($columns as $column => $callback) {
            $datatable->addColumn($column, $callback);
        }

        // Mark columns as raw HTML if needed
        if (!empty($rawColumns)) {
            $datatable->rawColumns($rawColumns);
        }

        return $datatable->toJson();
    }

    /**
     * Get base columns for agent data
     */
    protected function getAgentDataColumns()
    {
        return [
            'agent_name' => fn($row) => $row->agent->name ?? '-',
            'spent_percent' => fn($row) => round(($row->spent / $row->amount) * 100) . '%',
            'remaining' => fn($row) => number_format($row->getRemainingBalance(), 2),
            'status_label' => fn($row) => \App\Services\StatusLabelService::label($row->status, 'custody'),
        ];
    }

    /**
     * Get transaction columns
     */
    protected function getTransactionColumns()
    {
        return [
            'type_label' => fn($row) => \App\Services\StatusLabelService::label($row->type, 'transaction'),
            'source_label' => fn($row) => $row->source ? (__('messages.' . $row->source)) : '-',
        ];
    }
}
