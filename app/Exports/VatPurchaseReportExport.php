<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VatPurchaseReportExport implements FromCollection, WithHeadings, WithMapping
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
            'Date of Import',
            'Supplier',
            'Supplier Invoice No',
            'CID/Bill of No',
            'Description of goods',
            'Quantity/Unit',
            'Purchase Value (AED)',
            'VAT Rate',
            'Input VAT (AED)',
            'Freight & Insurance (AED)',
            'Total Cost (AED)',
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
            $row->date_of_import,
            $row->supplier,
            $row->supplier_invoice_no,
            $row->cid_bill_no,
            $row->description_of_goods,
            $row->quantity_unit,
            $row->purchase_value_aed,
            $row->vat_rate,
            $row->input_vat_aed,
            $row->freight_insurance_aed,
            $row->total_cost_aed,
            $row->payment_status,
            $row->remarks,
        ];
    }
}
