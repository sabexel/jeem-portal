<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Adjustment;
use App\Models\Returns;


class CustomReportsController extends Controller
{
    public function inventory_assets_accounts(Request $request)
    {
        $start_date = $request->input('start_date');
        $end_date   = $request->input('end_date');

        // Helper closure to keep code tidy
        $make_row = function (array $row) {
            // Ensure all expected keys exist; missing values become null
            $defaults = [
                'docu_type'        => null, // purchase | sale | return | adjustment
                'date'             => null, // Carbon or date string
                'document_no'      => null,
                'tracking_no'      => null,
                'item_code'        => null,
                'description'      => null,
                'name'             => null, // supplier/customer/etc
                'status'           => null,
                'store_location'   => null, // warehouse name/id
                'qty'              => null,
                'debit_cost'       => null,
                'credit_cost'      => null,
                'running_balance'  => null, // fill later if you compute it
                'notes'            => null,
            ];
            return array_merge($defaults, $row);
        };

        $purchases = Purchase::with(['supplier','warehouse'])  // eager load if you have relations
            ->when($start_date && $end_date, fn($q)=>$q->whereBetween('created_at', [$start_date, $end_date]))
            ->get()
            ->map(function ($m) use ($make_row) {
                return $make_row([
                    'docu_type'      => 'purchase',
                    'date'           => $m->created_at,
                    'document_no'    => $m->reference_no,
                    'tracking_no'    => null,
                    'item_code'      => null,
                    'description'    => 'Purchase',
                    'name'           => optional($m->supplier)->name,
                    'status'         => $m->status,
                    'store_location' => optional($m->warehouse)->name ?? $m->warehouse_id,
                    'qty'            => $m->total_qty,
                    'debit_cost'     => $m->total_cost,   // inventory increases
                    'credit_cost'    => null,
                    'notes'          => $m->note,
                ]);
            });

        $sales = Sale::with(['customer','warehouse'])
            ->when($start_date && $end_date, fn($q)=>$q->whereBetween('created_at', [$start_date, $end_date]))
            ->get()
            ->map(function ($m) use ($make_row) {
                return $make_row([
                    'docu_type'      => 'sale',
                    'date'           => $m->created_at,
                    'document_no'    => $m->reference_no,
                    'tracking_no'    => $m->awb, // tracking (if any)
                    'item_code'      => null,
                    'description'    => 'Sale',
                    'name'           => optional($m->customer)->name,
                    'status'         => $m->sale_status,
                    'store_location' => optional($m->warehouse)->name ?? $m->warehouse_id,
                    'qty'            => $m->total_qty,
                    'debit_cost'     => null,
                    'credit_cost'    => $m->total_price,  // inventory decreases (use cost if you track COGS separately)
                    'notes'          => $m->sale_note,
                ]);
            });

        $returns = Returns::with(['customer','warehouse'])
            ->when($start_date && $end_date, fn($q)=>$q->whereBetween('created_at', [$start_date, $end_date]))
            ->get()
            ->map(function ($m) use ($make_row) {
                return $make_row([
                    'docu_type'      => 'return',
                    'date'           => $m->created_at,
                    'document_no'    => $m->reference_no,
                    'description'    => 'Customer Return',
                    'name'           => optional($m->customer)->name,
                    'status'         => $m->order_tax_rate, // or any status field you keep
                    'store_location' => optional($m->warehouse)->name ?? $m->warehouse_id,
                    'qty'            => $m->total_qty,
                    'debit_cost'     => $m->total_price, // inventory comes back
                    'credit_cost'    => null,
                    'notes'          => $m->return_note,
                ]);
            });

        $adjustments = Adjustment::when($start_date && $end_date, fn($q)=>$q->whereBetween('created_at', [$start_date, $end_date]))
            ->get()
            ->map(function ($m) use ($make_row) {
                // Positive qty => debit (add); negative => credit (remove)
                $qty = $m->total_qty;
                return $make_row([
                    'docu_type'      => 'adjustment',
                    'date'           => $m->created_at,
                    'document_no'    => $m->reference_no,
                    'description'    => 'Stock Adjustment',
                    'name'           => null,
                    'status'         => null,
                    'store_location' => $m->warehouse_id,
                    'qty'            => $qty,
                    'debit_cost'     => $qty > 0 ? abs($qty) : null,
                    'credit_cost'    => $qty < 0 ? abs($qty) : null,
                    'notes'          => $m->note,
                ]);
            });

        // Merge + sort by date desc
        $rows = $purchases->merge($sales)->merge($returns)->merge($adjustments)
            ->sortByDesc(fn($r)=>$r['date'])
            ->values();

        // (Optional) compute running balance by walking from oldest to newest:
        $running = 0;
        $rows = $rows->sortBy(fn($r)=>$r['date'])->values()->map(function ($r) use (&$running) {
            $running += ($r['debit_cost'] ?? 0) - ($r['credit_cost'] ?? 0);
            $r['running_balance'] = $running;
            return $r;
        })->sortByDesc(fn($r)=>$r['date'])->values();

        return view('backend.custom_reports.inventory_asset_account', [
            'all_records' => $rows
        ]);
    }

    
    /* public function inventory_assets_accounts(Request $request)
    {
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $purchases = Purchase::when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('created_at', [$start_date, $end_date]);
            })
            ->get()
            ->map(function ($item) {
                $item->type = 'purchase';
                return $item;
            });

        $invoices = Sale::when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('created_at', [$start_date, $end_date]);
            })
            ->get()
            ->map(function ($item) {
                $item->type = 'sale';
                return $item;
            });

        $returns = Returns::when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('created_at', [$start_date, $end_date]);
            })
            ->get()
            ->map(function ($item) {
                $item->type = 'return';
                return $item;
            });

        $adjustments = Adjustment::when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('created_at', [$start_date, $end_date]);
            })
            ->get()
            ->map(function ($item) {
                $item->type = 'adjustment';
                return $item;
            });

        // Merge and sort by created_at desc
        $all_records = $purchases
            ->merge($invoices)
            ->merge($returns)
            ->merge($adjustments)
            ->sortByDesc('created_at')
            ->values(); // reindex

        // dd($all_records);

        return view('backend.custom_reports.inventory_asset_account', compact('all_records'));
    } */

}
