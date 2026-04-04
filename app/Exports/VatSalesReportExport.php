<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VatSalesReportExport implements FromCollection, WithHeadings, WithMapping
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
            'invoice_no',
            'invoice_date',
            'customer_market_place',
            'customer_location',
            'emirates_country',
            'sales_return',
            'goods_description',
            'quantity',
            'unit_price_aed',
            'sales_value_aed',
            'vat_rate',
            'vat_amount',
            'total_amount_aed',
            'platform_fees_aed',
            'net_receivables_aed',
            'payment_status',
            'shipping_export_doc_no',
            'remarks',
        ];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            $row->invoice_no,
            $row->invoice_date,
            $row->customer_market_place,
            $row->customer_location,
            $row->emirates_country,
            $row->sales_return,
            $row->goods_description,
            $row->quantity,
            $row->unit_price_aed,
            $row->sales_value_aed,
            $row->vat_rate,
            $row->vat_amount,
            $row->total_amount_aed,
            $row->platform_fees_aed,
            $row->net_receivables_aed,
            $row->payment_status,
            $row->shipping_export_doc_no,
            $row->remarks,
        ];
    }
}
