<?php

namespace App\Http\Controllers\Report;

/**
 * VAT Sales Report — line-level (product_sales) listing for a date range.
 *
 * Column mapping (see App\Services\Report\VatSalesReportQuery):
 * - invoice_no: sales.reference_no
 * - invoice_date: DATE(sales.created_at)
 * - customer_market_place: customers.ecom when column exists, else customers.name
 * - customer_location: address / city / district (district when column exists)
 * - emirates_country: customers.country, else state
 * - sales_return: sale_status 4, product_sales.return_qty, returns.grand_total sum (via returns.sale_id)
 * - goods_description: products.name + optional variants.name
 * - quantity / unit_price_aed / VAT / totals: product_sales (qty, net_unit_price, discount, tax_rate, tax, total)
 * - sales_value_aed: qty * net_unit_price - discount (tax-exclusive line value)
 * - platform_fees_aed: no dedicated column — 0
 * - net_receivables_aed: proportional sales.remittence_amount by line when set, else ps.total
 * - payment_status: sales.payment_status (same labels as Sale module)
 * - shipping_export_doc_no: sales.awb when present, else latest deliveries.reference_no
 * - remarks: sale_note / staff_note via CONCAT_WS
 */
use App\Exports\VatPurchaseReportExport;
use App\Exports\VatSalesReportExport;
use App\Http\Controllers\Controller;
use App\Services\Report\VatPurchaseReportQuery;
use App\Services\Report\VatSalesReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class VatSalesReportController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('vat-sales-report')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $permissions = $role->permissions;
        $all_permission = [];
        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }
        if (empty($all_permission)) {
            $all_permission[] = 'dummy text';
        }

        return view('backend.report.vat_sales_report', compact('all_permission'));
    }

    public function data(Request $request, VatSalesReportQuery $vat_sales_report_query): JsonResponse
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('vat-sales-report')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $rows = $vat_sales_report_query->fetch_rows($from_date, $to_date);

        return response()->json([
            'rows' => $rows,
            'count' => $rows->count(),
        ]);
    }

    public function export(Request $request, VatSalesReportQuery $vat_sales_report_query)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('vat-sales-report')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $rows = $vat_sales_report_query->fetch_rows($from_date, $to_date);
        $file_name = 'vat_sales_report_'.$from_date.'_'.$to_date.'.xlsx';

        return Excel::download(new VatSalesReportExport($rows), $file_name);
    }

    public function purchaseIndex()
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('vat-purchase-report')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $permissions = $role->permissions;
        $all_permission = [];
        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }
        if (empty($all_permission)) {
            $all_permission[] = 'dummy text';
        }

        return view('backend.report.vat_purchase_report', compact('all_permission'));
    }

    public function purchaseData(Request $request, VatPurchaseReportQuery $vat_purchase_report_query): JsonResponse
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('vat-purchase-report')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $rows = $vat_purchase_report_query->fetch_rows($from_date, $to_date);

        return response()->json([
            'rows' => $rows,
            'count' => $rows->count(),
        ]);
    }

    public function purchaseExport(Request $request, VatPurchaseReportQuery $vat_purchase_report_query)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('vat-purchase-report')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $rows = $vat_purchase_report_query->fetch_rows($from_date, $to_date);
        $file_name = 'vat_purchase_report_'.$from_date.'_'.$to_date.'.xlsx';

        return Excel::download(new VatPurchaseReportExport($rows), $file_name);
    }
}
