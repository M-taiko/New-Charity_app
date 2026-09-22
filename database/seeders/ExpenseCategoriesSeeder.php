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
            ['name' => 'شهريات', 'code' => 'ZAKAH_001', 'default_amount' => null, 'order' => 1],
            ['name' => 'علاج شهري', 'code' => 'ZAKAH_002', 'default_amount' => null, 'order' => 2],
            ['name' => 'علاج', 'code' => 'ZAKAH_003', 'default_amount' => null, 'order' => 3],
            ['name' => 'غارمين', 'code' => 'ZAKAH_004', 'default_amount' => null, 'order' => 4],
            ['name' => 'عمليات', 'code' => 'ZAKAH_005', 'default_amount' => null, 'order' => 5],
            ['name' => 'زواج', 'code' => 'ZAKAH_006', 'default_amount' => null, 'order' => 6],
            ['name' => 'مساعدات مادية', 'code' => 'ZAKAH_007', 'default_amount' => null, 'order' => 7],
            ['name' => 'أجهزة تعويضية', 'code' => 'ZAKAH_008', 'default_amount' => null, 'order' => 8],
            ['name' => 'تجهيزات ومصروفات دار عين شمس', 'code' => 'ZAKAH_009', 'default_amount' => null, 'order' => 9],
            ['name' => 'انتقالات زكاة', 'code' => 'ZAKAH_010', 'default_amount' => null, 'order' => 10],
            ['name' => 'بنزين', 'code' => 'ZAKAH_011', 'default_amount' => null, 'order' => 11],
            ['name' => 'نقل', 'code' => 'ZAKAH_012', 'default_amount' => null, 'order' => 12],
            ['name' => 'أدوات مكتبية ومطبوعات', 'code' => 'ZAKAH_013', 'default_amount' => null, 'order' => 13],
            ['name' => 'م.بريد', 'code' => 'ZAKAH_014', 'default_amount' => null, 'order' => 14],
            ['name' => 'م.طعام', 'code' => 'ZAKAH_015', 'default_amount' => null, 'order' => 15],
            ['name' => 'مرتبات وبدلات', 'code' => 'ZAKAH_016', 'default_amount' => null, 'order' => 16],
            ['name' => 'سكن', 'code' => 'ZAKAH_017', 'default_amount' => null, 'order' => 17],
            ['name' => 'تأمين طبي', 'code' => 'ZAKAH_018', 'default_amount' => null, 'order' => 18],
            ['name' => 'مستلزمات وصيانات الجمعية', 'code' => 'ZAKAH_019', 'default_amount' => null, 'order' => 19],
            ['name' => 'ايجار ومرافق الجمعية', 'code' => 'ZAKAH_020', 'default_amount' => null, 'order' => 20],
            ['name' => 'سقي الماء', 'code' => 'ZAKAH_021', 'default_amount' => null, 'order' => 21],
            ['name' => 'رسوم واشتراكات الجمعية', 'code' => 'ZAKAH_022', 'default_amount' => null, 'order' => 22],
            ['name' => 'بوفيه الجمعية', 'code' => 'ZAKAH_023', 'default_amount' => null, 'order' => 23],
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
            ['name' => 'تجهيزات ومصروفات دار شبرامنت', 'code' => 'CHARITY_001', 'default_amount' => null, 'order' => 1],
            ['name' => 'أدوات وخامات مستهلكة', 'code' => 'CHARITY_002', 'default_amount' => null, 'order' => 2],
            ['name' => 'صدقات', 'code' => 'CHARITY_003', 'default_amount' => null, 'order' => 3],
            ['name' => 'مسحوبات شخصية (خاص)', 'code' => 'CHARITY_004', 'default_amount' => null, 'order' => 4],
            ['name' => 'مائدة رمضان 21', 'code' => 'CHARITY_005', 'default_amount' => null, 'order' => 5],
            ['name' => 'زكاة الفطر', 'code' => 'CHARITY_006', 'default_amount' => null, 'order' => 6],
            ['name' => 'محطة المياه بسوهاج', 'code' => 'CHARITY_007', 'default_amount' => null, 'order' => 7],
            ['name' => 'قروض حسنة', 'code' => 'CHARITY_008', 'default_amount' => null, 'order' => 8],
            ['name' => 'شنط رمضان 18', 'code' => 'CHARITY_009', 'default_amount' => null, 'order' => 9],
            ['name' => 'شنط رمضان 19', 'code' => 'CHARITY_010', 'default_amount' => null, 'order' => 10],
            ['name' => 'وجبات رمضان 20', 'code' => 'CHARITY_011', 'default_amount' => null, 'order' => 11],
            ['name' => 'شنط رمضان 21', 'code' => 'CHARITY_012', 'default_amount' => null, 'order' => 12],
            ['name' => 'زكاة خارج العهدة', 'code' => 'CHARITY_013', 'default_amount' => null, 'order' => 13],
            ['name' => 'صدقات خارج العهدة', 'code' => 'CHARITY_014', 'default_amount' => null, 'order' => 14],
            ['name' => 'شنط رمضان 2022', 'code' => 'CHARITY_015', 'default_amount' => null, 'order' => 15],
            ['name' => 'مائدة رمضان 2022', 'code' => 'CHARITY_016', 'default_amount' => null, 'order' => 16],
            ['name' => 'شنط رمضان 2023', 'code' => 'CHARITY_017', 'default_amount' => null, 'order' => 17],
            ['name' => 'مائدة رمضان 2023', 'code' => 'CHARITY_018', 'default_amount' => null, 'order' => 18],
            ['name' => 'شنط رمضان 2024', 'code' => 'CHARITY_019', 'default_amount' => null, 'order' => 19],
            ['name' => 'مائدة رمضان 2024', 'code' => 'CHARITY_020', 'default_amount' => null, 'order' => 20],
            ['name' => 'شنط رمضان 2025', 'code' => 'CHARITY_021', 'default_amount' => null, 'order' => 21],
            ['name' => 'مائدة رمضان في شبرامنت 2025', 'code' => 'CHARITY_022', 'default_amount' => null, 'order' => 22],
            ['name' => 'مطبخ عين شمس 2025', 'code' => 'CHARITY_023', 'default_amount' => null, 'order' => 23],
            ['name' => 'مطبخ العاشر والأمل من رمضان 2025', 'code' => 'CHARITY_024', 'default_amount' => null, 'order' => 24],
            ['name' => 'وجبات موظفين الجمعية 2025', 'code' => 'CHARITY_025', 'default_amount' => null, 'order' => 25],
            ['name' => 'وجبات فروع الشركة 2025', 'code' => 'CHARITY_026', 'default_amount' => null, 'order' => 26],
            ['name' => 'حساب تعبئة شنط رمضان 2025', 'code' => 'CHARITY_027', 'default_amount' => null, 'order' => 27],
            ['name' => 'مائدة القلعة والموسكي 2025', 'code' => 'CHARITY_028', 'default_amount' => null, 'order' => 28],
            ['name' => 'وجبات افطار صائم 2025', 'code' => 'CHARITY_029', 'default_amount' => null, 'order' => 29],
            ['name' => 'مصروفات خارج العهدة', 'code' => 'CHARITY_030', 'default_amount' => null, 'order' => 30],
            ['name' => 'صيانة وقطع غيار سيارات', 'code' => 'CHARITY_031', 'default_amount' => null, 'order' => 31],
            ['name' => 'تراخيص', 'code' => 'CHARITY_032', 'default_amount' => null, 'order' => 32],
            ['name' => 'مخالفات السيارات', 'code' => 'CHARITY_033', 'default_amount' => null, 'order' => 33],
            ['name' => 'نقل وانتقالات - صدقات', 'code' => 'CHARITY_034', 'default_amount' => null, 'order' => 34],
            ['name' => 'شحن رصيد', 'code' => 'CHARITY_035', 'default_amount' => null, 'order' => 35],
            ['name' => 'اكراميات', 'code' => 'CHARITY_036', 'default_amount' => null, 'order' => 36],
            ['name' => 'سلف', 'code' => 'CHARITY_037', 'default_amount' => null, 'order' => 37],
            ['name' => 'ضيافة', 'code' => 'CHARITY_038', 'default_amount' => null, 'order' => 38],
            ['name' => 'مشتريات حوائج الناس', 'code' => 'CHARITY_039', 'default_amount' => null, 'order' => 39],
            ['name' => 'بيوت اسوان 21', 'code' => 'CHARITY_040', 'default_amount' => null, 'order' => 40],
            ['name' => 'قوص - وصلات مياة 21', 'code' => 'CHARITY_041', 'default_amount' => null, 'order' => 41],
            ['name' => 'اسيوط - اللحاف 21', 'code' => 'CHARITY_042', 'default_amount' => null, 'order' => 42],
            ['name' => 'الاقصر - وصلات مياة 21', 'code' => 'CHARITY_043', 'default_amount' => null, 'order' => 43],
            ['name' => 'ادوات نظافة الجمعية', 'code' => 'CHARITY_044', 'default_amount' => null, 'order' => 44],
            ['name' => 'مستلزمات محطة مياه شبرامنت', 'code' => 'CHARITY_045', 'default_amount' => null, 'order' => 45],
            ['name' => 'ايجار ومرافق محطة مياه شبرامنت', 'code' => 'CHARITY_046', 'default_amount' => null, 'order' => 46],
            ['name' => 'مرتبات - صدقات', 'code' => 'CHARITY_047', 'default_amount' => null, 'order' => 47],
            ['name' => 'صيانات محطة مياه شبرامنت', 'code' => 'CHARITY_048', 'default_amount' => null, 'order' => 48],
            ['name' => 'بوفيه - نظافة محطة مياه شبرامنت', 'code' => 'CHARITY_049', 'default_amount' => null, 'order' => 49],
            ['name' => 'ذبائح', 'code' => 'CHARITY_050', 'default_amount' => null, 'order' => 50],
            ['name' => 'اطعام', 'code' => 'CHARITY_051', 'default_amount' => null, 'order' => 51],
            ['name' => 'المحضن', 'code' => 'CHARITY_052', 'default_amount' => null, 'order' => 52],
            ['name' => 'أصول', 'code' => 'CHARITY_053', 'default_amount' => null, 'order' => 53],
            ['name' => 'مستلزمات وصيانة - صدقات', 'code' => 'CHARITY_054', 'default_amount' => null, 'order' => 54],
            ['name' => 'مصاحف وكتب', 'code' => 'CHARITY_055', 'default_amount' => null, 'order' => 55],
            ['name' => 'اعمار مساجد', 'code' => 'CHARITY_056', 'default_amount' => null, 'order' => 56],
            ['name' => 'مساعدات حالات الشركة', 'code' => 'CHARITY_057', 'default_amount' => null, 'order' => 57],
            ['name' => 'اجهزة طبية', 'code' => 'CHARITY_058', 'default_amount' => null, 'order' => 58],
            ['name' => 'عجوزات', 'code' => 'CHARITY_059', 'default_amount' => null, 'order' => 59],
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
