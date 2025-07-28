<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SalesExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{
    protected $starting_date;
    protected $ending_date;

    public function __construct($starting_date, $ending_date)
    {
        $this->starting_date = $starting_date;
        $this->ending_date = $ending_date;
    }
    
    
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // return Sale::all();
        // dd($this->starting_date);
        // return Sale::whereBetween('created_at', [$this->starting_date, $this->ending_date])->get();
        $end_date = Carbon::parse($this->ending_date)->endOfDay();
        // // dd($end_date);
        // dd(Sale::whereBetween('created_at', [$this->starting_date, $end_date])->get());
        return Sale::whereBetween('created_at', [$this->starting_date, $end_date])->get();
    }

    /**
     * Map the data for each row based on the required columns.
     */
    public function map($sales): array
    {
        // dd($sales->products);
        return [
            str_replace(['sr', '-'], '', $sales->reference_no),
            $sales->customer->name,
            $sales->customer->phone_number,
            $sales->consigneetelephone,
            $sales->customer->address,
            $sales->customer->city,
            $sales->customer->state,
            $sales->shipping_pieces,
            $sales->package_weight,
            '0',
            'COD',
            $sales->grand_total,
            $sales->pickupamount,
            '',
            '',
            // $sales->products->pluck('name')->implode(', '),
            /* $sales->products->map(function ($product) use ($sales) {
                $product_sales = \App\Models\Product_Sale::where('sale_id', $sales->id)
                ->join('product_variants', function ($join) {
                    $join->on('product_variants.variant_id', '=', 'product_sales.variant_id')
                        ->on('product_variants.product_id', '=', 'product_sales.product_id');
                })
                ->select('product_variants.item_code') 
                ->get();

                foreach ($product_sales as $sale) {
                    return $product->name . ' (' .$sale->item_code . ')';
                }
            
                // Return null if no variant is found
                return $product->name;
            })->filter()->implode(', '), */
            $sales->products->unique('id')->map(function ($product) use ($sales) {
                // Fetch the product sales specific to the current product
                $product_sales = \App\Models\Product_Sale::where('sale_id', $sales->id)
                    ->where('product_sales.product_id', $product->id) // Ensure filtering for the current product
                    ->join('product_variants', function ($join) {
                        $join->on('product_variants.variant_id', '=', 'product_sales.variant_id')
                             ->on('product_variants.product_id', '=', 'product_sales.product_id');
                    })
                    ->select('product_variants.item_code', 'product_sales.qty')
                    ->distinct() // Ensure unique item codes
                    ->get();

                // Prepare the output for each product sale
                $item_details = $product_sales->map(function ($sale) {
                    return $sale->item_code . ' x ' . $sale->qty; // Combine item_code and qty
                })->implode(', ');

                // Return the product name along with the item details
                if (!empty($item_details)) {
                    return $product->name . ' (' . $item_details . ')';
                }
            
                // If no item code is found, return the product name
                $product_sales = \App\Models\Product_Sale::where('sale_id', $sales->id)
                ->where('product_sales.product_id', $product->id)
                ->first();

                // dd($product_sales);
                return $product->name . '( x ' . (int)$product_sales->qty . ')';
            })->filter()->implode(', '),            
            'Handle with care Allow to open Parcel before Payment',
            '',
            '',
            '',
            '',
        ];
    }


    /**
     * Define column headings.
     */
    public function headings(): array
    {
        return [
            'SHIPPERREF',
            'CONSIGNEE',
            'CONSIGNEEMOBILE',
            'CONSIGNEETELEPHONE',
            'CONSIGNEEADDRESS',
            'DESTINATIONCODE',
            'CONSIGNEEAREA',
            'PIECES',
            'TOTALWEIGHT',
            'TOTALVOLWEIGHT',
            'SERVICE',
            'COD',
            'PICKUPAMOUNT',
            'LATITUDE',
            'LONGITUDE',
            'CONTENTS',
            'REMARKS',
            'DELIVERYSLOT',
            'HANDLEPACK',
            'HANDLECOLD',
            'HANDLEFRAGILE',
        ];
    }

    /**
     * Register events to modify the sheet after it is created.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Add dropdown list to the "Payment Method" column
                $validation = $sheet->getCell('K2')->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"COD,BulletService,CHQCollection,FOC,Fulfilment,NextDay,Prepaid,ReturnService-RTS"');

                

                // Apply the validation to the entire column
                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getCell('K' . $row)->setDataValidation(clone $validation);
                }
            },
        ];
    }
}