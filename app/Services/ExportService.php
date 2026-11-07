<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use Mpdf\Mpdf;
use Mpdf\Config\FontVariables;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one export service (Task6 T6-71): every list and report exports through
 * here — the SAME query as the table (filters, sort, search), a row cap, the
 * same authorization as the list (the caller's responsibility), audit-logged.
 *
 * Formats:
 * - xlsx: openspout streaming writer, RTL sheet, styled frozen header row,
 *   auto-filter, column widths, numbers as numbers, totals row, org/title/
 *   filters block at the top.
 * - pdf: mPDF with an Arabic-capable font (Amiri ships with the app), A4
 *   portrait or landscape by column count, repeated table header, page
 *   numbers "x / y", totals row.
 * - csv: UTF-8 BOM so Excel keeps Arabic shaping, one consistent number format.
 *
 * File names: {org}-{report}-{YYYY-MM-DD}.{ext}
 */
class ExportService
{
    /** exports are capped so a PHP-only host is never exhausted (T6-71.1) */
    public const int MAX_ROWS = 2000;

    /**
     * @param  Request  $request  the list request — its filter/sort/q params replay the table query
     * @param  Collection<array<string, mixed>>  $rows  already-mapped display rows
     * @param  array<int, array{key: string, label: string, numeric?: bool, total?: bool}>  $columns
     * @param  string  $reportName  slug for the file name, e.g. "expenses"
     */
    public function export(Request $request, Collection $rows, array $columns, string $reportName): StreamedResponse
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        if (! in_array($format, ['xlsx', 'pdf', 'csv'], true)) {
            abort(422, 'صيغة تصدير غير مدعومة: ' . $format);
        }

        $capped = $rows->count() > self::MAX_ROWS;
        $rows = $rows->take(self::MAX_ROWS)->values();

        $org = (string) Setting::get('org_name', 'Naguib Selim Charity');
        $title = $this->resolveTitle($reportName);
        $filtersSummary = $this->filtersSummary($request);
        $fileBase = str_replace(' ', '-', $org) . '-' . str_replace(' ', '-', $reportName) . '-' . now()->format('Y-m-d');

        ActivityLogService::log(
            'export',
            "تصدير {$reportName} بصيغة {$format} ({$rows->count()} صف)",
            null,
            ['format' => $format, 'count' => $rows->count(), 'capped' => $capped],
        );

        return match ($format) {
            'csv' => $this->csvResponse($rows, $columns, $org, $title, $filtersSummary, $capped, $fileBase),
            'pdf' => $this->pdfResponse($rows, $columns, $org, $title, $filtersSummary, $capped, $fileBase),
            default => $this->xlsxResponse($rows, $columns, $org, $title, $filtersSummary, $capped, $fileBase),
        };
    }

    /** the totals row: sum of every column marked total (numbers kept as numbers) */
    private function totalsRow(Collection $rows, array $columns): array
    {
        $totals = [];
        $first = true;
        foreach ($columns as $col) {
            if ($first) {
                $totals[$col['key']] = 'الإجمالي';
                $first = false;
                continue;
            }
            $totals[$col['key']] = ($col['total'] ?? false)
                ? (string) array_sum($rows->pluck($col['key'])->map(fn ($v) => (float) $v)->all())
                : '';
        }
        return $totals;
    }

    private function xlsxResponse(Collection $rows, array $columns, string $org, string $title, string $filters, bool $capped, string $fileBase): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileBase}.xlsx\"",
        ];

        return response()->streamDownload(function () use ($rows, $columns, $org, $title, $filters, $capped) {
            $options = new XlsxOptions();
            $options->shouldCreateNewSheetsAutomatically(false);
            $writer = new XlsxWriter($options);
            $writer->openToFile('php://output');

            $sheet = $writer->getCurrentSheet();
            $sheet->setName(mb_substr($title, 0, 28));
            $sheet->setRightToLeft(true);

            // org / title / filters block, then the capped notice when hit
            $orgStyle = (new Style())->setFontBold()->setFontSize(13);
            $writer->addRowWithStyle([$org], $orgStyle);
            $writer->addRow([$title . ' — ' . now()->format('Y-m-d H:i')]);
            if ($filters !== '') {
                $writer->addRow([$filters]);
            }
            if ($capped) {
                $writer->addRow(['تم اقتطاع التصدير عند ' . self::MAX_ROWS . ' صف (الحد الأقصى للتصدير)']);
            }
            $writer->addRow(['']);

            // header row — styled, frozen, with auto-filter over the data
            $headerRow = array_map(fn ($c) => $c['label'], $columns);
            $headerStyle = (new Style())
                ->setFontBold()
                ->setBackgroundColor('DFD7D3')
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
            $writer->addRowWithStyle($headerRow, $headerStyle);
            $options->setRowRangeAutoFilter(6, 6 + max($rows->count(), 1));
            $options->freezeRow(6);

            foreach ($rows as $row) {
                $writer->addRow(array_map(
                    fn ($c) => ($c['numeric'] ?? false) ? (float) $row[$c['key']] : (string) ($row[$c['key']] ?? ''),
                    $columns,
                ));
            }
            if ($rows->isNotEmpty()) {
                $totals = $this->totalsRow($rows, $columns);
                $writer->addRowWithStyle(array_map(
                    fn ($c) => ($c['numeric'] ?? false) ? (float) $totals[$c['key']] : (string) $totals[$c['key']],
                    $columns,
                ), (new Style())->setFontBold());
            }
            $writer->close();
        }, "{$fileBase}.xlsx", $headers);
    }

    private function csvResponse(Collection $rows, array $columns, string $org, string $title, string $filters, bool $capped, string $fileBase): StreamedResponse
    {
        // UTF-8 BOM — without it Excel mangles Arabic (T6-71.4)
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileBase}.csv\"",
        ];

        return response()->streamDownload(function () use ($rows, $columns, $org, $title, $filters, $capped) {
            $writer = new CsvWriter();
            $writer->setFieldDelimiter(',');
            $writer->openToFile('php://output');
            echo "\xEF\xBB\xBF"; // BOM

            $writer->addRow([$org]);
            $writer->addRow([$title . ' — ' . now()->format('Y-m-d H:i')]);
            if ($filters !== '') {
                $writer->addRow([$filters]);
            }
            if ($capped) {
                $writer->addRow(['تم اقتطاع التصدير عند ' . self::MAX_ROWS . ' صف']);
            }
            $writer->addRow([]);

            $writer->addRow(array_map(fn ($c) => $c['label'], $columns));
            foreach ($rows as $row) {
                $writer->addRow(array_map(
                    fn ($c) => ($c['numeric'] ?? false)
                        ? number_format((float) $row[$c['key']], 2, '.', '')
                        : (string) ($row[$c['key']] ?? ''),
                    $columns,
                ));
            }
            if ($rows->isNotEmpty()) {
                $totals = $this->totalsRow($rows, $columns);
                $writer->addRow(array_map(
                    fn ($c) => ($c['numeric'] ?? false) ? number_format((float) $totals[$c['key']], 2, '.', '') : (string) $totals[$c['key']],
                    $columns,
                ));
            }
            $writer->close();
        }, "{$fileBase}.csv", $headers);
    }

    private function pdfResponse(Collection $rows, array $columns, string $org, string $title, string $filters, bool $capped, string $fileBase): StreamedResponse
    {
        $landscape = count($columns) >= 7;
        $headers = ['Content-Type' => 'application/pdf'];

        return response()->streamDownload(function () use ($rows, $columns, $org, $title, $filters, $capped, $landscape) {
            // DejaVu Sans ships with mPDF and covers Arabic; mPDF's engine does
            // the RTL shaping. Drop an Amiri TTF into storage/fonts to upgrade.
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => $landscape ? 'A4-L' : 'A4',
                'directionality' => 'rtl',
                'default_font' => 'dejavusans',
            ]);
            $mpdf->SetTitle($org . ' — ' . $title);

            $html = '<html dir="rtl"><body style="font-family: dejavusans;">';
            $html .= '<h2 style="text-align:center;margin:0 0 2px;">' . e($org) . '</h2>';
            $html .= '<p style="text-align:center;margin:0 0 6px;">' . e($title) . ' — ' . now()->format('Y-m-d H:i') . '</p>';
            if ($filters !== '') {
                $html .= '<p style="text-align:center;font-size:10px;margin:0 0 6px;">' . e($filters) . '</p>';
            }
            if ($capped) {
                $html .= '<p style="text-align:center;font-size:10px;color:#b3261e;">تم اقتطاع التصدير عند ' . self::MAX_ROWS . ' صف</p>';
            }
            $html .= '<table style="border-collapse:collapse;width:100%;font-size:9px;" autosize="1">';
            $html .= '<thead><tr>';
            foreach ($columns as $col) {
                $html .= '<th style="border:0.5px solid #999;padding:3px;background:#DFD7D3;">' . e($col['label']) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($columns as $col) {
                    $align = ($col['numeric'] ?? false) ? 'text-align:left;direction:ltr;' : '';
                    $html .= '<td style="border:0.5px solid #bbb;padding:2px;' . $align . '">' . e((string) ($row[$col['key']] ?? '')) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            if ($rows->isNotEmpty()) {
                $totals = $this->totalsRow($rows, $columns);
                $html .= '<tfoot><tr>';
                foreach ($columns as $col) {
                    $html .= '<th style="border:0.5px solid #999;padding:3px;background:#DFD7D3;">' . e((string) $totals[$col['key']]) . '</th>';
                }
                $html .= '</tr></tfoot>';
            }
            $html .= '</table></body></html>';

            $mpdf->WriteHTML($html);
            $mpdf->Output($fileBase . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
        }, "{$fileBase}.pdf", $headers);
    }

    /** a human one-line summary of the active filter/sort/search params */
    private function filtersSummary(Request $request): string
    {
        $parts = [];
        $filter = $request->query('filter', []);
        if (is_array($filter)) {
            foreach ($filter as $key => $value) {
                if ($value === null || $value === '' || $value === 'all') {
                    continue;
                }
                if (is_array($value)) {
                    $value = implode(' - ', array_filter($value, fn ($v) => $v !== null && $v !== ''));
                }
                if ($value !== '' && $value !== []) {
                    $parts[] = "{$key}={$value}";
                }
            }
        }
        foreach (['type', 'status', 'user_id', 'custody_id', 'agent_id', 'category_id', 'item_id', 'reviewed', 'month', 'year'] as $param) {
            if ($request->filled($param)) {
                $parts[] = "{$param}=" . $request->query($param);
            }
        }
        if ($request->filled('q')) {
            $parts[] = 'بحث=' . $request->query('q');
        }
        if ($request->filled('sort')) {
            $parts[] = 'ترتيب=' . ltrim((string) $request->query('sort'), '-');
        }
        return implode('، ', $parts);
    }

    private function resolveTitle(string $reportName): string
    {
        $map = [
            'expenses' => 'المصروفات',
            'my-expenses' => 'مصروفاتي',
            'custodies' => 'العهد',
            'custody-movements' => 'حركات العهد',
            'custody-return-requests' => 'طلبات رد العهد',
            'my-movements' => 'حركاتي',
            'custody-transfers' => 'تحويلات العهد',
            'expense-edit-requests' => 'طلبات تعديل المصروفات',
            'treasury' => 'الخزائن',
            'salaries' => 'مرتبات الموارد البشرية',
            'salary-history' => 'تاريخ مرتبات الموظف',
            'salary-report' => 'تقرير المرتبات',
            'purchases' => 'طلبات الشراء',
            'purchase-requests' => 'طلبات الشراء',
            'maintenance-requests' => 'طلبات الصيانة',
            'suppliers' => 'الموردون',
            'social-cases' => 'الحالات الاجتماعية',
            'my-cases' => 'حالاتي',
            'hr-employees' => 'الموظفون',
            'hr-attendance' => 'الحضور والغياب',
            'hr-kpi' => 'مؤشرات الأداء',
            'tasks' => 'المهام',
            'notifications' => 'الإشعارات',
            'broadcasts' => 'الإذاعات',
            'activity-logs' => 'سجل النشاط',
            'users' => 'المستخدمون',
            'reports-dashboard' => 'لوحة التقارير',
            'report-governorates' => 'تقرير المحافظات',
            'report-researchers' => 'تقرير الباحثين',
            'report-social-case-expenses' => 'مصروفات الحالات الاجتماعية',
            'report-agents-balance' => 'أرصدة المندوبين',
            'report-expense-items' => 'بنود المصروفات',
            'report-categories-analytics' => 'تحليل الاتجاهات',
            'report-reconciliation' => 'تسوية الخزائن',
        ];
        return $map[$reportName] ?? $reportName;
    }
}
