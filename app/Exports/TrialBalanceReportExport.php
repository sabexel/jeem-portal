<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;

class TrialBalanceReportExport implements FromArray
{
    /**
     * @var Collection<int, array<string, mixed>>
     */
    protected $rows;

    /**
     * @var string
     */
    protected $from_date;

    /**
     * @var string
     */
    protected $to_date;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(Collection $rows, string $from_date, string $to_date)
    {
        $this->rows = $rows;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
    }

    public function array(): array
    {
        $data = [];
        $data[] = ['JEEM FZE Trial Balance as on '.$this->to_date];
        $data[] = [];
        $data[] = ['No', 'Particular', 'Debit', 'Credit'];

        foreach ($this->rows as $row) {
            $indent = str_repeat('  ', (int) $row['level']);
            $data[] = [
                $row['no'],
                $indent.$row['particular'],
                $row['debit'] ?: '',
                $row['credit'] ?: '',
            ];
        }

        return $data;
    }
}
