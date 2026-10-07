<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Tests\TestCase;

/**
 * يتأكد أن كل قوالب Blade تترجم إلى PHP سليم البنية.
 * يحمي من الأخطاء الصامتة (مثل @php(...) inline بأقواس متداخلة
 * التي تبتلع @endphp التالي وتكسر كل البلوكات بعدها).
 */
class BladeCompilationTest extends TestCase
{
    public function test_all_blade_templates_compile_to_valid_php(): void
    {
        $files = new Filesystem();
        $compiler = new BladeCompiler($files, storage_path('framework/views/test-compile'));
        $bladeFiles = $files->allFiles(resource_path('views'));

        $this->assertNotEmpty($bladeFiles, 'لا توجد قوالب للفحص');

        $broken = [];
        foreach ($bladeFiles as $file) {
            $compiled = $compiler->compileString($files->get($file->getRealPath()));

            $tmp = tempnam(sys_get_temp_dir(), 'blade_') . '.php';
            file_put_contents($tmp, $compiled);
            $output = shell_exec('php -l ' . escapeshellarg($tmp) . ' 2>&1');
            unlink($tmp);

            if ($output === null || !str_contains((string) $output, 'No syntax errors')) {
                $broken[] = $file->getRelativePathname() . ' → ' . trim((string) $output);
            }
        }

        $this->assertSame([], $broken, 'قوالب مكسورة:' . PHP_EOL . implode(PHP_EOL, $broken));
    }
}
