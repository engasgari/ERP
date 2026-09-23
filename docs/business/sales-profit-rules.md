# Sales Profit Rules (Audit 2026-09-16)

## Currency

All commercial amounts are stored and reported in **IRR (ریال)** with `decimal(18,2)`.
UI formatting uses `formatMoney()` — do not convert Rial/Toman in Blade/JS.

## Invoice math (InvoiceCalculationService)

- base = qty × unit_price
- taxable = max(base − discount, 0)
- tax = taxable × (tax_rate / 100)
- line_total = taxable + tax (includes VAT)
- header total = Σbase − Σdiscount + Σtax
- **net sales (ex VAT)** = subtotal − discount_amount

VAT is never treated as revenue profit or COGS.

## Gross profit (SalesProfitCalculationService)

```
gross_profit = net_sales − COGS
margin% = gross_profit / net_sales × 100
```

### COGS unit cost priority (products only)

1. Weighted average of confirmed **purchase** invoice lines on the **same project**
2. Weighted average of all confirmed purchase lines for the item
3. `items.purchase_price` (master)

Services: COGS = 0 in this layer (use ProjectCostingService for project P&L).

## Case: بیمه کوثر / SI-00064

- Wrong master formula: `purchase_price` 476M → COGS 2.856B → profit ≈ **1.164B**
- Correct (this service): project purchase weighted avg for item 65 on project 492
  - purchases: 2×560M + 2×588M + 6×547.4M → unit_cost ≈ **558.04M**
  - COGS for qty 6 ≈ **3.348B** → gross profit ≈ **671.76M** (margin ≈ 16.7%)
- Note: strict FIFO match to PI-00014 only would yield ≈ 735.6M — not implemented without business confirmation of lot matching
- Project PRJ-00018 pools many customers — project costing P&L ≠ Kosar invoice profit

## Non-goals of this layer

- Ledger P&L (41/51/52/53) remains FinancialReportService
- ProjectCostingService remains project rollup (all invoices on project)
- Executive monthly chart is a separate proxy and should not redefine sales gross profit
