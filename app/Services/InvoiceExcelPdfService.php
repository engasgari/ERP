<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class InvoiceExcelPdfService
{
    public function __construct(
        private readonly InvoiceExcelTemplateService $excel,
    ) {
    }

    /**
     * Build the official Excel invoice and convert it to PDF for exact print parity.
     */
    public function build(Invoice $invoice, ?CompanySetting $company): string
    {
        $xlsx = $this->excel->build($invoice, $company);

        try {
            $pdf = $this->convertToPdf($xlsx);
        } finally {
            @unlink($xlsx);
        }

        return $pdf;
    }

    /**
     * Prefer PDF from the official Excel template; fall back to the Excel file itself.
     *
     * @return array{path:string,filename:string,mime:string}
     */
    public function buildPrintArtifact(Invoice $invoice, ?CompanySetting $company, string $filenamePrefix = 'invoice'): array
    {
        $safeNumber = preg_replace('/[^\w\-]+/u', '_', (string) $invoice->number) ?: (string) $invoice->id;

        try {
            $pdf = $this->build($invoice, $company);

            return [
                'path' => $pdf,
                'filename' => $filenamePrefix.'-'.$safeNumber.'.pdf',
                'mime' => 'application/pdf',
            ];
        } catch (\Throwable) {
            $xlsx = $this->excel->build($invoice, $company);

            return [
                'path' => $xlsx,
                'filename' => $filenamePrefix.'-'.$safeNumber.'.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }
    }

    private function convertToPdf(string $xlsxPath): string
    {
        $directory = dirname($xlsxPath);
        $pdfPath = $directory.DIRECTORY_SEPARATOR.pathinfo($xlsxPath, PATHINFO_FILENAME).'.pdf';

        if ($this->convertWithLibreOffice($xlsxPath, $directory, $pdfPath)) {
            return $pdfPath;
        }

        if ($this->convertWithExcelCom($xlsxPath, $pdfPath)) {
            return $pdfPath;
        }

        throw new RuntimeException(
            'امکان تبدیل فاکتور اکسل به PDF وجود ندارد. LibreOffice یا Excel باید روی سرور نصب باشد.'
        );
    }

    private function convertWithLibreOffice(string $xlsxPath, string $directory, string $pdfPath): bool
    {
        $binary = $this->libreOfficeBinary();

        if ($binary === null) {
            return false;
        }

        $lockPath = storage_path('app/temp/libreoffice-convert.lock');
        @mkdir(dirname($lockPath), 0775, true);
        $lock = @fopen($lockPath, 'c+');

        if ($lock === false) {
            return false;
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                return false;
            }

            $profileDir = $directory.DIRECTORY_SEPARATOR.'lo-profile-'.uniqid('', true);
            @mkdir($profileDir, 0775, true);
            $profileUri = 'file://'.str_replace('\\', '/', $profileDir);

            $process = new Process([
                $binary,
                '--headless',
                '--nologo',
                '--nofirststartwizard',
                '--norestore',
                '-env:UserInstallation='.$profileUri,
                '--convert-to',
                'pdf:calc_pdf_Export',
                '--outdir',
                $directory,
                $xlsxPath,
            ]);
            $process->setTimeout(60);
            $process->setEnv([
                'HOME' => $profileDir,
                'TMPDIR' => $directory,
            ]);

            try {
                $process->run();
            } catch (ProcessTimedOutException) {
                $this->killLibreOffice();

                return false;
            } finally {
                $this->deleteDirectory($profileDir);
            }

            return $process->isSuccessful() && is_file($pdfPath);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function convertWithExcelCom(string $xlsxPath, string $pdfPath): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $script = <<<'PS1'
param([string]$InputPath, [string]$OutputPath)
$excel = $null
$workbook = $null
try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false
    $workbook = $excel.Workbooks.Open($InputPath)
    # 0 = xlTypePDF
    $workbook.ExportAsFixedFormat(0, $OutputPath)
    if (-not (Test-Path -LiteralPath $OutputPath)) {
        throw "PDF was not created"
    }
    exit 0
} catch {
    Write-Error $_.Exception.Message
    exit 1
} finally {
    if ($workbook -ne $null) { $workbook.Close($false) | Out-Null }
    if ($excel -ne $null) { $excel.Quit() | Out-Null }
    if ($workbook -ne $null) { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($workbook) | Out-Null }
    if ($excel -ne $null) { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
PS1;

        $scriptFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'erp-invoice-excel-pdf-'.uniqid('', true).'.ps1';
        file_put_contents($scriptFile, $script);

        try {
            $process = new Process([
                'powershell',
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $scriptFile,
                '-InputPath',
                $xlsxPath,
                '-OutputPath',
                $pdfPath,
            ]);
            $process->setTimeout(60);

            try {
                $process->run();
            } catch (ProcessTimedOutException) {
                return false;
            }

            return $process->isSuccessful() && is_file($pdfPath);
        } finally {
            @unlink($scriptFile);
        }
    }

    private function libreOfficeBinary(): ?string
    {
        foreach (['soffice', 'libreoffice'] as $binary) {
            $process = Process::fromShellCommandline(
                PHP_OS_FAMILY === 'Windows' ? 'where '.$binary : 'command -v '.$binary
            );
            $process->run();

            if (! $process->isSuccessful()) {
                continue;
            }

            $path = trim($process->getOutput());
            if ($path !== '') {
                $first = preg_split('/\r\n|\r|\n/', $path)[0] ?? '';

                return $first !== '' ? $first : $binary;
            }
        }

        return null;
    }

    private function killLibreOffice(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }

        $process = Process::fromShellCommandline('pkill -9 -f soffice.bin || true; pkill -9 -f libreoffice || true');
        $process->setTimeout(10);
        $process->run();
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
