{{-- resources/views/reports/inventory_asset_account/index.blade.php --}}
@extends('layout')
@section('title', 'Inventory Asset Account')

@section('content')
<div class="card mb-4">
  <h5 class="card-header">Inventory Asset Account — Details</h5>

  <div class="card-body">
    <form id="filters_form" class="row" action="#" method="GET">
      <div class="form-group col-md-3">
        <label>From Date</label>
        <input type="date" name="from_date" id="from_date" class="form-control"
               value="{{ request('from_date', now()->subDays(30)->toDateString()) }}">
      </div>
      <div class="form-group col-md-3">
        <label>To Date</label>
        <input type="date" name="to_date" id="to_date" class="form-control"
               value="{{ request('to_date', now()->toDateString()) }}">
      </div>
      <div class="form-group col-md-3">
        <label>Store (optional)</label>
        <input type="text" name="store_id" id="store_id" class="form-control"
               value="{{ request('store_id') }}">
      </div>
      <div class="form-group col-md-3">
        <label>Item Code (optional)</label>
        <input type="text" name="item_code" id="item_code" class="form-control"
               value="{{ request('item_code') }}">
      </div>

      <div class="col-12 d-flex mt-2">
        <button type="submit" class="btn btn-primary mr-2" id="btn_apply_filters">Apply Filters</button>
        <button type="button" class="btn btn-outline-secondary mr-2" id="btn_reset_filters">Reset</button>
        <a href="#" id="btn_export_excel" class="btn btn-success ml-auto">Export Excel</a>
      </div>
    </form>
  </div>

  <div class="table-responsive px-3 pb-3">
    <table class="table table-striped table-bordered" id="inventory_asset_table" style="width:100%">
      <thead>
        <tr>
          <th>Docu Type</th>
          <th>Date</th>
          <th>Document Number</th>
          <th>Tracking Num (If Any)</th>
          <th>Item Code</th>
          <th>Description</th>
          <th>Name</th>
          <th>Store Location</th>
          <th class="text-right">Qty</th>
          <th class="text-right">Debit (Inventory In) (Cost Price)</th>
          <th class="text-right">Credit (Inventory Out) (Cost Price)</th>
          <th class="text-right">Running Balance</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <th colspan="9" class="text-right">Totals:</th>
          <th class="text-right" id="total_debit">0</th>
          <th class="text-right" id="total_credit">0</th>
          <th class="text-right" id="closing_balance">0</th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function($){
  console.log('inventory_asset_account: script_loaded');

  var data_url   = "{{ route('reports.inventory_asset_account.data') }}";
  var export_url = "{{ route('reports.inventory_asset_account.export') }}";

  var $filters_form    = $('#filters_form');
  var $table_body      = $('#inventory_asset_table tbody');
  var $total_debit     = $('#total_debit');
  var $total_credit    = $('#total_credit');
  var $closing_balance = $('#closing_balance');

  function clean(val){ return (val || '').toString().replace(/#/g,'').trim(); }

  function build_query_string() {
    return $.param({
      from_date: clean($('#from_date').val()),
      to_date:   clean($('#to_date').val()),
      store_id:  clean($('#store_id').val()),
      item_code: clean($('#item_code').val())
    });
  }

  function load_rows() {
    var qs = build_query_string();
    console.log('inventory_asset_account: fetching', data_url + '?' + qs);

    $.ajax({
      url: data_url + '?' + qs,
      type: 'GET',
      dataType: 'json',
      beforeSend: function(){
        $table_body.html('<tr><td colspan="13">Loading…</td></tr>');
      },
      success: function(resp){
        var rows = resp.rows || [];
        var html = '';

        if (!rows.length) {
          html = '<tr><td colspan="13">No records found.</td></tr>';
        } else {
          $.each(rows, function(i, r){
            html += '<tr>' +
              '<td>' + (r.docu_type || '') + '</td>' +
              '<td>' + (r.date || '') + '</td>' +
              '<td>' + (r.document_number || '') + '</td>' +
              '<td>' + (r.tracking_num || '') + '</td>' +
              '<td>' + (r.item_code || '') + '</td>' +
              '<td>' + (r.description || '') + '</td>' +
              '<td>' + (r.name || '') + '</td>' +
              '<td>' + (r.store_location || '') + '</td>' +
              '<td class="text-right">' + Number(r.qty).toLocaleString() + '</td>' +
              '<td class="text-right">' + Number(r.debit).toFixed(2) + '</td>' +
              '<td class="text-right">' + Number(r.credit).toFixed(2) + '</td>' +
              '<td class="text-right">' + Number(r.running_balance).toFixed(2) + '</td>' +
              '<td>' + (r.notes || '') + '</td>' +
            '</tr>';
          });
        }

        $table_body.html(html);

        if (resp.totals) {
          $total_debit.text(Number(resp.totals.debit || 0).toFixed(2));
          $total_credit.text(Number(resp.totals.credit || 0).toFixed(2));
          $closing_balance.text(Number(resp.totals.closing_balance || 0).toFixed(2));
        }
      },
      error: function(xhr){
        console.error('inventory_asset_account: ajax_error', xhr.status, xhr.responseText);
        $table_body.html('<tr><td colspan="13" class="text-danger">Failed to load data.</td></tr>');
        $total_debit.text('0'); $total_credit.text('0'); $closing_balance.text('0');
      }
    });
  }

  // keep on same page; do AJAX instead of navigating
  $filters_form.on('submit', function(e){ e.preventDefault(); load_rows(); });

  $('#btn_reset_filters').on('click', function(){
    $('#from_date').val(''); $('#to_date').val(''); $('#store_id').val(''); $('#item_code').val('');
    load_rows();
  });

  $('#btn_export_excel').on('click', function(e){
    e.preventDefault();
    window.location.href = export_url + '?' + build_query_string();
  });

  // initial pull with prefilled values
  $(function(){ load_rows(); });

})(jQuery);
</script>
@endpush
