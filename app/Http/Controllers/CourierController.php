<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Courier;

use App\Imports\RemittanceImport;
use Maatwebsite\Excel\Facades\Excel;

class CourierController extends Controller
{

    public function index()
    {
        $lims_courier_all = Courier::where('is_active', true)->orderBy('id', 'desc')->get();
        return view('backend.courier.index', compact('lims_courier_all'));
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->is_active = true;
        Courier::create($request->all());
        return redirect()->back()->with('message', 'Courier created successfully');
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        Courier::find($request->id)->update($request->all());
        return redirect()->back()->with('message', 'Courier updated successfully');
    }

    public function destroy($id)
    {
        Courier::find($id)->update(['is_active' => false]);
        return redirect()->back()->with('not_permitted', 'Courier deleted successfully');
    }

    public function process_excel(Request $request) {
        $request->validate([
            'remittance_file' => 'required|mimes:xlsx,csv,xls',
        ]);

        Excel::import(new RemittanceImport, $request->file('remittance_file'));
        return redirect()->back()->with('message', 'File imported successfully');
    }
}
