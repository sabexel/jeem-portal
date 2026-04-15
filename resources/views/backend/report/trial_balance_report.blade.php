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
                <h3 class="text-center mb-0">JEEM FZE Trial Balance</h3>
            </div>
            <div class="card-body">
                <div class="row mt-2">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><strong>{{trans('file.Date')}}</strong></label>
                            <input type="text" class="trial-balance-daterangepicker-field form-control" id="trial_balance_date_display" value="" placeholder="{{trans('file.Choose Your Date')}}" autocomplete="off" />
                            <input type="hidden" name="from_date" id="trial_balance_from_date" value="" />
                            <input type="hidden" name="to_date" id="trial_balance_to_date" value="" />
                        </div>
                    </div>
                    <div class="col-md-4 mt-4">
                        <button type="button" class="btn btn-primary" id="btn_trial_balance_generate_report">
                            <i class="fa fa-search"></i> Generate Report
                        </button>
                    </div>
                    <div class="col-md-4 mt-4 text-right">
                        <form method="post" action="{{ route('reports.trial_balance.export') }}" id="trial_balance_export_form" class="d-inline">
                            @csrf
                            <input type="hidden" name="from_date" id="trial_balance_export_from_date" value="" />
                            <input type="hidden" name="to_date" id="trial_balance_export_to_date" value="" />
                            <button type="submit" class="btn btn-info" id="btn_trial_balance_export_excel" disabled>
                                <i class="fa fa-file-excel-o"></i> Export Excel
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid d-none" id="trial_balance_report_results_wrap">
        <div class="card">
            <div class="card-body">
                <h5 id="trial_balance_as_on_text" class="mb-3"></h5>
                <div class="table-responsive">
                    <table id="trial_balance_table" class="table table-bordered table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width: 90px;">No</th>
                                <th>Particular</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid d-none" id="trial_balance_report_empty_wrap">
        <div class="alert alert-info text-center">No data found for selected date range.</div>
    </div>
</section>
@endsection

@push('scripts')
<script type="text/javascript">
(function ($) {
    'use strict';

    var decimal_places = {{ (int) ($general_setting->decimal ?? 2) }};
    var data_url = @json(route('reports.trial_balance.data'));

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    function format_number(val) {
        var n = parseFloat(val);
        if (isNaN(n) || n === 0) {
            return '';
        }
        return n.toFixed(decimal_places);
    }

    function render_table_rows(rows) {
        var html = '';
        $.each(rows, function (idx, row) {
            var padding_left = 10 + (row.level * 25);
            var row_class = row.is_header ? 'font-weight-bold bg-light' : '';

            html += '<tr class="' + row_class + '">';
            html += '<td>' + (row.no || '') + '</td>';
            html += '<td style="padding-left:' + padding_left + 'px;">' + (row.particular || '') + '</td>';
            html += '<td class="text-right">' + format_number(row.debit) + '</td>';
            html += '<td class="text-right">' + format_number(row.credit) + '</td>';
            html += '</tr>';
        });
        $('#trial_balance_table tbody').html(html);
    }

    $('#btn_trial_balance_generate_report').on('click', function () {
        var from_date = $('#trial_balance_from_date').val();
        var to_date = $('#trial_balance_to_date').val();

        if (!from_date || !to_date) {
            alert('Please select a date range.');
            return;
        }
        if (to_date < from_date) {
            alert('End date must be greater than or equal to start date.');
            return;
        }

        $('#trial_balance_export_from_date').val(from_date);
        $('#trial_balance_export_to_date').val(to_date);
        $('#btn_trial_balance_export_excel').prop('disabled', false);

        $('#trial_balance_report_results_wrap').addClass('d-none');
        $('#trial_balance_report_empty_wrap').addClass('d-none');

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
                    $('#trial_balance_report_empty_wrap').removeClass('d-none');
                    return;
                }

                render_table_rows(rows);
                $('#trial_balance_as_on_text').text('JEEM FZE Trial Balance as on ' + to_date);
                $('#trial_balance_report_results_wrap').removeClass('d-none');
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
        $('.trial-balance-daterangepicker-field').daterangepicker({
            callback: function (startDate, endDate, period) {
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $('#trial_balance_from_date').val(start_date);
                $('#trial_balance_to_date').val(end_date);
            }
        });
    });
})(jQuery);

$("ul#report #trial-balance-report-menu").addClass("active");
</script>
@endpush
