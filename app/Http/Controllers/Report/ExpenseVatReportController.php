<?php

namespace App\Http\Controllers\Report;

use App\Exports\ExpenseVatReportExport;
use App\Http\Controllers\Controller;
use App\Services\Report\ExpenseVatReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class ExpenseVatReportController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('expense-vat-report')) {
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

        return view('backend.report.expense_vat_report', compact('all_permission'));
    }

    public function data(Request $request, ExpenseVatReportQuery $expense_vat_report_query): JsonResponse
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('expense-vat-report')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $rows = $expense_vat_report_query->fetch_rows($from_date, $to_date);

        return response()->json([
            'rows' => $rows,
            'count' => $rows->count(),
        ]);
    }

    public function export(Request $request, ExpenseVatReportQuery $expense_vat_report_query)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('expense-vat-report')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $rows = $expense_vat_report_query->fetch_rows($from_date, $to_date);
        $file_name = 'expense_vat_report_'.$from_date.'_'.$to_date.'.xlsx';

        return Excel::download(new ExpenseVatReportExport($rows), $file_name);
    }
}
