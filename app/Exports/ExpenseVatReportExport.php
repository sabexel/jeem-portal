<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExpenseVatReportExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var Collection<int, object>
     */
    protected $rows;

    /**
     * @param  Collection<int, object>  $rows
     */
    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Vendor',
            'Invoice/Reference No',
            'Description of Expenses',
            'Expense Type',
            'Amount (AED)',
            'VAT Rate',
            'Input VAT (AED)',
            'Total Cost (AED)',
            'Payment mode',
            'Payment Status',
            'Remarks',
        ];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            $row->date,
            $row->vendor,
            $row->invoice_reference_no,
            $row->description_of_expenses,
            $row->expense_type,
            $row->amount_aed,
            $row->vat_rate,
            $row->input_vat_aed,
            $row->total_cost_aed,
            $row->payment_mode,
            $row->payment_status,
            $row->remarks,
        ];
    }
}
