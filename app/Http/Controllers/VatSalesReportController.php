<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VatSalesReportController extends Controller
{
    public function index(Request $request)
    {
        $start_date = $request->get('start_date');
        $end_date   = $request->get('end_date');

        // default: current month
        if (empty($start_date) || empty($end_date)) {
            $start_date = now()->startOfMonth()->toDateString();
            $end_date   = now()->toDateString();
        }

        // basic validation (keep simple)
        $request->merge([
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ]);

        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        /*
         * NOTE:
         * - This report is line-item based (each sold product = one row).
         * - Sales Return column = returned amount for that sale (sum of returns.grand_total).
         * - Platform Fees: not found in DB dump; kept as 0 for now (you can map later).
         * - Net Receivables: uses sales.remittence_amount if available else (line_total - platform_fee).
         * - Shipping/Export Doc No: uses sales.awb if exists else deliveries.reference_no.
         */
        $rows = DB::table('sales as s')
            ->join('product_sales as ps', 'ps.sale_id', '=', 's.id')
            ->leftJoin('products as p', 'p.id', '=', 'ps.product_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('deliveries as d', 'd.sale_id', '=', 's.id')
            ->leftJoin(DB::raw('(
                SELECT sale_id, SUM(grand_total) AS returned_amount
                FROM returns
                GROUP BY sale_id
            ) r'), 'r.sale_id', '=', 's.id')
            ->whereDate('s.created_at', '>=', $start_date)
            ->whereDate('s.created_at', '<=', $end_date)
            ->selectRaw('
                s.reference_no                                         as invoice_no,
                DATE(s.created_at)                                     as invoice_date,

                COALESCE(NULLIF(c.ecom, ""), c.name)                   as customer_market_place,
                TRIM(CONCAT(IFNULL(c.city,""), IF(c.district IS NULL OR c.district="", "", CONCAT(" - ", c.district)))) as customer_location,
                COALESCE(NULLIF(c.country, ""), c.state)               as emirates_country,

                COALESCE(r.returned_amount, 0)                         as sales_return,

                p.name                                                 as goods_description,
                ps.qty                                                 as quantity,
                ps.net_unit_price                                      as unit_price_aed,

                (ps.qty * ps.net_unit_price - ps.discount)             as sales_value_aed,

                ps.tax_rate                                            as vat_rate,
                ps.tax                                                 as vat_amount,
                ps.total                                               as total_amount_aed,

                0                                                      as platform_fees_aed,

                CASE
                    WHEN s.remittence_amount IS NOT NULL THEN s.remittence_amount
                    ELSE (ps.total - 0)
                END                                                    as net_receivables_aed,

                CASE
                    WHEN s.payment_status = 1 THEN "Pending"
                    WHEN s.payment_status = 2 THEN "Due"
                    WHEN s.payment_status = 3 THEN "Partial"
                    ELSE "Paid"
                END                                                    as payment_status,

                COALESCE(NULLIF(s.awb,""), d.reference_no)             as shipping_export_doc_no,
                COALESCE(NULLIF(s.sale_note,""), s.staff_note)         as remarks
            ')
            ->orderBy('s.created_at', 'asc')
            ->paginate(200)
            ->appends([
                'start_date' => $start_date,
                'end_date'   => $end_date,
            ]);

        return view('backend.reports.vat_sales', compact('rows', 'start_date', 'end_date'));
    }
}
