<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT Purchase Report — one row per product_purchases line.
 *
 * - date_of_import: DATE(purchases.created_at)
 * - supplier_invoice_no: purchases.reference_no (internal / supplier ref)
 * - cid_bill_no: purchases.document when present
 * - purchase_value_aed: tax-exclusive line value (qty * net_unit_cost - discount)
 * - freight_insurance_aed: purchases.shipping_cost allocated by line share of line totals
 * - total_cost_aed: line total (incl. VAT) + allocated freight
 */
class VatPurchaseReportQuery
{
    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function fetch_rows(string $from_date, string $to_date)
    {
        $goods_desc = $this->goods_description_sql();
        $freight = $this->freight_allocated_sql();
        $remarks = $this->remarks_sql();

        $query = DB::table('purchases as pur')
            ->join('product_purchases as pp', 'pp.purchase_id', '=', 'pur.id')
            ->leftJoin('products as p', 'p.id', '=', 'pp.product_id')
            ->leftJoin('variants as v', 'v.id', '=', 'pp.variant_id')
            ->leftJoin('suppliers as sup', 'sup.id', '=', 'pur.supplier_id')
            ->leftJoin('units as u', 'u.id', '=', 'pp.purchase_unit_id')
            ->whereDate('pur.created_at', '>=', $from_date)
            ->whereDate('pur.created_at', '<=', $to_date)
            ->selectRaw("
                DATE(pur.created_at) AS date_of_import,
                COALESCE(NULLIF(TRIM(sup.company_name), ''), sup.name, '') AS supplier,
                pur.reference_no AS supplier_invoice_no,
                NULLIF(TRIM(pur.document), '') AS cid_bill_no,
                {$goods_desc} AS description_of_goods,
                TRIM(CONCAT(CAST(pp.qty AS CHAR), ' / ', IFNULL(u.unit_name, ''))) AS quantity_unit,
                (pp.qty * pp.net_unit_cost - pp.discount) AS purchase_value_aed,
                pp.tax_rate AS vat_rate,
                pp.tax AS input_vat_aed,
                {$freight} AS freight_insurance_aed,
                (pp.total + {$freight}) AS total_cost_aed,
                CASE
                    WHEN pur.payment_status = 1 THEN 'Due'
                    ELSE 'Paid'
                END AS payment_status,
                {$remarks} AS remarks
            ")
            ->orderBy('pur.created_at', 'asc')
            ->orderBy('pur.id', 'asc')
            ->orderBy('pp.id', 'asc');

        return $query->get();
    }

    protected function goods_description_sql(): string
    {
        return 'TRIM(CONCAT(IFNULL(p.name, ""), IF(v.name IS NOT NULL AND TRIM(v.name) != "", CONCAT(" - ", v.name), "")))';
    }

    /**
     * Allocate purchase-level shipping_cost across lines by each line's share of sum(line total).
     */
    protected function freight_allocated_sql(): string
    {
        return "COALESCE(CASE
            WHEN COALESCE(pur.shipping_cost, 0) = 0 THEN 0
            ELSE COALESCE(pur.shipping_cost, 0) * (pp.total / NULLIF((
                SELECT SUM(pp2.total) FROM product_purchases pp2 WHERE pp2.purchase_id = pur.id
            ), 0))
        END, 0)";
    }

    protected function remarks_sql(): string
    {
        $note = "NULLIF(TRIM(pur.note), '')";

        if (Schema::hasColumn('product_purchases', 'return_qty')) {
            return "NULLIF(TRIM(CONCAT_WS(' | ', {$note}, CASE WHEN COALESCE(pp.return_qty, 0) > 0 THEN CONCAT('Return qty: ', pp.return_qty) ELSE NULL END)), '')";
        }

        return $note;
    }
}
