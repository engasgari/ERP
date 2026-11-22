<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use RuntimeException;
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
        $pdf = $this->convertToPdf($xlsx);

        @unlink($xlsx);

        return $pdf;
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

        $process = new Process([
            $binary,
            '--headless',
            '--nologo',
            '--nofirststartwizard',
            '--convert-to',
            'pdf:calc_pdf_Export',
            '--outdir',
            $directory,
            $xlsxPath,
        ]);
        $process->setTimeout(120);
        $process->setEnv([
            'HOME' => $directory,
            'TMPDIR' => $directory,
        ]);
        $process->run();

        return $process->isSuccessful() && is_file($pdfPath);
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
            $process->setTimeout(120);
            $process->run();

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
}
