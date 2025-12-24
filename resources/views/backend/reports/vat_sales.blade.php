@extends('backend.layout.main') {{-- adjust if your layout path differs --}}

@section('content')
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">VAT Sales Report</h5>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('reports.vat_sales') }}" id="vat_report_filter_form">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control"
                               value="{{ $start_date ?? '' }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control"
                               value="{{ $end_date ?? '' }}" required>
                    </div>

                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-filter me-1"></i> Filter
                        </button>

                        <a href="{{ route('reports.vat_sales') }}" class="btn btn-outline-secondary" id="btn_reset_filters">
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <hr class="my-4">

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle" id="vat_sales_table">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice No</th>
                            <th>Invoice Date</th>
                            <th>Customer/Market Place</th>
                            <th>Customer Location</th>
                            <th>Emirates/Country</th>
                            <th>Sales Return</th>
                            <th>Goods Description</th>
                            <th>Quantity</th>
                            <th>Unit Price (AED)</th>
                            <th>Sales Value (AED)</th>
                            <th>VAT Rate</th>
                            <th>VAT Amount</th>
                            <th>Total Amount (AED)</th>
                            <th>Platform Fees (AED)</th>
                            <th>Net Receivables (AED)</th>
                            <th>Payment Status</th>
                            <th>Shipping/Export Doc No</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row->invoice_no }}</td>
                                <td>{{ $row->invoice_date }}</td>
                                <td>{{ $row->customer_market_place }}</td>
                                <td>{{ $row->customer_location }}</td>
                                <td>{{ $row->emirates_country }}</td>

                                <td class="text-end">{{ number_format((float)$row->sales_return, 2) }}</td>

                                <td>{{ $row->goods_description }}</td>
                                <td class="text-end">{{ number_format((float)$row->quantity, 2) }}</td>

                                <td class="text-end">{{ number_format((float)$row->unit_price_aed, 2) }}</td>
                                <td class="text-end">{{ number_format((float)$row->sales_value_aed, 2) }}</td>

                                <td class="text-end">{{ number_format((float)$row->vat_rate, 2) }}%</td>
                                <td class="text-end">{{ number_format((float)$row->vat_amount, 2) }}</td>

                                <td class="text-end">{{ number_format((float)$row->total_amount_aed, 2) }}</td>
                                <td class="text-end">{{ number_format((float)$row->platform_fees_aed, 2) }}</td>
                                <td class="text-end">{{ number_format((float)$row->net_receivables_aed, 2) }}</td>

                                <td>
                                    @php
                                        $badge = 'secondary';
                                        if ($row->payment_status === 'Paid') $badge = 'success';
                                        elseif ($row->payment_status === 'Partial') $badge = 'warning';
                                        elseif ($row->payment_status === 'Due') $badge = 'danger';
                                        elseif ($row->payment_status === 'Pending') $badge = 'dark';
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">{{ $row->payment_status }}</span>
                                </td>

                                <td>{{ $row->shipping_export_doc_no }}</td>
                                <td style="max-width:260px; white-space:normal;">
                                    {{ $row->remarks }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="18" class="text-center text-muted py-4">
                                    No records found for selected dates.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $rows->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#btn_reset_filters').on('click', function (e) {
        // Let anchor navigate, just keeping this here if you later change to button
    });

    // Simple client-side check (server-side validation already exists)
    $('#vat_report_filter_form').on('submit', function (e) {
        var start_date = $('input[name="start_date"]').val();
        var end_date   = $('input[name="end_date"]').val();

        if (!start_date || !end_date) {
            e.preventDefault();
            alert('Please select both Start Date and End Date.');
            return false;
        }
        if (end_date < start_date) {
            e.preventDefault();
            alert('End Date must be greater than or equal to Start Date.');
            return false;
        }
    });
});
</script>
@endpush
