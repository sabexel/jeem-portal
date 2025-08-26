<?php
// app/Http/Controllers/Reports/InventoryAssetAccountController.php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exports\InventoryAssetAccountExport;
use Maatwebsite\Excel\Facades\Excel;

class InventoryAssetAccountController extends Controller
{
    public function index(Request $request)
    {
        // defaults (last 30 days)
        $from_date = $request->input('from_date', now()->subDays(30)->toDateString());
        $to_date   = $request->input('to_date', now()->toDateString());
        $store_id  = $request->input('store_id');     // optional
        $item_code = $request->input('item_code');    // optional

        return view('reports.inventory_asset_account.index', compact(
            'from_date', 'to_date', 'store_id', 'item_code'
        ));
    }

    public function data(Request $request)
    {
        $from_date = $request->input('from_date');
        $to_date   = $request->input('to_date');
        $store_id  = $request->input('store_id');
        $item_code = $request->input('item_code');

        $rows = $this->build_rows($from_date, $to_date, $store_id, $item_code);

        // Totals
        $total_debit  = $rows->sum('debit');
        $total_credit = $rows->sum('credit');
        $closing_balance = $rows->last()['running_balance'] ?? 0;

        return response()->json([
            'rows' => $rows,
            'totals' => [
                'debit' => $total_debit,
                'credit' => $total_credit,
                'closing_balance' => $closing_balance,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $from_date = $request->input('from_date');
        $to_date   = $request->input('to_date');
        $store_id  = $request->input('store_id');
        $item_code = $request->input('item_code');

        $rows = $this->build_rows($from_date, $to_date, $store_id, $item_code);

        $file_name = 'inventory_asset_account_' . ($from_date ?: 'all') . '_to_' . ($to_date ?: 'all') . '.xlsx';
        return Excel::download(new InventoryAssetAccountExport($rows), $file_name);
    }

    /**
     * Build unified rows for Inventory Asset Account (Details).
     * Running balance is computed in PHP (safe for all MySQL versions).
     */
    private function build_rows(?string $from_date, ?string $to_date, ?string $store_id, ?string $item_code): Collection
    {
        // ---------- Purchases (IN @ cost from product_purchases) ----------
        $purchases = DB::table('purchases as p')
            ->join('product_purchases as pp', 'pp.purchase_id', '=', 'p.id')
            ->join('products as pr', 'pr.id', '=', 'pp.product_id')
            ->leftJoin('product_variants as pv', function ($j) {
                $j->on('pv.product_id', '=', 'pr.id')->on('pv.id', '=', 'pp.variant_id');
            })
            ->leftJoin('suppliers as sup', 'sup.id', '=', 'p.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->when($from_date, fn($q) => $q->whereDate('p.created_at', '>=', $from_date))
            ->when($to_date,   fn($q) => $q->whereDate('p.created_at', '<=', $to_date))
            ->when($store_id,  fn($q) => $q->where('p.warehouse_id', $store_id))
            ->when($item_code, function ($q) use ($item_code) {
                $q->where(function ($q2) use ($item_code) {
                    $q2->where('pv.item_code', $item_code)->orWhere('pr.code', $item_code);
                });
            })
            ->get([
                DB::raw("'Purchase' as docu_type"),
                DB::raw('DATE(p.created_at) as date'),
                'p.reference_no as document_number',
                DB::raw('NULL as tracking_num'),
                DB::raw('COALESCE(pv.item_code, pr.code) as item_code'),
                'pr.name as description',
                'sup.name as name',
                'w.name as store_location',
                'pp.qty as qty',
                DB::raw('(pp.net_unit_cost * pp.qty) as debit'),
                DB::raw('0 as credit'),
                DB::raw("'Purchase received' as notes"),
            ])
            ->map(function ($r) {
                return [
                    'docu_type'       => (string) $r->docu_type,
                    'date'            => (string) $r->date,
                    'document_number' => (string) $r->document_number,
                    'tracking_num'    => null,
                    'item_code'       => (string) $r->item_code,
                    'description'     => (string) $r->description,
                    'name'            => $r->name ? (string) $r->name : null,
                    'store_location'  => $r->store_location ? (string) $r->store_location : null,
                    'qty'             => (float) $r->qty,
                    'debit'           => round((float) $r->debit, 2),
                    'credit'          => 0.0,
                    'notes'           => (string) $r->notes,
                ];
            });

        // ---------- Sales / Invoices (OUT @ product cost) ----------
        $sales = DB::table('sales as s')
            ->join('product_sales as ps', 'ps.sale_id', '=', 's.id')
            ->join('products as pr', 'pr.id', '=', 'ps.product_id')
            ->leftJoin('product_variants as pv', function ($j) {
                $j->on('pv.product_id', '=', 'pr.id')->on('pv.id', '=', 'ps.variant_id');
            })
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->when($from_date, fn($q) => $q->whereDate('s.created_at', '>=', $from_date))
            ->when($to_date,   fn($q) => $q->whereDate('s.created_at', '<=', $to_date))
            ->when($store_id,  fn($q) => $q->where('s.warehouse_id', $store_id))
            ->when($item_code, function ($q) use ($item_code) {
                $q->where(function ($q2) use ($item_code) {
                    $q2->where('pv.item_code', $item_code)->orWhere('pr.code', $item_code);
                });
            })
            ->get([
                DB::raw("'Invoice' as docu_type"),
                DB::raw('DATE(s.created_at) as date'),
                's.reference_no as document_number',
                's.awb as tracking_num',
                DB::raw('COALESCE(pv.item_code, pr.code) as item_code'),
                'pr.name as description',
                'c.name as name',
                'w.name as store_location',
                'ps.qty as qty',
                DB::raw('0 as debit'),
                DB::raw('(pr.cost * ps.qty) as credit'),
                DB::raw("'Sold (valued at product cost)' as notes"),
            ])
            ->map(function ($r) {
                return [
                    'docu_type'       => (string) $r->docu_type,
                    'date'            => (string) $r->date,
                    'document_number' => (string) $r->document_number,
                    'tracking_num'    => $r->tracking_num ? (string) $r->tracking_num : null,
                    'item_code'       => (string) $r->item_code,
                    'description'     => (string) $r->description,
                    'name'            => $r->name ? (string) $r->name : null,
                    'store_location'  => $r->store_location ? (string) $r->store_location : null,
                    'qty'             => (float) $r->qty,
                    'debit'           => 0.0,
                    'credit'          => round((float) $r->credit, 2),
                    'notes'           => (string) $r->notes,
                ];
            });

        // ---------- Sales Returns (IN @ product cost) ----------
        $sales_returns = DB::table('returns as r')
            ->join('product_returns as prr', 'prr.return_id', '=', 'r.id')
            ->join('products as pr', 'pr.id', '=', 'prr.product_id')
            ->leftJoin('product_variants as pv', function ($j) {
                $j->on('pv.product_id', '=', 'pr.id')->on('pv.id', '=', 'prr.variant_id');
            })
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->when($from_date, fn($q) => $q->whereDate('r.created_at', '>=', $from_date))
            ->when($to_date,   fn($q) => $q->whereDate('r.created_at', '<=', $to_date))
            ->when($store_id,  fn($q) => $q->where('r.warehouse_id', $store_id))
            ->when($item_code, function ($q) use ($item_code) {
                $q->where(function ($q2) use ($item_code) {
                    $q2->where('pv.item_code', $item_code)->orWhere('pr.code', $item_code);
                });
            })
            ->get([
                DB::raw("'Sales Return' as docu_type"),
                DB::raw('DATE(r.created_at) as date'),
                'r.reference_no as document_number',
                DB::raw('NULL as tracking_num'),
                DB::raw('COALESCE(pv.item_code, pr.code) as item_code'),
                'pr.name as description',
                'c.name as name',
                'w.name as store_location',
                'prr.qty as qty',
                DB::raw('(pr.cost * prr.qty) as debit'),
                DB::raw('0 as credit'),
                DB::raw("'Customer return (valued at product cost)' as notes"),
            ])
            ->map(function ($r) {
                return [
                    'docu_type'       => (string) $r->docu_type,
                    'date'            => (string) $r->date,
                    'document_number' => (string) $r->document_number,
                    'tracking_num'    => null,
                    'item_code'       => (string) $r->item_code,
                    'description'     => (string) $r->description,
                    'name'            => $r->name ? (string) $r->name : null,
                    'store_location'  => $r->store_location ? (string) $r->store_location : null,
                    'qty'             => (float) $r->qty,
                    'debit'           => round((float) $r->debit, 2),
                    'credit'          => 0.0,
                    'notes'           => (string) $r->notes,
                ];
            });

        // ---------- Merge + sort (date → document_number → item_code) ----------
        $rows = $purchases
            ->concat($sales)
            ->concat($sales_returns)
            ->sortBy(function ($r) {
                return sprintf('%s|%s|%s', $r['date'], $r['document_number'], $r['item_code']);
            })
            ->values();

        // ---------- Running balance ----------
        $running_balance = 0.0;
        $rows = $rows->map(function ($r) use (&$running_balance) {
            $running_balance += ($r['debit'] - $r['credit']);
            $r['running_balance'] = round($running_balance, 2);
            return $r;
        });

        return $rows;
    }
}