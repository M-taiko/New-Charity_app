<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SocialCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use ZipArchive;
use SimpleXMLElement;

class SocialCasesExcelSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('جاري استيراد بيانات الحالات الاجتماعية من Excel...');

        $excelFile = __DIR__ . '/../../الحالات الاجتماعيه .xlsx';

        if (!file_exists($excelFile)) {
            $this->command->error('ملف Excel غير موجود: ' . $excelFile);
            return;
        }

        try {
            $rows = $this->parseExcel($excelFile);
            $importedCount = 0;
            $skippedCount = 0;
            $batch = [];
            $batchSize = 50;

            $defaultResearcher = User::first();
            $researcherId = $defaultResearcher ? $defaultResearcher->id : null;

            // Based on actual Excel analysis, the real column structure is:
            // D = الاسم (Name)
            // H = رقم الهاتف (Phone - in scientific notation)
            // I = الحالة الإجتماعية/ملخص الحالة (Marital Status/Case Summary)
            // J = العنوان (Address)
            // K = مركز/حي (District)
            // L = المحافظة (City)
            // M = وصف المسكن (House Description)
            // N = الدخل والعمل (Monthly Income/Work)
            // O = وصف الحالة (Description)
            // P = المساعدة المطلوبة (Assistance Required)
            // Q = قيمة المساعدة (Requested Amount)
            // R = نوع الحالة (Case Type)

            // Column indices (A=0, B=1, C=2, D=3, E=4, F=5, G=6, H=7, I=8, J=9, K=10, L=11, M=12, N=13, O=14, P=15, Q=16, R=17...)
            $headerRowIndex = 0; // First row is the header
            $fieldMapping = [
                'name' => 3,                  // D (الاسم)
                'phone' => 7,                 // H (رقم الهاتف)
                'marital_status' => 8,       // I (الحالة الإجتماعية)
                'address' => 9,              // J (العنوان)
                'district' => 10,            // K (مركز/حي)
                'city' => 11,                // L (المحافظة)
                'house_description' => 12,   // M (وصف المسكن)
                'monthly_income' => 13,      // N (الدخل والعمل)
                'description' => 14,         // O (وصف الحالة)
                'assistance_required' => 15, // P (المساعدة المطلوبة)
                'requested_amount' => 16,    // Q (قيمة المساعدة)
                'case_type' => 17,           // R (نوع الحالة)
            ];

            if ($headerRowIndex < 0) {
                $this->command->error('لم يتم العثور على صف رؤوس الأعمدة');
                return;
            }

            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex <= $headerRowIndex) continue; // Skip header row

                try {
                    $name = trim($row[$fieldMapping['name']] ?? '');
                    $phone = $row[$fieldMapping['phone']] ?? null;

                    if (empty($name)) {
                        $skippedCount++;
                        continue;
                    }

                    if (SocialCase::where('name', trim($name))->where('phone', $phone)->exists()) {
                        $skippedCount++;
                        continue;
                    }

                    // Parse phone number (handle scientific notation)
                    $parsedPhone = $phone;
                    if (is_numeric($phone) && strpos($phone, 'E') !== false) {
                        $parsedPhone = intval((float)$phone);
                    }

                    $batch[] = [
                        'researcher_id' => $researcherId,
                        'name' => trim($name),
                        'phone' => $parsedPhone,
                        'address' => $row[$fieldMapping['address']] ?? null,
                        'city' => $row[$fieldMapping['city']] ?? null,
                        'district' => $row[$fieldMapping['district']] ?? null,
                        'age' => null, // Age data not available in the actual Excel structure
                        'marital_status' => $row[$fieldMapping['marital_status']] ?? null,
                        'description' => $row[$fieldMapping['description']] ?? null,
                        'house_description' => $row[$fieldMapping['house_description']] ?? null,
                        'monthly_income' => $row[$fieldMapping['monthly_income']] ?? null,
                        'assistance_required' => $row[$fieldMapping['assistance_required']] ?? null,
                        'requested_amount' => $this->parseAmount($row[$fieldMapping['requested_amount']] ?? null),
                        'case_type' => $this->cleanCaseType($row[$fieldMapping['case_type']] ?? null),
                        'case_intake_date' => null,
                        'assistance_date' => null,
                        'case_outcome' => null,
                        'dependent_on' => null,
                        'affiliated_to' => null,
                        'status' => 'new',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::table('social_cases')->insertOrIgnore($batch);
                        $importedCount += count($batch);
                        $this->command->line("✓ تم استيراد {$importedCount} حالة...");
                        $batch = [];
                    }
                } catch (\Exception $e) {
                    $skippedCount++;
                    continue;
                }
            }

            if (!empty($batch)) {
                DB::table('social_cases')->insertOrIgnore($batch);
                $importedCount += count($batch);
            }

            $this->command->info("✅ تم استيراد {$importedCount} حالة اجتماعية بنجاح");
            if ($skippedCount > 0) {
                $this->command->warn("⚠️ تم تخطي {$skippedCount} حالة");
            }
        } catch (\Exception $e) {
            $this->command->error('خطأ: ' . $e->getMessage());
        }
    }

    private function parseExcel($filePath)
    {
        $zip = new ZipArchive();
        $zip->open($filePath);

        // Load shared strings
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $stringsXml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
            foreach ($stringsXml->si as $si) {
                $sharedStrings[] = (string)$si->t;
            }
        }

        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                // Get column letter from cell reference (e.g., "D2" -> "D")
                $ref = (string)$cell['r'];
                $colLetter = preg_replace('/[0-9]/', '', $ref);
                $colIndex = $this->columnLetterToIndex($colLetter);

                $type = (string)$cell['t']; // "s" for string (shared string index), "n" for number
                $val = (string)$cell->v;

                // If type is "s", the value is an index into sharedStrings
                if ($type === 's' && isset($sharedStrings[(int)$val])) {
                    $val = $sharedStrings[(int)$val];
                }

                $rowData[$colIndex] = $val;
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    private function columnLetterToIndex($letter)
    {
        $index = 0;
        for ($i = 0; $i < strlen($letter); $i++) {
            $index = $index * 26 + (ord($letter[$i]) - ord('A') + 1);
        }
        return $index - 1; // 0-based index
    }

    private function parseAmount($value)
    {
        if (empty($value)) {
            return null;
        }
        preg_match('/(\d+)/', str_replace([',', 'ج'], '', (string)$value), $matches);
        return !empty($matches[1]) ? floatval($matches[1]) : null;
    }

    private function cleanCaseType($value)
    {
        if (empty($value)) {
            return 'أخرى';
        }
        $cleaned = trim((string)$value);
        $allowedTypes = ['عمليات', 'غارمين', 'مساعدة مادية', 'علاج مؤقت', 'تعليم', 'أخرى'];
        return in_array($cleaned, $allowedTypes) ? $cleaned : 'أخرى';
    }
}
