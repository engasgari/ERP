<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Party;
use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

class InvoiceExcelTemplateService
{
    private const LINES_PER_PAGE = 8;

    private const PAGE_HEIGHT = 34;

    private const FIRST_LINE_ROW = 21;

    private const LAST_LINE_ROW = 28;

    /** @var list<string> */
    private const LINE_COLUMNS = ['B', 'D', 'G', 'T', 'W', 'Z', 'AE', 'AL', 'AQ', 'AX', 'BC'];

    /** @var list<string> Left-to-right digit boxes: AH is visually right, AS is visually left. */
    private const ECONOMIC_CODE_COLUMNS = ['AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS'];

    /** @var list<string> Extra template cells that must be cleared on the economic-code row. */
    private const ECONOMIC_CODE_CLEAR_COLUMNS = ['AG'];

    /** @var list<string> */
    private const REGISTRATION_COLUMNS = ['AY', 'AZ', 'BA', 'BB', 'BC', 'BD'];

    /** @var list<string> */
    private const POSTAL_CODE_COLUMNS = ['AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ'];

    /** @var list<string> */
    private const NATIONAL_ID_COLUMNS = ['AY', 'AZ', 'BA', 'BB', 'BC', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI'];

    public function build(Invoice $invoice, ?CompanySetting $company): string
    {
        $template = storage_path('app/invoice_sample.xlsx');

        if (! is_file($template)) {
            throw new RuntimeException('فایل قالب اکسل فاکتور پیدا نشد: storage/app/invoice_sample.xlsx');
        }

        $target = storage_path('app/temp/invoice-' . $invoice->id . '-' . uniqid() . '.xlsx');

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        copy($template, $target);

        $zip = new ZipArchive();

        if ($zip->open($target) !== true) {
            throw new RuntimeException('امکان باز کردن فایل اکسل قالب وجود ندارد.');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('ساختار فایل اکسل قالب معتبر نیست.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($sheetXml);

        $invoice->loadMissing('party', 'project', 'lines.item.unit');

        $isSale = $invoice->direction === 'sale';

        $seller = $isSale
            ? $this->companyProfile($company)
            : $this->partyProfile($invoice->party);

        $buyer = $isSale
            ? $this->partyProfile($invoice->party)
            : $this->companyProfile($company);

        $lines = $invoice->lines->values();
        $pageCount = max(1, (int) ceil($lines->count() / self::LINES_PER_PAGE));

        $this->ensurePageCount($dom, $pageCount);

        $subtotal = 0;
        $discountTotal = 0;
        $afterDiscountTotal = 0;
        $taxTotal = 0;
        $finalTotal = 0;

        foreach ($lines as $line) {
            $base = (float) $line->quantity * (float) $line->unit_price;
            $afterDiscount = max($base - (float) $line->discount_amount, 0);

            $subtotal += $base;
            $discountTotal += (float) $line->discount_amount;
            $afterDiscountTotal += $afterDiscount;
            $taxTotal += (float) $line->tax_amount;
            $finalTotal += (float) $line->line_total;
        }

        for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
            $this->fillPageHeader($dom, $invoice, $seller, $buyer, $pageIndex);

            $pageLines = $lines->slice($pageIndex * self::LINES_PER_PAGE, self::LINES_PER_PAGE)->values();
            $row = $this->pageRow($pageIndex, self::FIRST_LINE_ROW);

            foreach ($pageLines as $index => $line) {
                $this->fillLineRow($dom, $row, $line, ($pageIndex * self::LINES_PER_PAGE) + $index);
                $row++;
            }

            for (; $row <= $this->pageRow($pageIndex, self::LAST_LINE_ROW); $row++) {
                $this->clearLineRow($dom, $row);
            }

            if ($pageIndex < $pageCount - 1) {
                $this->fillContinuationFooter($dom, $pageIndex);
            } else {
                $this->fillTotalsFooter(
                    $dom,
                    $pageIndex,
                    $subtotal,
                    $discountTotal,
                    $afterDiscountTotal,
                    $taxTotal,
                    $finalTotal,
                    $invoice
                );
            }
        }

        if ($pageCount > 1) {
            $this->configurePrintSetup($dom, $pageCount);
        }

        $this->updateSheetDimension($dom, $pageCount * self::PAGE_HEIGHT);

        $zip->deleteName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet1.xml', $dom->saveXML());
        $this->removeCalcChain($zip);

        if ($pageCount > 1) {
            $this->updatePrintArea($zip, $pageCount);
            $this->duplicateDrawings($zip, $pageCount);
        }

        $zip->close();

        return $target;
    }

    /**
     * @param  array{name:string,economic_code:string,registration_number:string,postal_code:string,national_id:string,address:string,phone:string,province:string,city:string}  $seller
     * @param  array{name:string,economic_code:string,registration_number:string,postal_code:string,national_id:string,address:string,phone:string,province:string,city:string}  $buyer
     */
    private function fillPageHeader(DOMDocument $dom, Invoice $invoice, array $seller, array $buyer, int $pageIndex): void
    {
        $this->setText($dom, $this->pageCoordinate($pageIndex, 'S1'), $this->documentTitle($invoice));
        $this->setText($dom, $this->pageCoordinate($pageIndex, 'E3'), gregorianToJalaliDate($invoice->invoice_date));
        $this->setText($dom, $this->pageCoordinate($pageIndex, 'BE3'), $invoice->number);
        $this->setText(
            $dom,
            $this->pageCoordinate($pageIndex, 'K5'),
            'پروژه: ' . ($invoice->project?->code ? $invoice->project->code . ' - ' . $invoice->project->name : '-')
        );

        $this->fillPartyBlock($dom, $seller, 7, $pageIndex);
        $this->fillPartyBlock($dom, $buyer, 14, $pageIndex);
    }

    private function fillLineRow(DOMDocument $dom, int $row, $line, int $index): void
    {
        $item = $line->item;
        $base = (float) $line->quantity * (float) $line->unit_price;
        $afterDiscount = max($base - (float) $line->discount_amount, 0);
        $description = trim(($item?->name ?? 'کالا/خدمت حذف‌شده') . ($line->description ? ' - ' . $line->description : ''));

        $this->setNumber($dom, 'B' . $row, $index + 1);
        $this->setText($dom, 'D' . $row, $item?->code ?: '_');
        $this->setText($dom, 'G' . $row, $description);
        $this->setNumber($dom, 'T' . $row, (float) $line->quantity);
        $this->setText($dom, 'W' . $row, $item?->unit?->name ?: '-');
        $this->setNumber($dom, 'Z' . $row, (float) $line->unit_price);
        $this->setNumber($dom, 'AE' . $row, $base);
        $this->setNumber($dom, 'AL' . $row, (float) $line->discount_amount);
        $this->setNumber($dom, 'AQ' . $row, $afterDiscount);
        $this->setNumber($dom, 'AX' . $row, (float) $line->tax_amount);
        $this->setNumber($dom, 'BC' . $row, (float) $line->line_total);
    }

    private function clearLineRow(DOMDocument $dom, int $row): void
    {
        foreach (self::LINE_COLUMNS as $column) {
            $this->clearCellCoordinate($dom, $column . $row);
        }
    }

    private function fillContinuationFooter(DOMDocument $dom, int $pageIndex): void
    {
        $this->setText($dom, $this->pageCoordinate($pageIndex, 'G29'), 'ادامه در صفحه بعد');

        foreach (['AE29', 'AL29', 'AQ29', 'AX29', 'BC29'] as $coordinate) {
            $this->clearCellCoordinate($dom, $this->pageCoordinate($pageIndex, $coordinate));
        }

        $this->clearCellCoordinate($dom, $this->pageCoordinate($pageIndex, 'AE30'));
    }

    private function fillTotalsFooter(
        DOMDocument $dom,
        int $pageIndex,
        float $subtotal,
        float $discountTotal,
        float $afterDiscountTotal,
        float $taxTotal,
        float $finalTotal,
        Invoice $invoice
    ): void {
        $this->setText($dom, $this->pageCoordinate($pageIndex, 'G29'), persianNumberToWords($finalTotal) . ' ریال');
        $this->setNumber($dom, $this->pageCoordinate($pageIndex, 'AE29'), $subtotal);
        $this->setNumber($dom, $this->pageCoordinate($pageIndex, 'AL29'), $discountTotal);
        $this->setNumber($dom, $this->pageCoordinate($pageIndex, 'AQ29'), $afterDiscountTotal);
        $this->setNumber($dom, $this->pageCoordinate($pageIndex, 'AX29'), $taxTotal);
        $this->setNumber($dom, $this->pageCoordinate($pageIndex, 'BC29'), $finalTotal);
        $this->setText(
            $dom,
            $this->pageCoordinate($pageIndex, 'AE30'),
            'توضیحات: ' . $this->invoiceNotes($invoice)
        );
    }

    private function pageRow(int $pageIndex, int $rowOnPage): int
    {
        return ($pageIndex * self::PAGE_HEIGHT) + $rowOnPage;
    }

    private function pageCoordinate(int $pageIndex, string $coordinate): string
    {
        if (! preg_match('/^([A-Z]+)(\d+)$/', $coordinate, $matches)) {
            return $coordinate;
        }

        return $matches[1] . $this->pageRow($pageIndex, (int) $matches[2]);
    }

    private function ensurePageCount(DOMDocument $dom, int $pageCount): void
    {
        for ($pageIndex = 1; $pageIndex < $pageCount; $pageIndex++) {
            if ($this->findRow($dom, $this->pageRow($pageIndex, 1)) !== null) {
                continue;
            }

            $this->duplicatePage($dom, $pageIndex);
        }
    }

    private function duplicatePage(DOMDocument $dom, int $targetPageIndex): void
    {
        $offset = $targetPageIndex * self::PAGE_HEIGHT;
        $sheetData = $this->sheetData($dom);

        for ($rowNumber = 1; $rowNumber <= self::PAGE_HEIGHT; $rowNumber++) {
            $sourceRow = $this->findRow($dom, $rowNumber);

            if ($sourceRow === null) {
                continue;
            }

            $newRow = $sourceRow->cloneNode(true);
            $newRowNumber = $rowNumber + $offset;
            $newRow->setAttribute('r', (string) $newRowNumber);

            foreach ($newRow->getElementsByTagName('c') as $cell) {
                if (! $cell instanceof DOMElement || ! $cell->hasAttribute('r')) {
                    continue;
                }

                $cell->setAttribute('r', $this->offsetCellRef($cell->getAttribute('r'), $offset));

                $formulas = [];

                foreach ($cell->getElementsByTagName('f') as $formula) {
                    $formulas[] = $formula;
                }

                foreach ($formulas as $formula) {
                    $formula->parentNode?->removeChild($formula);
                }
            }

            $sheetData->appendChild($newRow);
        }

        $this->duplicateMergeCells($dom, $offset);
        $this->ensurePageBreak($dom, $this->pageRow($targetPageIndex, 1));
    }

    private function duplicateMergeCells(DOMDocument $dom, int $offset): void
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $mergeCellsNode = $xpath->query('//x:mergeCells')->item(0);

        if (! $mergeCellsNode instanceof DOMElement) {
            return;
        }

        $templateMerges = [];

        foreach ($xpath->query('x:mergeCell', $mergeCellsNode) as $mergeCell) {
            if (! $mergeCell instanceof DOMElement) {
                continue;
            }

            $ref = $mergeCell->getAttribute('ref');

            if ($this->rangeWithinRows($ref, 1, self::PAGE_HEIGHT)) {
                $templateMerges[] = $ref;
            }
        }

        foreach ($templateMerges as $ref) {
            $newMerge = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'mergeCell');
            $newMerge->setAttribute('ref', $this->offsetRangeRef($ref, $offset));
            $mergeCellsNode->appendChild($newMerge);
        }

        $mergeCellsNode->setAttribute('count', (string) $xpath->query('x:mergeCell', $mergeCellsNode)->length);
    }

