<?php

namespace App\Services\Report;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceReportQuery
{
    /**
     * @return array{rows: Collection<int, array<string, mixed>>, total_debit: float, total_credit: float}
     */
    public function fetch_rows(string $from_date, string $to_date): array
    {
        $account_balances = $this->account_balances($from_date, $to_date);
        $expense_category_balances = $this->expense_category_balances($from_date, $to_date);

        $rows = [];
        $used_fixed_expense_heads = [];

        $push = function (
            string $no,
            string $particular,
            int $level,
            float $debit = 0,
            float $credit = 0,
            bool $is_header = false
        ) use (&$rows): void {
            $rows[] = [
                'no' => $no,
                'particular' => $particular,
                'level' => $level,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'is_header' => $is_header,
            ];
        };

        $sum_accounts = function (array $names) use ($account_balances): array {
            $debit = 0.0;
            $credit = 0.0;

            foreach ($names as $name) {
                $normalized = $this->normalize($name);
                if (! isset($account_balances[$normalized])) {
                    continue;
                }
                $debit += $account_balances[$normalized]['debit'];
                $credit += $account_balances[$normalized]['credit'];
            }

            return ['debit' => $debit, 'credit' => $credit];
        };

        $sum_expense_categories = function (array $names) use ($expense_category_balances, &$used_fixed_expense_heads): float {
            $amount = 0.0;
            foreach ($names as $name) {
                $normalized = $this->normalize($name);
                if (! isset($expense_category_balances[$normalized])) {
                    continue;
                }
                $amount += $expense_category_balances[$normalized];
                $used_fixed_expense_heads[$normalized] = true;
            }

            return $amount;
        };

        // 1 ASSETS
        $push('1', 'ASSETS', 0, 0, 0, true);
        $push('1.1', 'Current Assets', 1, 0, 0, true);
        $push('1.1.1', 'Cash & Bank', 2, 0, 0, true);
        foreach ([
            'Cash & Cash Equivalent (Parent)',
            'Habib Bank AG Zurich',
            'WIO Bank',
            'Cash in Hand',
            'Petty Cash',
            'Payment Clearing Account',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('1.1.2', 'Accounts Receivable', 2, 0, 0, true);
        foreach (['Accounts Receivable (A/R)'] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('1.1.3', 'Inventory', 2, 0, 0, true);
        foreach ([
            'Inventory Asset (Parent)',
            'ABS Bags',
            'PP Bags',
            'EV Bags',
            'Duffle Bags',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('1.1.4', 'Advances & Prepayments', 2, 0, 0, true);
        foreach ([
            'Advances & Prepayments (Parent)',
            'Advance to Suppliers',
            'License Renewal Charges (Prepaid)',
            'Employee Advances',
            'Director Advance - Ali',
            'Director Advance - Atif',
            'Prepaid Rent - UAE',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('1.1.5', 'VAT Recoverable', 2, 0, 0, true);
        foreach (['VAT Input on Purchases / Expenses'] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('1.2', 'Non-Current Assets', 1, 0, 0, true);
        $push('1.2.1', 'Property, Plant & Equipment', 2, 0, 0, true);
        foreach ([
            'PPE (Parent)',
            'Computers & Laptops',
            'Pakistan Office Initial Investment (Furniture/Fixtures)',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('1.2.2', 'Intangible Assets', 2, 0, 0, true);
        foreach ([
            'Intangible Assets',
            'Software License',
            'Website Costs (if any)',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        // 2 LIABILITIES
        $push('2', 'LIABILITIES', 0, 0, 0, true);
        $push('2.1', 'Current Liabilities', 1, 0, 0, true);
        $push('2.1.1', 'Accounts Payable', 2, 0, 0, true);
        foreach (['Accounts Payable (A/P)'] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('2.1.2', 'Payroll & Accrued Expenses', 2, 0, 0, true);
        foreach ([
            'Payroll Liabilities (Parent)',
            'UAE - Salary Payable',
            'Employee Visa Payable',
            'Accrued Expenses',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('2.1.3', 'VAT Payable', 2, 0, 0, true);
        foreach ([
            'VAT Output on Sales',
            'VAT Payable to FTA',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('2.1.4', 'Other Payables', 2, 0, 0, true);
        foreach ([
            'Delivery Charges Deducted by TFM',
            'Outstanding Customer Balances (TFM/Walk-in holds)',
            'Bank Charges Deducted by Bank',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('2.2', 'Non-Current Liabilities', 1, 0, 0, true);
        $push('2.2.1', 'Director Loans', 2, 0, 0, true);
        foreach ([
            'Loan - Ali Taha',
            "Loan - Boss' Father",
            'Loan - Atif',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        // 3 EQUITY
        $push('3', 'EQUITY', 0, 0, 0, true);
        $push('3.1', 'Paid-up Capital', 1, 0, 0, true);
        foreach ([
            'Capital - Atif',
            'Capital - Ali',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }
        $push('3.2', 'Retained Earnings', 1, 0, 0, true);
        foreach (['Retained Earnings'] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        // 4 INCOME
        $push('4', 'INCOME', 0, 0, 0, true);
        $push('4.1', 'Operating Revenue', 1, 0, 0, true);
        foreach ([
            'Bag Sales (Parent)',
            'ABS Bag Sales',
            'PP Bag Sales',
            'Duffle Bag Sales',
            'EV Bag Sales',
            'Vegetable Cutter Sales',
            'Leather Products Sales',
            'Other Items Sales',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        // 5 COGS
        $push('5', 'COST OF GOODS SOLD (COGS)', 0, 0, 0, true);
        $push('5.1', 'Inventory Sold', 1, 0, 0, true);
        foreach (['COGS - Bags'] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('5.2', 'Direct Delivery Costs', 1, 0, 0, true);
        foreach ([
            'Delivery Cost - J&T / LC',
            'Delivery Cost - TFM',
            'Amazon Charges',
            'Delivery Charge - Bag Supply',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        $push('5.3', 'Other Direct Costs', 1, 0, 0, true);
        foreach ([
            'Commission',
            'Packaging Cost',
            'Vehicle Fuel (if used for delivery)',
        ] as $label) {
            $amt = $sum_accounts([$label]);
            $push('', $label, 3, $amt['debit'], $amt['credit']);
        }

        // 6 EXPENSES
        $push('6', 'EXPENSES', 0, 0, 0, true);
        $push('6.1', 'Marketing', 1, 0, 0, true);
        foreach ([
            'Advertisement & Publicity',
            'SMM - Bags',
        ] as $label) {
            $amount = $sum_expense_categories([$label]);
            $push('', $label, 3, $amount, 0);
        }

        $push('6.2', 'Rent & Utilities', 1, 0, 0, true);
        foreach ([
            'UAE Store Rent',
            'Electricity Expense',
            'Internet Bill',
            'Drinking Water',
        ] as $label) {
            $amount = $sum_expense_categories([$label]);
            $push('', $label, 3, $amount, 0);
        }

        $push('6.3', 'Office & Admin', 1, 0, 0, true);
        foreach ([
            'Office Supplies',
            'Miscellaneous',
            'Cash Over/Short',
        ] as $label) {
            $amount = $sum_expense_categories([$label]);
            $push('', $label, 3, $amount, 0);
        }

        $push('6.4', 'Bank & Finance Costs', 1, 0, 0, true);
        foreach (['Bank Charges'] as $label) {
            $amount = $sum_expense_categories([$label]);
            $push('', $label, 3, $amount, 0);
        }

        $push('6.5', 'Travel', 1, 0, 0, true);
        foreach (['Travel on Official Business'] as $label) {
            $amount = $sum_expense_categories([$label]);
            $push('', $label, 3, $amount, 0);
        }

        $push('6.7', 'Salaries & HR', 1, 0, 0, true);
        foreach ([
            'Salary Expense',
            'Overtime',
            'Employee Medical Expense',
            'Employee Visa Cost',
        ] as $label) {
            $amount = $sum_expense_categories([$label]);
            $push('', $label, 3, $amount, 0);
        }

        $push('6.8', 'Others', 1, 0, 0, true);
        foreach ([
            'Donations & Charity',
            'Meals & Entertainment',
        ] as $label) {
            $amount = $sum_expense_categories([$label]);
            $push('', $label, 3, $amount, 0);
        }

        // Dynamic expense heads that are newly added in Expense Categories
        foreach ($expense_category_balances as $normalized_name => $amount) {
            if (isset($used_fixed_expense_heads[$normalized_name])) {
                continue;
            }
            $label = $this->title_from_normalized($normalized_name);
            $push('', $label, 3, $amount, 0);
        }

        $total_debit = array_sum(array_column($rows, 'debit'));
        $total_credit = array_sum(array_column($rows, 'credit'));
        $push('', 'Total', 0, $total_debit, $total_credit);

        return [
            'rows' => collect($rows),
            'total_debit' => round($total_debit, 2),
            'total_credit' => round($total_credit, 2),
        ];
    }

    /**
     * @return array<string, array{debit: float, credit: float}>
     */
    protected function account_balances(string $from_date, string $to_date): array
    {
        $accounts = DB::table('accounts')
            ->where('is_active', 1)
            ->select('id', 'name', DB::raw('COALESCE(initial_balance, 0) as initial_balance'))
            ->get();

        $sale_payment_credit = DB::table('payments')
            ->whereNotNull('sale_id')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('account_id', DB::raw('SUM(amount) as amount'))
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        $purchase_payment_debit = DB::table('payments')
            ->whereNotNull('purchase_id')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('account_id', DB::raw('SUM(amount) as amount'))
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        $return_sale_debit = DB::table('returns')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('account_id', DB::raw('SUM(grand_total) as amount'))
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        $return_purchase_credit = DB::table('return_purchases')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('account_id', DB::raw('SUM(grand_total) as amount'))
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        $expense_debit = DB::table('expenses')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('account_id', DB::raw('SUM(amount) as amount'))
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        $payroll_debit = DB::table('payrolls')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('account_id', DB::raw('SUM(amount) as amount'))
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        $income_credit = DB::table('incomes')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('account_id', DB::raw('SUM(amount) as amount'))
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        $transfer_debit = DB::table('money_transfers')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('from_account_id as account_id', DB::raw('SUM(amount) as amount'))
            ->groupBy('from_account_id')
            ->pluck('amount', 'account_id');

        $transfer_credit = DB::table('money_transfers')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->select('to_account_id as account_id', DB::raw('SUM(amount) as amount'))
            ->groupBy('to_account_id')
            ->pluck('amount', 'account_id');

        $balances = [];
        foreach ($accounts as $account) {
            $credit_movement = (float) ($sale_payment_credit[$account->id] ?? 0)
                + (float) ($return_purchase_credit[$account->id] ?? 0)
                + (float) ($income_credit[$account->id] ?? 0)
                + (float) ($transfer_credit[$account->id] ?? 0);

            $debit_movement = (float) ($purchase_payment_debit[$account->id] ?? 0)
                + (float) ($return_sale_debit[$account->id] ?? 0)
                + (float) ($expense_debit[$account->id] ?? 0)
                + (float) ($payroll_debit[$account->id] ?? 0)
                + (float) ($transfer_debit[$account->id] ?? 0);

            $net = (float) $account->initial_balance + $credit_movement - $debit_movement;
            $normalized = $this->normalize($account->name);

            if (! isset($balances[$normalized])) {
                $balances[$normalized] = ['debit' => 0.0, 'credit' => 0.0];
            }

            if ($net >= 0) {
                $balances[$normalized]['debit'] += $net;
            } else {
                $balances[$normalized]['credit'] += abs($net);
            }
        }

        return $balances;
    }

    /**
     * @return array<string, float>
     */
    protected function expense_category_balances(string $from_date, string $to_date): array
    {
        $rows = DB::table('expense_categories as ec')
            ->leftJoin('expenses as e', function ($join) use ($from_date, $to_date): void {
                $join->on('e.expense_category_id', '=', 'ec.id')
                    ->whereDate('e.created_at', '>=', $from_date)
                    ->whereDate('e.created_at', '<=', $to_date);
            })
            ->where('ec.is_active', 1)
            ->select('ec.name', DB::raw('COALESCE(SUM(e.amount), 0) as amount'))
            ->groupBy('ec.id', 'ec.name')
            ->orderBy('ec.name')
            ->get();

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$this->normalize($row->name)] = (float) $row->amount;
        }

        return $mapped;
    }

    protected function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['–', '—'], '-', $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    protected function title_from_normalized(string $normalized): string
    {
        return ucwords($normalized);
    }
}
