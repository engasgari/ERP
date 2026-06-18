<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

class InvoiceExcelTemplateService
{
    public function build(Invoice $invoice, ?CompanySetting $company): string
    {
        $template = storage_path('app/invoice_sample.xlsx');

        if (!is_file($template)) {
            throw new RuntimeException('فایل قالب اکسل فاکتور پیدا نشد: storage/app/invoice_sample.xlsx');
        }

        $target = storage_path('app/temp/invoice-' . $invoice->id . '-' . uniqid() . '.xlsx');

        if (!is_dir(dirname($target))) {
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

        $invoice->loadMissing('party', 'lines.item.unit');

        $this->setText($dom, 'E3', gregorianToJalaliDate($invoice->invoice_date));
        $this->setText($dom, 'BE3', $invoice->number);

        $this->setText($dom, 'K7', $company?->company_name ?? config('app.name', 'ERP'));
        $this->setText($dom, 'AH7', $company?->economic_code ?: '-');
        $this->setText($dom, 'AY7', $company?->registration_number ?: '-');
        $this->setText($dom, 'I9', 'تهران');
        $this->setText($dom, 'U9', 'تهران');
        $this->setText($dom, 'AH9', $company?->postal_code ?: '-');
        $this->setText($dom, 'AY9', $company?->national_id ?: '-');
        $this->setText($dom, 'E11', $company?->address ?: '-');
        $this->setText($dom, 'AY11', $company?->phone ?: '-');

        $this->setText($dom, 'K14', $invoice->party->name);
        $this->setText($dom, 'AH14', $invoice->party->economic_code ?: '-');
        $this->setText($dom, 'AY14', '-');
        $this->setText($dom, 'I16', 'تهران');
        $this->setText($dom, 'AH16', '-');
        $this->setText($dom, 'AY16', $invoice->party->national_id ?: '-');
        $this->setText($dom, 'E18', $invoice->party->address ?: '-');
        $this->setText($dom, 'AY18', $invoice->party->phone ?: ($invoice->party->mobile ?: '-'));

        $row = 21;
        $subtotal = 0;
        $discountTotal = 0;
        $afterDiscountTotal = 0;
        $taxTotal = 0;
        $finalTotal = 0;

        foreach ($invoice->lines->take(8) as $index => $line) {
            $base = (float) $line->quantity * (float) $line->unit_price;
            $afterDiscount = max($base - (float) $line->discount_amount, 0);
            $tax = (float) $line->tax_amount;
            $lineTotal = (float) $line->line_total;

            $this->setNumber($dom, 'B' . $row, $index + 1);
            $this->setText($dom, 'D' . $row, $line->item->code ?: '_');
            $this->setText($dom, 'G' . $row, trim($line->item->name . ($line->description ? ' - ' . $line->description : '')));
            $this->setNumber($dom, 'T' . $row, (float) $line->quantity);
            $this->setText($dom, 'W' . $row, $line->item->unit?->name ?: '-');
            $this->setNumber($dom, 'Z' . $row, (float) $line->unit_price);
            $this->setNumber($dom, 'AE' . $row, $base);
            $this->setNumber($dom, 'AL' . $row, (float) $line->discount_amount);
            $this->setNumber($dom, 'AQ' . $row, $afterDiscount);
            $this->setNumber($dom, 'AX' . $row, $tax);
            $this->setNumber($dom, 'BC' . $row, $lineTotal);

            $subtotal += $base;
            $discountTotal += (float) $line->discount_amount;
            $afterDiscountTotal += $afterDiscount;
            $taxTotal += $tax;
            $finalTotal += $lineTotal;
            $row++;
        }

        for (; $row <= 28; $row++) {
            foreach (['B', 'D', 'G', 'T', 'W', 'Z', 'AE', 'AL', 'AQ', 'AX', 'BC'] as $column) {
                $this->setText($dom, $column . $row, '');
            }
        }

        $this->setText($dom, 'G29', persianNumberToWords($finalTotal) . ' ریال');
        $this->setNumber($dom, 'AE29', $subtotal);
        $this->setNumber($dom, 'AL29', $discountTotal);
        $this->setNumber($dom, 'AQ29', $afterDiscountTotal);
        $this->setNumber($dom, 'AX29', $taxTotal);
        $this->setNumber($dom, 'BC29', $finalTotal);
        $this->setText($dom, 'AE30', 'توضیحات: ' . ($invoice->description ?: 'نرخ پایه خدمات / کالا طبق توافق طرفین'));

        $zip->addFromString('xl/worksheets/sheet1.xml', $dom->saveXML());
        $zip->close();

        return $target;
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

    private function cell(DOMDocument $dom, string $coordinate): DOMElement
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $node = $xpath->query("//x:c[@r='{$coordinate}']")->item(0);

        if (!$node instanceof DOMElement) {
            throw new RuntimeException("سلول {$coordinate} در قالب اکسل پیدا نشد.");
        }

        return $node;
    }

    private function clearCell(DOMElement $cell): void
    {
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
    }
}
