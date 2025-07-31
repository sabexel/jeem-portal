<?php

namespace App\Imports;

use App\Models\Sale;
use App\Models\Payment;
use App\Models\CashRegister;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

class RemittanceImport implements OnEachRow, WithHeadingRow, WithStartRow
{
    public $total_read_rows = 0;
    public $total_processed_rows = 0;
    public $errors = [];

    public function startRow(): int
    {
        return 9;
    }

    public function headingRow(): int
    {
        return 8;
    }

    public function onRow(Row $row)
    {
        $this->total_read_rows++;

        try {
            $row_data = $row->toArray();

            $raw_ref = $row_data['ref']; // e.g. 20250204083739
            $formatted_ref = 'sr-' . substr($raw_ref, 0, 8) . '-' . substr($raw_ref, 8);
            $sale = Sale::where('reference_no', $formatted_ref)->orwhere('reference_no', $raw_ref)->first();
            // $sale = Sale::where('reference_no', $row_data['ref'])->first();

            if (!$sale) {
                $this->errors[] = "Sale not found for reference: " . $row_data['ref'];
                return;
            }

            $total_paid = Payment::where('sale_id', $sale->id)->sum('amount');
            if ($total_paid == $sale->grand_total) {
                // Payment already completed, skip this row
                return;
            }

            if ($sale->paid_amount < $sale->grand_total) {
                $cash_register_data = CashRegister::where([
                    ['user_id', Auth::id()],
                    ['warehouse_id', $sale->warehouse_id],
                    ['status', true]
                ])->first();

                if (!$cash_register_data) {
                    $this->errors[] = "Cash register not found for user in warehouse: " . $sale->warehouse_id;
                    return;
                }

                Payment::create([
                    'purchase_id'        => null,
                    'user_id'            => Auth::id(),
                    'sale_id'            => $sale->id,
                    'cash_register_id'   => $cash_register_data->id,
                    'account_id'         => 1,
                    'payment_receiver'   => Auth::user()->name,
                    'payment_reference'  => 'spr-' . date("Ymd") . '-' . date("his"),
                    'amount'             => $row_data['cod'],
                    'used_points'        => 0,
                    'change'             => 0,
                    'paying_method'      => 'Deposite',
                    'payment_note'       => 'TFM',
                ]);

                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    $customer->expense += $row_data['cod'];
                    $customer->save();
                }
            }

            $sale->paid_amount = $row_data['cod'];
            $sale->remittence_amount = $row_data['remitted'];
            $sale->awb = $row_data['awb'];
            $sale->courier_charges = $row_data['charges'];
            if ($row_data['cod'] == $sale->grand_total) {
                $sale->payment_status = 4;
            }
            $sale->save();

            $this->total_processed_rows++;
        } catch (\Exception $e) {
            $this->errors[] = "Row " . $this->total_read_rows . " failed: " . $e->getMessage();
        }
    }
}
