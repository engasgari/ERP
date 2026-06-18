<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditErpUiFramework extends Command
{
    protected $signature = 'erp-ui:audit {--strict : در صورت وجود markup اختصاصی با کد خطا خارج شود}';

    protected $description = 'بررسی استفاده مستقیم صفحات از جدول، فیلتر و مودال خارج از Core/UI';

    public function handle(): int
    {
        $root = resource_path('views');
        $allowed = collect([
            resource_path('views/components/erp/ui'),
            resource_path('views/livewire/core/ui'),
            resource_path('views/components/erp-modal-script.blade.php'),
        ])->map(fn ($path) => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path))->all();

        $patterns = ['erp-filter-panel', 'erp-compact-table', 'erp-modal-backdrop', 'erp-modal-panel'];
        $violations = [];

        foreach (File::allFiles($root) as $file) {
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file->getPathname());

            if (collect($allowed)->contains(fn ($allowedPath) => str_starts_with($path, $allowedPath))) {
                continue;
            }

            $contents = File::get($path);
            foreach ($patterns as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = [str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path), $pattern];
                }
            }
        }

        if ($violations === []) {
            $this->info('همه صفحات بررسی‌شده از Core/UI پیروی می‌کنند.');

            return self::SUCCESS;
        }

        $this->warn('صفحات زیر هنوز markup اختصاصی دارند و باید به Core/UI مهاجرت کنند:');
        $this->table(['فایل', 'الگوی مستقیم'], $violations);

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
