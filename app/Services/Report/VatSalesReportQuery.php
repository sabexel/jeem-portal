<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds VAT Sales Report rows (one row per product_sales line).
 *
 * Column sourcing notes are documented in VatSalesReportController.
 */
class VatSalesReportQuery
{
    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function fetch_rows(string $from_date, string $to_date)
    {
        $customer_market = $this->customer_market_place_sql();
        $customer_loc = $this->customer_location_sql();
        $goods_desc = $this->goods_description_sql();
        $sales_return = $this->sales_return_sql();
        $net_recv = $this->net_receivables_sql();
        $shipping_doc = $this->shipping_export_doc_sql();

        $query = DB::table('sales as s')
            ->join('product_sales as ps', 'ps.sale_id', '=', 's.id')
            ->leftJoin('products as p', 'p.id', '=', 'ps.product_id')
            ->leftJoin('variants as v', 'v.id', '=', 'ps.variant_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin(DB::raw('(
                SELECT sale_id, SUM(grand_total) AS returned_amount
                FROM `returns`
                GROUP BY sale_id
            ) r'), 'r.sale_id', '=', 's.id')
            ->whereDate('s.created_at', '>=', $from_date)
            ->whereDate('s.created_at', '<=', $to_date)
            ->selectRaw("
                s.reference_no AS invoice_no,
                DATE(s.created_at) AS invoice_date,
                {$customer_market} AS customer_market_place,
                {$customer_loc} AS customer_location,
                COALESCE(NULLIF(TRIM(c.country), ''), NULLIF(TRIM(c.state), '')) AS emirates_country,
                {$sales_return} AS sales_return,
                {$goods_desc} AS goods_description,
                ps.qty AS quantity,
                ps.net_unit_price AS unit_price_aed,
                (ps.qty * ps.net_unit_price - ps.discount) AS sales_value_aed,
                ps.tax_rate AS vat_rate,
                ps.tax AS vat_amount,
                ps.total AS total_amount_aed,
                0 AS platform_fees_aed,
                {$net_recv} AS net_receivables_aed,
                CASE
                    WHEN s.payment_status = 1 THEN 'Pending'
                    WHEN s.payment_status = 2 THEN 'Due'
                    WHEN s.payment_status = 3 THEN 'Partial'
                    ELSE 'Paid'
                END AS payment_status,
                {$shipping_doc} AS shipping_export_doc_no,
                NULLIF(TRIM(CONCAT_WS(' | ', NULLIF(TRIM(s.sale_note), ''), NULLIF(TRIM(s.staff_note), ''))), '') AS remarks
            ")
            ->orderBy('s.created_at', 'asc')
            ->orderBy('s.id', 'asc')
            ->orderBy('ps.id', 'asc');

        return $query->get();
    }

    protected function customer_market_place_sql(): string
    {
        // Marketplace label: use dedicated column when present; otherwise customer name.
        if (Schema::hasColumn('customers', 'ecom')) {
            return 'COALESCE(NULLIF(TRIM(c.ecom), ""), c.name)';
        }

        return 'c.name';
    }

    protected function customer_location_sql(): string
    {
        if (Schema::hasColumn('customers', 'district')) {
            return 'TRIM(CONCAT(IFNULL(c.address, ""), IF(c.address IS NOT NULL AND TRIM(c.address) != "" AND c.city IS NOT NULL AND TRIM(c.city) != "", ", ", ""), IFNULL(c.city, ""), IF(c.district IS NULL OR TRIM(c.district) = "", "", CONCAT(" - ", c.district))))';
        }

        return 'TRIM(CONCAT(IFNULL(c.address, ""), IF(c.address IS NOT NULL AND TRIM(c.address) != "" AND c.city IS NOT NULL AND TRIM(c.city) != "", ", ", ""), IFNULL(c.city, "")))';
    }

    protected function goods_description_sql(): string
    {
        return 'TRIM(CONCAT(IFNULL(p.name, ""), IF(v.name IS NOT NULL AND TRIM(v.name) != "", CONCAT(" - ", v.name), "")))';
    }

    protected function sales_return_sql(): string
    {
        // sale_status: 4 = Returned (SaleController). product_sales.return_qty = line-level returns when migrated.
        $line_qty = Schema::hasColumn('product_sales', 'return_qty')
            ? 'COALESCE(ps.return_qty, 0)'
            : '0';

        return "CONCAT_WS(' | ',
            CASE WHEN s.sale_status = 4 THEN 'Invoice: Returned' ELSE NULL END,
            CASE WHEN {$line_qty} > 0 THEN CONCAT('Line return qty: ', {$line_qty}) ELSE NULL END,
            CASE WHEN COALESCE(r.returned_amount, 0) > 0 THEN CONCAT('Return docs total: ', COALESCE(r.returned_amount, 0)) ELSE NULL END,
            CASE
                WHEN s.sale_status <> 4 AND {$line_qty} = 0 AND COALESCE(r.returned_amount, 0) = 0 THEN 'Sale'
                ELSE NULL
            END
        )";
    }

    protected function net_receivables_sql(): string
    {
        // platform_fees_aed: no per-line source in schema — treated as 0 in SELECT above.
        // remittence_amount: sale-level net received; allocate to lines by line share of grand_total when set.
        if (Schema::hasColumn('sales', 'remittence_amount')) {
            return "CASE
                WHEN s.remittence_amount IS NOT NULL THEN
                    CASE
                        WHEN s.grand_total IS NULL OR s.grand_total = 0 THEN ps.total
                        ELSE ROUND(s.remittence_amount * (ps.total / s.grand_total), 4)
                    END
                ELSE ps.total
            END";
        }

        return 'ps.total';
    }

    protected function shipping_export_doc_sql(): string
    {
        // Prefer sale AWB; else latest delivery reference for the sale (avoids duplicate rows from join).
        if (Schema::hasColumn('sales', 'awb')) {
            return "COALESCE(NULLIF(TRIM(s.awb), ''), (SELECT d2.reference_no FROM deliveries d2 WHERE d2.sale_id = s.id ORDER BY d2.id DESC LIMIT 1))";
        }

        return '(SELECT d2.reference_no FROM deliveries d2 WHERE d2.sale_id = s.id ORDER BY d2.id DESC LIMIT 1)';
    }
}
