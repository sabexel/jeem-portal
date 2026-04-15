<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;

class ExpenseVatReportQuery
{
    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function fetch_rows(string $from_date, string $to_date)
    {
        $goods_desc = $this->goods_description_sql();
        $freight = $this->freight_allocated_sql();
        $payment_mode = $this->payment_mode_sql();

        return DB::table('purchases as pur')
            ->join('product_purchases as pp', 'pp.purchase_id', '=', 'pur.id')
            ->leftJoin('products as p', 'p.id', '=', 'pp.product_id')
            ->leftJoin('variants as v', 'v.id', '=', 'pp.variant_id')
            ->leftJoin('suppliers as sup', 'sup.id', '=', 'pur.supplier_id')
            ->whereDate('pur.created_at', '>=', $from_date)
            ->whereDate('pur.created_at', '<=', $to_date)
            ->selectRaw("
                DATE(pur.created_at) AS date,
                COALESCE(NULLIF(TRIM(sup.company_name), ''), sup.name, '') AS vendor,
                pur.reference_no AS invoice_reference_no,
                {$goods_desc} AS description_of_expenses,
                'Purchase' AS expense_type,
                (pp.qty * pp.net_unit_cost - pp.discount) AS amount_aed,
                pp.tax_rate AS vat_rate,
                pp.tax AS input_vat_aed,
                (pp.total + {$freight}) AS total_cost_aed,
                {$payment_mode} AS payment_mode,
                CASE
                    WHEN pur.payment_status = 1 THEN 'Due'
                    WHEN pur.payment_status = 2 THEN 'Paid'
                    ELSE 'Unknown'
                END AS payment_status,
                NULLIF(TRIM(pur.note), '') AS remarks
            ")
            ->orderBy('pur.created_at', 'asc')
            ->orderBy('pur.id', 'asc')
            ->orderBy('pp.id', 'asc')
            ->get();
    }

    protected function goods_description_sql(): string
    {
        return 'TRIM(CONCAT(IFNULL(p.name, ""), IF(v.name IS NOT NULL AND TRIM(v.name) != "", CONCAT(" - ", v.name), "")))';
    }

    /**
     * Allocate purchase-level shipping_cost across lines by each line's share of line totals.
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

    protected function payment_mode_sql(): string
    {
        return "COALESCE((
            SELECT GROUP_CONCAT(DISTINCT pay.paying_method ORDER BY pay.id SEPARATOR ', ')
            FROM payments pay
            WHERE pay.purchase_id = pur.id
        ), 'N/A')";
    }
}
