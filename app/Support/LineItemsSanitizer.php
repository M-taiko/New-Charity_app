<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * تنظيف بنود المصروف (line_items) الواردة من الطلب
 * مشترك بين ExpenseController و ExpenseEditRequestController
 */
class LineItemsSanitizer
{
    /**
     * قراءة بنود المصروف من الطلب وتنظيفها
     * الحقل الأساسي line_items مع قبول line_items_data كاسم بديل للتوافق
     */
    public static function fromRequest(Request $request): ?array
    {
        $raw = $request->filled('line_items')
            ? $request->line_items
            : ($request->filled('line_items_data') ? $request->line_items_data : null);

        if ($raw === null || $raw === '') {
            return null;
        }

        $items = is_array($raw) ? $raw : json_decode($raw, true);

        if (!is_array($items) || $items === []) {
            return null;
        }

        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = $item['description'] ?? null;
            if (!is_string($description) || trim($description) === '') {
                continue;
            }
            $quantity = isset($item['quantity']) && is_numeric($item['quantity']) && (float) $item['quantity'] >= 0
                ? (float) $item['quantity'] : null;
            $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price']) && (float) $item['unit_price'] >= 0
                ? (float) $item['unit_price'] : null;
            $clean[] = [
                'description' => mb_substr(trim($description), 0, 255),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        return $clean === [] ? null : $clean;
    }
}
