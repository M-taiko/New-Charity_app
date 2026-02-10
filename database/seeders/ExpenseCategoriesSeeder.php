<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;

class ExpenseCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Category 1: الزكاة (Zakat)
        $zakah = ExpenseCategory::create([
            'name' => 'الزكاة',
            'code' => 'ZAKAH',
            'description' => 'فئة الزكاة وأحكام الزكاة الشرعية',
            'is_active' => true,
            'order' => 1,
        ]);

        // Zakah items
        $zakahItems = [
            ['name' => 'كفالة يتيم', 'code' => 'ZAKAH_001', 'default_amount' => 500, 'order' => 1],
            ['name' => 'مساعدة أسرة محتاجة', 'code' => 'ZAKAH_002', 'default_amount' => 1000, 'order' => 2],
            ['name' => 'علاج مريض', 'code' => 'ZAKAH_003', 'default_amount' => 2000, 'order' => 3],
            ['name' => 'سداد دين', 'code' => 'ZAKAH_004', 'default_amount' => 5000, 'order' => 4],
            ['name' => 'مساعدة طالب علم', 'code' => 'ZAKAH_005', 'default_amount' => 800, 'order' => 5],
            ['name' => 'كفالة أرملة', 'code' => 'ZAKAH_006', 'default_amount' => 600, 'order' => 6],
            ['name' => 'إعانة غارم', 'code' => 'ZAKAH_007', 'default_amount' => 3000, 'order' => 7],
        ];

        foreach ($zakahItems as $item) {
            ExpenseItem::create([
                'expense_category_id' => $zakah->id,
                'name' => $item['name'],
                'code' => $item['code'],
                'default_amount' => $item['default_amount'],
                'is_active' => true,
                'order' => $item['order'],
            ]);
        }

        // Category 2: الصدقات (Charity)
        $charity = ExpenseCategory::create([
            'name' => 'الصدقات',
            'code' => 'CHARITY',
            'description' => 'فئة الصدقات والمساعدات الخيرية',
            'is_active' => true,
            'order' => 2,
        ]);

        // Charity items
        $charityItems = [
            ['name' => 'إفطار صائم', 'code' => 'CHARITY_001', 'default_amount' => 200, 'order' => 1],
            ['name' => 'كسوة محتاج', 'code' => 'CHARITY_002', 'default_amount' => 300, 'order' => 2],
            ['name' => 'حقيبة غذائية', 'code' => 'CHARITY_003', 'default_amount' => 250, 'order' => 3],
            ['name' => 'مساعدة طارئة', 'code' => 'CHARITY_004', 'default_amount' => 500, 'order' => 4],
            ['name' => 'أضحية', 'code' => 'CHARITY_005', 'default_amount' => 1500, 'order' => 5],
            ['name' => 'مشروع خيري', 'code' => 'CHARITY_006', 'default_amount' => 2000, 'order' => 6],
            ['name' => 'تبرع عام', 'code' => 'CHARITY_007', 'default_amount' => null, 'order' => 7],
        ];

        foreach ($charityItems as $item) {
            ExpenseItem::create([
                'expense_category_id' => $charity->id,
                'name' => $item['name'],
                'code' => $item['code'],
                'default_amount' => $item['default_amount'],
                'is_active' => true,
                'order' => $item['order'],
            ]);
        }

        // Category 3: مصروفات أخرى (Other Expenses)
        ExpenseCategory::create([
            'name' => 'مصروفات أخرى',
            'code' => 'OTHER',
            'description' => 'مصروفات عامة متنوعة بدون تصنيف محدد',
            'is_active' => true,
            'order' => 3,
        ]);
        // لا بنود لهذه الفئة - المستخدم يدخل الوصف مباشرة
    }
}
