<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SimpleXlsxExporter
{
    public static function download(string $filename, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $path = self::buildTempFile($headings, $rows);

            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, array{name: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public static function downloadMultiSheet(string $filename, array $sheets): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheets): void {
            $path = self::buildTempMultiSheetFile($sheets);

            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public static function buildTempFile(array $headings, array $rows): string
    {
        return self::buildTempMultiSheetFile([
            [
                'name' => 'Report',
                'headings' => $headings,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * @param  array<int, array{name: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public static function buildTempMultiSheetFile(array $sheets): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('افزونه zip برای خروجی اکسل فعال نیست.');
        }

        if ($sheets === []) {
            $sheets = [[
                'name' => 'Report',
                'headings' => [],
                'rows' => [],
            ]];
        }

        $path = tempnam(sys_get_temp_dir(), 'erp-xlsx-');

        if ($path === false) {
            throw new RuntimeException('امکان ایجاد فایل موقت اکسل وجود ندارد.');
        }

        $xlsxPath = $path . '.xlsx';
        @unlink($path);

        $zip = new ZipArchive();

        if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('امکان ایجاد فایل اکسل وجود ندارد.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml(count($sheets)));
        $zip->addFromString('_rels/.rels', self::relsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml(count($sheets)));
        $zip->addFromString('xl/styles.xml', self::stylesXml());

        foreach ($sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $zip->addFromString(
                'xl/worksheets/sheet' . $sheetNumber . '.xml',
                self::sheetXml($sheet['headings'] ?? [], $sheet['rows'] ?? [])
            );
        }

        $zip->close();

        return $xlsxPath;
    }

    private static function sheetXml(array $headings, array $rows): string
    {
        $sheetRows = '';
        $columnCount = max(1, count($headings));

        foreach (array_merge([$headings], $rows) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells = '';
            $styleId = $rowIndex === 0 ? '1' : '2';
            $values = array_values(is_array($row) ? $row : [$row]);
            $columnCount = max($columnCount, count($values), 1);

            foreach ($values as $columnIndex => $value) {
                $cellRef = self::columnLetter($columnIndex) . $rowNumber;
                $cells .= '<c r="' . $cellRef . '" s="' . $styleId . '" t="inlineStr"><is><t>'
                    . self::escapeXml((string) $value)
                    . '</t></is></c>';
            }

            $sheetRows .= '<row r="' . $rowNumber . '">' . $cells . '</row>';
        }

        if ($sheetRows === '') {
            $sheetRows = '<row r="1"><c r="A1" s="1" t="inlineStr"><is><t></t></is></c></row>';
        }

        $lastColumn = self::columnLetter($columnCount - 1);
        $lastRow = max(1, count($rows) + 1);

        // OOXML order matters: dimension → sheetViews → sheetData
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>';
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        $number = $index + 1;

        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)) . $letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }

    private static function escapeXml(string $value): string
    {
        // Excel rejects control characters below 0x20 except tab/LF/CR.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypesXml(int $sheetCount = 1): string
    {
        $overrides = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param  array<int, array{name: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    private static function workbookXml(array $sheets): string
    {
        $sheetNodes = '';

        foreach ($sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $sheetName = self::escapeXml(self::sanitizeSheetName($sheet['name'] ?? ('Sheet' . $sheetNumber)));
            $sheetNodes .= '<sheet name="' . $sheetName . '" sheetId="' . $sheetNumber . '" r:id="rId' . $sheetNumber . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView windowWidth="24000" windowHeight="14000" rightToLeft="1"/></bookViews>'
            . '<sheets>' . $sheetNodes . '</sheets>'
            . '</workbook>';
    }

    private static function workbookRelsXml(int $sheetCount): string
    {
        $relationships = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
        }

        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    private static function sanitizeSheetName(string $name): string
    {
        $name = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name));

        if ($name === '') {
            return 'Sheet';
        }

        return mb_substr($name, 0, 31);
    }

    private static function stylesXml(): string
    {
        // Excel requires the second fill to be gray125 even when unused.
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><color theme="1"/><name val="Tahoma"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color theme="1"/><name val="Tahoma"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FF9CA3AF"/></left>'
            . '<right style="thin"><color rgb="FF9CA3AF"/></right>'
            . '<top style="thin"><color rgb="FF9CA3AF"/></top>'
            . '<bottom style="thin"><color rgb="FF9CA3AF"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            . '<alignment horizontal="right" vertical="center" readingOrder="2" wrapText="1"/>'
            . '</xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
            . '<alignment horizontal="right" vertical="center" readingOrder="2" wrapText="1"/>'
            . '</xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
