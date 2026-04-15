<?php

namespace App\Http\Controllers\Report;

use App\Exports\TrialBalanceReportExport;
use App\Http\Controllers\Controller;
use App\Services\Report\TrialBalanceReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class TrialBalanceReportController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('trial-balance-report')) {
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

        return view('backend.report.trial_balance_report', compact('all_permission'));
    }

    public function data(Request $request, TrialBalanceReportQuery $trial_balance_report_query): JsonResponse
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('trial-balance-report')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $report = $trial_balance_report_query->fetch_rows($from_date, $to_date);

        return response()->json([
            'rows' => $report['rows'],
            'total_debit' => $report['total_debit'],
            'total_credit' => $report['total_credit'],
        ]);
    }

    public function export(Request $request, TrialBalanceReportQuery $trial_balance_report_query)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('trial-balance-report')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $report = $trial_balance_report_query->fetch_rows($from_date, $to_date);
        $file_name = 'trial_balance_report_'.$from_date.'_'.$to_date.'.xlsx';

        return Excel::download(
            new TrialBalanceReportExport($report['rows'], $from_date, $to_date),
            $file_name
        );
    }
}
