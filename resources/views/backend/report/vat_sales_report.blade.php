@extends('backend.layout.main')
@section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section class="forms">
    <div class="container-fluid">
        <div class="card mt-3">
            <div class="card-header mt-2">
                <h3 class="text-center mb-0">VAT Sales Report</h3>
            </div>
            <div class="card-body">
                <div class="row mt-2">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><strong>{{trans('file.Date')}}</strong></label>
                            <input type="text" class="vat-sales-daterangepicker-field form-control" id="vat_sales_date_display" value="" placeholder="{{trans('file.Choose Your Date')}}" autocomplete="off" />
                            <input type="hidden" name="from_date" id="vat_from_date" value="" />
                            <input type="hidden" name="to_date" id="vat_to_date" value="" />
                        </div>
                    </div>
                    <div class="col-md-4 mt-4">
                        <button type="button" class="btn btn-primary" id="btn_vat_generate_report">
                            <i class="fa fa-search"></i> Generate Report
                        </button>
                    </div>
                    <div class="col-md-4 mt-4 text-right">
                        <form method="post" action="{{ route('reports.vat_sales.export') }}" id="vat_sales_export_form" class="d-inline">
                            @csrf
                            <input type="hidden" name="from_date" id="export_from_date" value="" />
                            <input type="hidden" name="to_date" id="export_to_date" value="" />
                            <button type="submit" class="btn btn-info" id="btn_vat_export_excel" disabled>
                                <i class="fa fa-file-excel-o"></i> Export Excel
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid d-none" id="vat_report_results_wrap">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="vat_sales_table" class="table table-bordered table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>invoice_no</th>
                                <th>invoice_date</th>
                                <th>customer_market_place</th>
                                <th>customer_location</th>
                                <th>emirates_country</th>
                                <th>sales_return</th>
                                <th>goods_description</th>
                                <th>quantity</th>
                                <th>unit_price_aed</th>
                                <th>sales_value_aed</th>
                                <th>vat_rate</th>
                                <th>vat_amount</th>
                                <th>total_amount_aed</th>
                                <th>platform_fees_aed</th>
                                <th>net_receivables_aed</th>
                                <th>payment_status</th>
                                <th>shipping_export_doc_no</th>
                                <th>remarks</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid d-none" id="vat_report_empty_wrap">
        <div class="alert alert-info text-center">No data found for selected date range.</div>
    </div>
</section>
@endsection

@push('scripts')
<script type="text/javascript">
(function ($) {
    'use strict';

    var decimal_places = {{ (int) ($general_setting->decimal ?? 2) }};
    var vat_sales_datatable = null;
    var data_url = @json(route('reports.vat_sales.data'));

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    function format_number(val) {
        var n = parseFloat(val);
        if (isNaN(n)) {
            return '';
        }
        return n.toFixed(decimal_places);
    }

    function destroy_vat_table() {
        if (vat_sales_datatable !== null) {
            vat_sales_datatable.destroy();
            vat_sales_datatable = null;
            $('#vat_sales_table tbody').empty();
        }
    }

    $('#btn_vat_generate_report').on('click', function () {
        var from_date = $('#vat_from_date').val();
        var to_date = $('#vat_to_date').val();

        if (!from_date || !to_date) {
            alert('Please select a date range.');
            return;
        }
        if (to_date < from_date) {
            alert('End date must be greater than or equal to start date.');
            return;
        }

        $('#export_from_date').val(from_date);
        $('#export_to_date').val(to_date);
        $('#btn_vat_export_excel').prop('disabled', false);

        destroy_vat_table();
        $('#vat_report_results_wrap').addClass('d-none');
        $('#vat_report_empty_wrap').addClass('d-none');

        $.ajax({
            url: data_url,
            method: 'POST',
            dataType: 'json',
            data: {
                from_date: from_date,
                to_date: to_date
            },
            success: function (response) {
                var rows = response.rows || [];
                if (!rows.length) {
                    $('#vat_report_empty_wrap').removeClass('d-none');
                    return;
                }
                $('#vat_report_results_wrap').removeClass('d-none');

                vat_sales_datatable = $('#vat_sales_table').DataTable({
                    data: rows,
                    columns: [
                        { data: 'invoice_no' },
                        { data: 'invoice_date' },
                        { data: 'customer_market_place' },
                        { data: 'customer_location' },
                        { data: 'emirates_country' },
                        { data: 'sales_return' },
                        { data: 'goods_description' },
                        {
                            data: 'quantity',
                            render: function (data) {
                                return format_number(data);
                            }
                        },
                        {
                            data: 'unit_price_aed',
                            render: function (data) {
                                return format_number(data);
                            }
                        },
                        {
                            data: 'sales_value_aed',
                            render: function (data) {
                                return format_number(data);
                            }
                        },
                        {
                            data: 'vat_rate',
                            render: function (data) {
                                return format_number(data);
                            }
                        },
                        {
                            data: 'vat_amount',
                            render: function (data) {
                                return format_number(data);
                            }
                        },
                        {
                            data: 'total_amount_aed',
                            render: function (data) {
                                return format_number(data);
                            }
                        },
                        {
                            data: 'platform_fees_aed',
                            render: function (data) {
                                return format_number(data);
                            }
                        },
                        {
                            data: 'net_receivables_aed',
                            render: function (data) {
                                return format_number(data);
                            }
                        },
                        { data: 'payment_status' },
                        { data: 'shipping_export_doc_no' },
                        { data: 'remarks' }
                    ],
                    order: [[1, 'asc']],
                    pageLength: 50,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'copy',
                            text: '<i title="Copy" class="fa fa-copy"></i>',
                            exportOptions: { columns: ':visible' }
                        },
                        {
                            extend: 'csv',
                            text: '<i title="CSV" class="fa fa-file-text-o"></i>',
                            exportOptions: { columns: ':visible' }
                        },
                        {
                            extend: 'print',
                            text: '<i title="Print" class="fa fa-print"></i>',
                            exportOptions: { columns: ':visible' }
                        }
                    ]
                });
            },
            error: function (xhr) {
                var msg = 'Could not load report.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        var errs = xhr.responseJSON.errors;
                        var first_key = Object.keys(errs)[0];
                        if (first_key && errs[first_key] && errs[first_key][0]) {
                            msg = errs[first_key][0];
                        }
                    }
                }
                alert(msg);
            }
        });
    });

    $(document).ready(function () {
        // Same daterangepicker fork as layout (vendor/daterange): uses callback(), not apply.daterangepicker.
        $('.vat-sales-daterangepicker-field').daterangepicker({
            callback: function (startDate, endDate, period) {
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $('#vat_from_date').val(start_date);
                $('#vat_to_date').val(end_date);
            }
        });
    });
})(jQuery);

$("ul#report #vat-sales-report-menu").addClass("active");
</script>
@endpush
