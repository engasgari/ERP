<?php

namespace App\Http\Controllers;

use App\Models\BomLine;
use App\Models\BomVersion;
use App\Models\InventoryDocumentLine;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\MeasurementUnit;
use App\Models\ProductionMaterialConsumption;
use App\Models\Warehouse;
use App\Services\InventoryPostingService;
use App\Services\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use RuntimeException;
use Throwable;
use ZipArchive;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        return view('items.index');
    }

    public function create()
    {
        return view('items.create', [
            'item' => new Item(['type' => 'product', 'is_active' => true]),
            'units' => MeasurementUnit::where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, NumberingService $numbering, InventoryPostingService $inventory)
    {
        $validated = $this->validated($request, includeInitialStock: true);

        $item = Item::create(collect($validated)->except(['initial_quantity', 'initial_warehouse_id'])->all() + [
            'code' => $numbering->next('item', 'I-'),
            'is_active' => true,
        ]);

        try {
            $inventory->postInitialStock(
                $item,
                (float) ($validated['initial_quantity'] ?? 0),
                $validated['purchase_price'] ?? null,
                $validated['initial_warehouse_id'] ?? null,
                auth()->id()
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('items.index')
                ->with('error', $exception->getMessage() . ' کالا ثبت شد، اما موجودی اولیه ثبت نشد.');
        }

        return redirect()->route('items.index')->with('success', 'کالا/خدمت ثبت شد.');
    }

    public function edit(Item $item)
    {
        return view('items.create', [
            'item' => $item,
            'units' => MeasurementUnit::where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $item->update($this->validated($request));

        return redirect()->route('items.index')->with('success', 'کالا/خدمت ویرایش شد.');
    }

    public function importForm()
    {
        return view('items.import', [
            'units' => MeasurementUnit::where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function import(Request $request, NumberingService $numbering, InventoryPostingService $inventory)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ], [
            'file.required' => 'لطفا فایل اکسل را انتخاب کنید.',
            'file.file' => 'فایل انتخاب‌شده معتبر نیست.',
            'file.max' => 'حجم فایل نباید بیشتر از ۱۰ مگابایت باشد.',
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            return back()->with('error', 'فرمت فایل باید csv، txt، xlsx یا xls باشد.');
        }

        try {
            $rows = $this->readImportRows($request->file('file')->getRealPath(), $extension);
        } catch (Throwable $exception) {
            return back()->with('error', 'فایل قابل خواندن نیست: ' . $exception->getMessage());
        }

        if (empty($rows)) {
            return back()->with('error', 'فایل انتخاب‌شده ردیف قابل ثبت ندارد.');
        }

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $data = $this->normalizeImportRow($row, $rowNumber, $numbering);

                DB::transaction(function () use ($data, $inventory) {
                    $item = Item::create(collect($data)->except(['initial_quantity', 'initial_warehouse_id'])->all());

                    $inventory->postInitialStock(
                        $item,
                        (float) ($data['initial_quantity'] ?? 0),
                        $data['purchase_price'] ?? null,
                        $data['initial_warehouse_id'] ?? null,
                        auth()->id()
                    );
                });

                $imported++;
            } catch (Throwable $exception) {
                $errors[] = "ردیف {$rowNumber}: " . $exception->getMessage();
            }
        }

        $totalRows = count($rows);
        $message = "{$totalRows} ردیف خوانده شد؛ {$imported} کالا/خدمت با موفقیت وارد شد.";
        if ($errors) {
            $message .= ' خطاها: ' . implode(' | ', array_slice($errors, 0, 6));
            if (count($errors) > 6) {
                $message .= ' و ' . (count($errors) - 6) . ' خطای دیگر';
            }
        }

        return redirect()->route('items.import.form')->with($imported > 0 ? 'success' : 'error', $message);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="items-import-template.xlsx"',
        ];

        return Response::make($this->buildImportTemplateXlsx(), 200, $headers);
    }

    public function destroy(Item $item)
    {
        $related = [
            'ردیف فاکتور' => InvoiceLine::where('item_id', $item->id)->count(),
            'ردیف سند انبار' => InventoryDocumentLine::where('item_id', $item->id)->count(),
            'فرمول BOM محصول' => BomVersion::where('item_id', $item->id)->count(),
            'ردیف BOM' => BomLine::where('component_item_id', $item->id)->count(),
            'مصرف تولید' => ProductionMaterialConsumption::where('item_id', $item->id)->count(),
        ];

        $details = collect($related)
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $title) => "{$title}: {$count}")
            ->values()
            ->all();

        $invoiceNumbers = DB::table('invoice_lines as l')
            ->join('invoices as i', 'i.id', '=', 'l.invoice_id')
            ->where('l.item_id', $item->id)
            ->limit(5)
            ->pluck('i.number')
            ->filter()
            ->implode('، ');
        $inventoryNumbers = DB::table('inventory_document_lines as l')
            ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
            ->where('l.item_id', $item->id)
            ->limit(5)
            ->pluck('d.number')
            ->filter()
            ->implode('، ');

        if ($invoiceNumbers) {
            $details[] = 'نمونه فاکتورها: ' . $invoiceNumbers;
        }
        if ($inventoryNumbers) {
            $details[] = 'نمونه اسناد انبار: ' . $inventoryNumbers;
        }

        if (! empty($details)) {
            return redirect()->route('items.index')
                ->with('error', 'به دلیل وجود سند یا گردش مرتبط، امکان حذف این کالا/خدمت وجود ندارد.')
                ->with('error_details', $details);
        }

        try {
            $item->delete();
        } catch (Throwable) {
            return redirect()->route('items.index')
                ->with('error', 'به دلیل وجود سند یا گردش مرتبط، امکان حذف این کالا/خدمت وجود ندارد.')
                ->with('error_details', ['یک یا چند رکورد وابسته در دیتابیس وجود دارد.']);
        }

        return redirect()->route('items.index')->with('success', 'کالا/خدمت حذف شد.');
    }

    private function validated(Request $request, bool $includeInitialStock = false): array
    {
        $rules = [
            'type' => 'required|in:product,service',
            'name' => 'required|string|max:255',
            'measurement_unit_id' => 'nullable|exists:measurement_units,id',
            'category' => 'nullable|string|max:255',
            'sale_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];

        if ($includeInitialStock) {
            $rules['initial_quantity'] = 'nullable|numeric|min:0';
            $rules['initial_warehouse_id'] = 'nullable|exists:warehouses,id';
        }

        $validated = $request->validate($rules);
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function normalizeImportRow(array $row, int $rowNumber, NumberingService $numbering): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('نام کالا/خدمت الزامی است.');
        }

        $type = $this->normalizeType($row['type'] ?? 'product');
        $code = trim((string) ($row['code'] ?? ''));
        $code = $code !== '' ? $code : $numbering->next('item', 'I-');

        if (Item::where('code', $code)->exists()) {
            throw new RuntimeException("کد {$code} قبلا ثبت شده است.");
        }

        $unitId = $this->findUnitId($row['measurement_unit'] ?? null);
        $warehouseId = $this->findWarehouseId($row['initial_warehouse'] ?? null);
        $initialQuantity = $this->toDecimal($row['initial_quantity'] ?? null);

        if ($type === 'service') {
            $unitId = null;
            $initialQuantity = null;
            $warehouseId = null;
        }

        if ($type === 'product' && $initialQuantity > 0 && ! $warehouseId && ! Warehouse::where('is_active', true)->exists()) {
            throw new RuntimeException('برای ثبت موجودی اولیه، ابتدا یک انبار فعال تعریف کنید.');
        }

        return [
            'code' => $code,
            'type' => $type,
            'name' => $name,
            'measurement_unit_id' => $unitId,
            'category' => trim((string) ($row['category'] ?? '')) ?: null,
            'sale_price' => $this->toDecimal($row['sale_price'] ?? null),
            'purchase_price' => $this->toDecimal($row['purchase_price'] ?? null),
            'initial_quantity' => $initialQuantity,
            'initial_warehouse_id' => $warehouseId,
            'is_active' => $this->toBool($row['is_active'] ?? 'بله'),
            'description' => trim((string) ($row['description'] ?? '')) ?: null,
        ];
    }

    private function readImportRows(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $content = file_get_contents($path) ?: '';

            if ($extension === 'xlsx' || str_starts_with($content, "PK\x03\x04")) {
                return $this->readXlsxRows($path);
            }

            if (str_contains($content, 'Excel Workbook Frameset') || str_contains($content, 'WorksheetSource')) {
                throw new RuntimeException('این فایل بعد از ذخیره در Excel به چند فایل وابسته تبدیل شده است و فقط با آپلود همین فایل اصلی قابل خواندن نیست. لطفا قالب خام جدید را دوباره دانلود کنید یا فایل را با فرمت xlsx ذخیره کنید.');
            }

            if (str_contains($content, 'urn:schemas-microsoft-com:office:spreadsheet')) {
                return $this->readSpreadsheetXmlRows($content);
            }

            if (str_contains(mb_strtolower($content), '<table')) {
                return $this->readHtmlExcelRows($content);
            }

            throw new RuntimeException('این فایل اکسل قابل خواندن نیست. لطفا از قالب خام همین صفحه استفاده کنید یا فایل را با فرمت CSV ذخیره کنید.');
        }

        return $this->readCsvRows($path);
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('امکان باز کردن فایل xlsx وجود ندارد.');
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('ساختار فایل xlsx معتبر نیست.');
        }

        $zip->close();

        $xml = @simplexml_load_string($sheetXml);
        if (! $xml) {
            return [];
        }

        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $xml->xpath('//x:sheetData/x:row') ?: [];
        $rawRows = [];

        foreach ($rowNodes as $rowNode) {
            $rowNode->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = [];
            foreach ($rowNode->xpath('x:c') ?: [] as $cellNode) {
                $attributes = $cellNode->attributes();
                $reference = (string) ($attributes['r'] ?? '');
                $index = $reference !== '' ? $this->xlsxColumnIndex($reference) : count($cells);

                while (count($cells) < $index) {
                    $cells[] = '';
                }

                $cells[] = $this->xlsxCellValue($cellNode, $sharedStrings);
            }

            if (!empty(array_filter($cells, fn ($value) => trim((string) $value) !== ''))) {
                $rawRows[] = $cells;
            }
        }

        return $this->rowsFromRawRows($rawRows);
    }

    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = @simplexml_load_string($content);
        if (! $xml) {
            return [];
        }

        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($xml->xpath('//x:si') ?: [] as $node) {
            $parts = [];
            foreach ($node->xpath('.//x:t') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function xlsxCellValue(\SimpleXMLElement $cellNode, array $sharedStrings): string
    {
        $cellNode->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $attributes = $cellNode->attributes();
        $type = (string) ($attributes['t'] ?? '');

        if ($type === 'inlineStr') {
            $texts = $cellNode->xpath('.//x:t') ?: [];
            return trim(implode('', array_map(fn ($node) => (string) $node, $texts)));
        }

        $valueNode = $cellNode->xpath('x:v');
        $value = isset($valueNode[0]) ? trim((string) $valueNode[0]) : '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function xlsxColumnIndex(string $cellReference): int
    {
        preg_match('/^([A-Z]+)/i', $cellReference, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    private function readSpreadsheetXmlRows(string $content): array
    {
        $xml = @simplexml_load_string($content);
        if (! $xml) {
            return [];
        }

        $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        $rowNodes = $xml->xpath('//ss:Worksheet/ss:Table/ss:Row') ?: [];
        $rawRows = [];

        foreach ($rowNodes as $rowNode) {
            $cells = [];
            foreach ($rowNode->xpath('ss:Cell') ?: [] as $cellNode) {
                $attributes = $cellNode->attributes('urn:schemas-microsoft-com:office:spreadsheet');
                $index = isset($attributes['Index']) ? ((int) $attributes['Index']) - 1 : count($cells);
                while (count($cells) < $index) {
                    $cells[] = '';
                }
                $data = $cellNode->xpath('ss:Data');
                $cells[] = isset($data[0]) ? trim((string) $data[0]) : '';
            }

            if (!empty(array_filter($cells, fn ($value) => trim((string) $value) !== ''))) {
                $rawRows[] = $cells;
            }
        }

        return $this->rowsFromRawRows($rawRows);
    }

    private function readHtmlExcelRows(string $content): array
    {
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/isu', $content, $rowMatches);
        $rawRows = [];

        foreach ($rowMatches[1] as $rowHtml) {
            preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/isu', $rowHtml, $cellMatches);
            $cells = array_map(function ($cell) {
                $cell = preg_replace('/<br\s*\/?>/iu', "\n", $cell);
                $cell = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return trim($cell);
            }, $cellMatches[1] ?? []);

            if (!empty(array_filter($cells, fn ($value) => trim((string) $value) !== ''))) {
                $rawRows[] = $cells;
            }
        }

        return $this->rowsFromRawRows($rawRows);
    }

    private function readCsvRows(string $path): array
    {
        $rows = [];
        $separator = $this->detectSeparator($path);

        if (($handle = fopen($path, 'r')) !== false) {
            $headerRow = fgetcsv($handle, 0, $separator) ?: [];
            $firstHeader = isset($headerRow[0]) ? preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $headerRow[0])) : '';
            if (str_starts_with(strtolower($firstHeader), 'sep=')) {
                $headerRow = fgetcsv($handle, 0, $separator) ?: [];
            }

            $headers = $this->cleanHeaders($headerRow);

            while (($data = fgetcsv($handle, 0, $separator)) !== false) {
                if (!empty(array_filter($data, fn ($value) => trim((string) $value) !== ''))) {
                    $rows[] = $this->combineRow($headers, $data);
                }
            }

            fclose($handle);
        }

        return $rows;
    }

    private function rowsFromRawRows(array $rawRows): array
    {
        $headerIndex = collect($rawRows)->search(fn ($row) => collect($this->cleanHeaders($row))->contains('name'));
        if ($headerIndex === false) {
            return [];
        }

        $rawRows = array_slice($rawRows, $headerIndex);
        $headers = $this->cleanHeaders(array_shift($rawRows));

        return collect($rawRows)
            ->map(fn ($row) => $this->combineRow($headers, $row))
            ->filter(fn ($row) => !empty(array_filter($row, fn ($value) => trim((string) $value) !== '')))
            ->values()
            ->all();
    }

    private function cleanHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
            $header = trim(str_replace([' ', '-', '‌'], '_', $header));
            $header = str_replace(['_(ریال)', '(ریال)', '_ریال', 'ریال'], '', $header);

            return match (mb_strtolower($header)) {
                'نوع', 'type' => 'type',
                'کد', 'code', 'item_code' => 'code',
                'نام', 'نام_کالا', 'نام_خدمت', 'name', 'item_name' => 'name',
                'واحد', 'واحد_سنجش', 'measurement_unit', 'unit' => 'measurement_unit',
                'دسته', 'دسته‌بندی', 'دسته_بندی', 'category' => 'category',
                'قیمت_فروش', 'sale_price' => 'sale_price',
                'قیمت_خرید', 'purchase_price' => 'purchase_price',
                'موجودی_اولیه', 'initial_quantity', 'opening_quantity' => 'initial_quantity',
                'انبار_اولیه', 'initial_warehouse', 'warehouse' => 'initial_warehouse',
                'فعال', 'وضعیت', 'is_active', 'active' => 'is_active',
                'توضیحات', 'شرح', 'description' => 'description',
                default => mb_strtolower($header),
            };
        }, $headers);
    }

    private function combineRow(array $headers, array $row): array
    {
        $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));

        return array_combine($headers, $row) ?: [];
    }

    private function detectSeparator(string $path): string
    {
        $handle = fopen($path, 'r');
        $firstLine = $handle ? (fgets($handle) ?: '') : '';
        if ($handle) {
            fclose($handle);
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', trim($firstLine));
        if (str_starts_with(strtolower($firstLine), 'sep=')) {
            $separator = substr($firstLine, 4, 1);
            return $separator !== '' ? $separator : ',';
        }

        $detected = ',';
        $maxCount = 0;

        foreach ([',', ';', "\t", '|'] as $separator) {
            $count = substr_count($firstLine, $separator);
            if ($count > $maxCount) {
                $maxCount = $count;
                $detected = $separator;
            }
        }

        return $detected;
    }

    private function buildImportTemplateXlsx(): string
    {
        $rows = [
            ['نوع', 'کد', 'نام', 'واحد', 'دسته‌بندی', 'قیمت فروش (ریال)', 'قیمت خرید (ریال)', 'موجودی اولیه', 'انبار اولیه', 'فعال', 'توضیحات'],
            ['کالا', '', 'ورق فولادی', 'کیلوگرم', 'مواد اولیه', 0, 120000, 100, 'انبار اصلی', 'بله', 'نمونه کالا'],
            ['خدمت', '', 'خدمات نصب', '', 'خدمات', 2500000, 0, '', '', 'بله', 'نمونه خدمت'],
        ];

        $temp = tempnam(sys_get_temp_dir(), 'items-import-');
        $zip = new ZipArchive();

        if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('امکان ساخت قالب اکسل وجود ندارد.');
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelationships());
        $zip->addFromString('docProps/app.xml', $this->xlsxAppProperties());
        $zip->addFromString('docProps/core.xml', $this->xlsxCoreProperties());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheet($rows));
        $zip->close();

        $content = file_get_contents($temp) ?: '';
        @unlink($temp);

        return $content;
    }

    private function xlsxSheet(array $rows): string
    {
        $sheetRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                $cell = $this->xlsxColumnName($columnIndex + 1) . ($rowIndex + 1);
                $style = $rowIndex === 0 ? ' s="1"' : '';

                if (is_numeric($value) && $value !== '') {
                    $cells[] = '<c r="' . $cell . '"' . $style . '><v>' . $value . '</v></c>';
                } else {
                    $cells[] = '<c r="' . $cell . '" t="inlineStr"' . $style . '><is><t>' . $this->xmlEscape((string) $value) . '</t></is></c>';
                }
            }
            $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews>'
            . '<cols><col min="1" max="2" width="16" customWidth="1"/><col min="3" max="3" width="28" customWidth="1"/><col min="4" max="11" width="18" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '</worksheet>';
    }

    private function xlsxColumnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xlsxRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<workbookPr/>'
            . '<sheets><sheet name="کالا و خدمات" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>';
    }

    private function xlsxAppProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>ERP</Application></Properties>';
    }

    private function xlsxCoreProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>ERP</dc:creator><cp:lastModifiedBy>ERP</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . now()->toAtomString() . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . now()->toAtomString() . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function normalizeType(mixed $value): string
    {
        $value = trim(mb_strtolower((string) $value));

        return match ($value) {
            'service', 'خدمت', 'خدمات' => 'service',
            'product', 'item', 'goods', 'کالا', '' => 'product',
            default => throw new RuntimeException('نوع باید کالا یا خدمت باشد.'),
        };
    }

    private function findUnitId(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return MeasurementUnit::query()
            ->where('id', $value)
            ->orWhere('name', $value)
            ->orWhere('code', $value)
            ->value('id') ?: throw new RuntimeException("واحد سنجش '{$value}' پیدا نشد.");
    }

    private function findWarehouseId(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Warehouse::query()
            ->where('id', $value)
            ->orWhere('name', $value)
            ->value('id') ?: throw new RuntimeException("انبار '{$value}' پیدا نشد.");
    }

    private function toDecimal(mixed $value): ?float
    {
        $value = trim(str_replace([',', '٬'], ['', ''], (string) $value));

        return $value === '' ? null : (float) $value;
    }

    private function toBool(mixed $value): bool
    {
        $value = trim(mb_strtolower((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'y', 'بله', 'فعال', ''], true);
    }
}