    private function ensurePageBreak(DOMDocument $dom, int $row): void
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $worksheet = $xpath->query('//x:worksheet')->item(0);

        if (! $worksheet instanceof DOMElement) {
            return;
        }

        $rowBreaks = $xpath->query('//x:rowBreaks')->item(0);

        if (! $rowBreaks instanceof DOMElement) {
            $rowBreaks = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'rowBreaks');
            $insertBefore = $xpath->query('//x:drawing')->item(0)
                ?? $xpath->query('//x:legacyDrawing')->item(0);

            if ($insertBefore instanceof DOMElement) {
                $worksheet->insertBefore($rowBreaks, $insertBefore);
            } else {
                $worksheet->appendChild($rowBreaks);
            }
        }

        foreach ($xpath->query('x:brk', $rowBreaks) as $existingBreak) {
            if ($existingBreak instanceof DOMElement && (int) $existingBreak->getAttribute('id') === $row) {
                return;
            }
        }

        $break = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'brk');
        $break->setAttribute('id', (string) $row);
        $break->setAttribute('max', '16383');
        $break->setAttribute('man', '1');
        $rowBreaks->appendChild($break);

        $breakCount = $xpath->query('x:brk', $rowBreaks)->length;
        $rowBreaks->setAttribute('count', (string) $breakCount);
        $rowBreaks->setAttribute('manualBreakCount', (string) $breakCount);
    }

    private function updateSheetDimension(DOMDocument $dom, int $maxRow): void
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $dimension = $xpath->query('//x:dimension')->item(0);

        if ($dimension instanceof DOMElement) {
            $dimension->setAttribute('ref', 'B1:BR' . $maxRow);
        }
    }

    private function configurePrintSetup(DOMDocument $dom, int $pageCount): void
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $pageSetUpPr = $xpath->query('//x:pageSetUpPr')->item(0);

        if ($pageSetUpPr instanceof DOMElement) {
            // fitToPage forces Excel to ignore manual row breaks and squeeze the print area.
            $pageSetUpPr->setAttribute('fitToPage', '0');
        }

        $pageSetup = $xpath->query('//x:pageSetup')->item(0);

        if ($pageSetup instanceof DOMElement) {
            $pageSetup->removeAttribute('fitToWidth');
            $pageSetup->removeAttribute('fitToHeight');

            if (! $pageSetup->hasAttribute('scale')) {
                $pageSetup->setAttribute('scale', '83');
            }
        }
    }

    private function sheetData(DOMDocument $dom): DOMElement
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheetData = $xpath->query('//x:sheetData')->item(0);

        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('ساختار sheetData در قالب اکسل معتبر نیست.');
        }

        return $sheetData;
    }

    private function findRow(DOMDocument $dom, int $rowNumber): ?DOMElement
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $row = $xpath->query("//x:sheetData/x:row[@r='{$rowNumber}']")->item(0);

        return $row instanceof DOMElement ? $row : null;
    }

    private function offsetCellRef(string $ref, int $offset): string
    {
        if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches)) {
            return $ref;
        }

        return $matches[1] . ((int) $matches[2] + $offset);
    }

    private function offsetRangeRef(string $ref, int $offset): string
    {
        if (! str_contains($ref, ':')) {
            return $this->offsetCellRef($ref, $offset);
        }

        [$start, $end] = explode(':', $ref, 2);

        return $this->offsetCellRef($start, $offset) . ':' . $this->offsetCellRef($end, $offset);
    }

    private function rangeWithinRows(string $ref, int $minRow, int $maxRow): bool
    {
        if (! preg_match('/^([A-Z]+)(\d+)(?::([A-Z]+)(\d+))?$/', $ref, $matches)) {
            return false;
        }

        $startRow = (int) $matches[2];
        $endRow = isset($matches[4]) ? (int) $matches[4] : $startRow;

        return $startRow >= $minRow && $endRow <= $maxRow;
    }

    private function documentTitle(Invoice $invoice): string
    {
        $isSale = $invoice->direction === 'sale';
        $isProforma = $invoice->document_type === 'proforma';

        return match (true) {
            $isProforma && $isSale => 'پیش فاکتور فروش کالا و خدمات',
            $isProforma && ! $isSale => 'پیش فاکتور خرید کالا و خدمات',
            $isSale => 'فاکتور فروش کالا و خدمات',
            default => 'فاکتور خرید کالا و خدمات',
        };
    }

    private function invoiceNotes(Invoice $invoice): string
    {
        if ($invoice->document_type === 'proforma') {
            $default = 'اعتبار این پیش فاکتور 48 ساعت کاری می باشد و هزینه ارسال کالا با مشتری می باشد.';
            $custom = trim((string) ($invoice->description ?? ''));

            return $custom !== '' ? $default . ' ' . $custom : $default;
        }

        return trim((string) ($invoice->description ?? '')) ?: 'نرخ پایه خدمات / کالا طبق توافق طرفین';
    }

    /**
     * @return array{name:string,economic_code:string,registration_number:string,postal_code:string,national_id:string,address:string,phone:string,province:string,city:string}
     */
    private function partyProfile(?Party $party): array
    {
        if (! $party) {
            return $this->emptyProfile('طرف حساب حذف‌شده');
        }

        return [
            'name' => $party->name,
            'economic_code' => (string) ($party->economic_code ?? ''),
            'registration_number' => (string) ($party->registration_number ?? ''),
            'postal_code' => (string) ($party->postal_code ?? ''),
            'national_id' => (string) ($party->national_id ?? ''),
            'address' => (string) ($party->address ?? ''),
            'phone' => (string) ($party->phone ?: ($party->mobile ?? '')),
            'province' => 'تهران',
            'city' => 'تهران',
        ];
    }

    /**
     * @return array{name:string,economic_code:string,registration_number:string,postal_code:string,national_id:string,address:string,phone:string,province:string,city:string}
     */
    private function companyProfile(?CompanySetting $company): array
    {
        return [
            'name' => $company?->company_name ?? config('app.name', 'ERP'),
            'economic_code' => (string) ($company?->economic_code ?? ''),
            'registration_number' => (string) ($company?->registration_number ?? ''),
            'postal_code' => (string) ($company?->postal_code ?? ''),
            'national_id' => (string) ($company?->national_id ?? ''),
            'address' => (string) ($company?->address ?? ''),
            'phone' => (string) ($company?->phone ?? ''),
            'province' => 'تهران',
            'city' => 'تهران',
        ];
    }

    /**
     * @return array{name:string,economic_code:string,registration_number:string,postal_code:string,national_id:string,address:string,phone:string,province:string,city:string}
     */
    private function emptyProfile(string $name): array
    {
        return [
            'name' => $name,
            'economic_code' => '',
            'registration_number' => '',
            'postal_code' => '',
            'national_id' => '',
            'address' => '',
            'phone' => '',
            'province' => 'تهران',
            'city' => 'تهران',
        ];
    }

    /**
     * @param  array{name:string,economic_code:string,registration_number:string,postal_code:string,national_id:string,address:string,phone:string,province:string,city:string}  $profile
     */
    private function fillPartyBlock(DOMDocument $dom, array $profile, int $nameRow, int $pageIndex = 0): void
    {
        $nameRow = $this->pageRow($pageIndex, $nameRow);
        $provinceRow = $nameRow + 2;
        $addressRow = $nameRow + 4;

        $this->setText($dom, 'K' . $nameRow, $profile['name']);

        foreach (self::ECONOMIC_CODE_CLEAR_COLUMNS as $column) {
            $this->clearCellCoordinate($dom, $column . $nameRow);
        }

        $this->setSpreadDigits($dom, self::ECONOMIC_CODE_COLUMNS, $nameRow, $profile['economic_code']);
        $this->setSpreadDigits($dom, self::REGISTRATION_COLUMNS, $nameRow, $profile['registration_number']);

        $this->setText($dom, 'I' . $provinceRow, $profile['province']);
        $this->setText($dom, 'U' . $provinceRow, $profile['city']);
        $this->setSpreadDigits($dom, self::POSTAL_CODE_COLUMNS, $provinceRow, $profile['postal_code']);
        $this->setSpreadDigits($dom, self::NATIONAL_ID_COLUMNS, $provinceRow, $profile['national_id']);

        $this->setText($dom, 'E' . $addressRow, $profile['address'] ?: '-');
        $this->setText($dom, 'AY' . $addressRow, $profile['phone'] ?: '-');
    }

    /**
     * @param  list<string>  $columns
     */
    private function setSpreadDigits(DOMDocument $dom, array $columns, int $row, ?string $value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        $columnCount = count($columns);

        // The official invoice template is RTL: AH is visually on the right and AS on the left.
        // Numeric identifiers read left-to-right, so digits[0] belongs in AS and shorter values
        // stay right-aligned within the available boxes.
        $visualColumns = array_reverse($columns);
        $offset = max(0, $columnCount - strlen($digits));

        foreach ($visualColumns as $index => $column) {
            $digitIndex = $index - $offset;
            $digit = ($digitIndex >= 0 && $digitIndex < strlen($digits)) ? $digits[$digitIndex] : '';

            if ($digit === '') {
                $this->clearCellCoordinate($dom, $column . $row);
            } else {
                $this->setText($dom, $column . $row, $digit);
            }
        }
    }

    private function setText(DOMDocument $dom, string $coordinate, ?string $value): void
    {
        $cell = $this->cell($dom, $coordinate);
        $this->clearCell($cell);
        $cell->setAttribute('t', 'inlineStr');

        $is = $dom->createElement('is');
        $text = $dom->createElement('t');
        $text->appendChild($dom->createTextNode((string) $value));
        $is->appendChild($text);
        $cell->appendChild($is);
    }

    private function setNumber(DOMDocument $dom, string $coordinate, float|int $value): void
    {
        $cell = $this->cell($dom, $coordinate);
        $this->clearCell($cell);
        $cell->removeAttribute('t');
        $cell->appendChild($dom->createElement('v', (string) $value));
    }

    private function clearCellCoordinate(DOMDocument $dom, string $coordinate): void
    {
        try {
            $this->clearCell($this->cell($dom, $coordinate));
        } catch (RuntimeException) {
            // Some optional cells may not exist in the template.
        }
    }

    private function cell(DOMDocument $dom, string $coordinate): DOMElement
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $node = $xpath->query("//x:c[@r='{$coordinate}']")->item(0);

        if (! $node instanceof DOMElement) {
            throw new RuntimeException("سلول {$coordinate} در قالب اکسل پیدا نشد.");
        }

        return $node;
    }

    private function clearCell(DOMElement $cell): void
    {
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }

        $cell->removeAttribute('t');
    }

    private function removeCalcChain(ZipArchive $zip): void
    {
        $zip->deleteName('xl/calcChain.xml');

        $contentTypes = $zip->getFromName('[Content_Types].xml');

        if (is_string($contentTypes)) {
            $contentTypes = (string) preg_replace(
                '/<Override PartName="\/xl\/calcChain\.xml"[^>]*\/>/',
                '',
                $contentTypes
            );
            $zip->deleteName('[Content_Types].xml');
            $zip->addFromString('[Content_Types].xml', $contentTypes);
        }

        $workbookRels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (is_string($workbookRels)) {
            $workbookRels = (string) preg_replace(
                '/<Relationship[^>]*calcChain[^>]*\/>/',
                '',
                $workbookRels
            );
            $zip->deleteName('xl/_rels/workbook.xml.rels');
            $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        }
    }

    private function updatePrintArea(ZipArchive $zip, int $pageCount): void
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');

        if (! is_string($workbookXml)) {
            return;
        }

        $maxRow = $pageCount * self::PAGE_HEIGHT;
        $updated = preg_replace(
            '/(\$A\$1:\$BK\$)\d+/',
            '${1}' . $maxRow,
            $workbookXml
        );

        if (! is_string($updated) || $updated === $workbookXml) {
            return;
        }

        $zip->deleteName('xl/workbook.xml');
        $zip->addFromString('xl/workbook.xml', $updated);
    }

    private function duplicateDrawings(ZipArchive $zip, int $pageCount): void
    {
        $drawingXml = $zip->getFromName('xl/drawings/drawing1.xml');

        if (! is_string($drawingXml)) {
            return;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($drawingXml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
        $root = $xpath->query('//xdr:wsDr')->item(0);

        if (! $root instanceof DOMElement) {
            return;
        }

        $templateAnchors = [];

        foreach ($xpath->query('//xdr:twoCellAnchor') as $anchor) {
            if ($anchor instanceof DOMElement) {
                $templateAnchors[] = $anchor;
            }
        }

        if ($templateAnchors === []) {
            return;
        }

        $maxShapeId = 0;

        foreach ($xpath->query('//xdr:cNvPr') as $shapeNode) {
            if ($shapeNode instanceof DOMElement) {
                $maxShapeId = max($maxShapeId, (int) $shapeNode->getAttribute('id'));
            }
        }

        for ($pageIndex = 1; $pageIndex < $pageCount; $pageIndex++) {
            $rowOffset = $pageIndex * self::PAGE_HEIGHT;

            foreach ($templateAnchors as $anchor) {
                $clone = $anchor->cloneNode(true);

                if (! $clone instanceof DOMElement) {
                    continue;
                }

                $this->offsetDrawingAnchorRows($clone, $rowOffset);
                $this->assignDrawingShapeId($clone, ++$maxShapeId);
                $root->appendChild($clone);
            }
        }

        $zip->deleteName('xl/drawings/drawing1.xml');
        $zip->addFromString('xl/drawings/drawing1.xml', $dom->saveXML());
    }

    private function offsetDrawingAnchorRows(DOMElement $anchor, int $rowOffset): void
    {
        foreach ($anchor->getElementsByTagNameNS(
            'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing',
            'row'
        ) as $rowNode) {
            $rowNode->textContent = (string) ((int) $rowNode->textContent + $rowOffset);
        }
    }

    private function assignDrawingShapeId(DOMElement $anchor, int $shapeId): void
    {
        $shapeNode = $anchor->getElementsByTagNameNS(
            'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing',
            'cNvPr'
        )->item(0);

        if (! $shapeNode instanceof DOMElement) {
            return;
        }

        $shapeNode->setAttribute('id', (string) $shapeId);

        $name = $shapeNode->getAttribute('name');

        if ($name !== '') {
            $shapeNode->setAttribute('name', $name . ' ' . $shapeId);
        }
    }
}
