@extends('backend.layout.main')
@section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif


<section>
    <div class="container-fluid"> 
        hello world
    </div>
    <div class="table-responsive">
  <table id="sale-table" class="table sale-list" style="width: 100%">
    <thead>
      <tr>
        <th class="not-exported"></th>
        <th>Docu Type</th>
        <th>Date</th>
        <th>Document No</th>
        <th>Tracking (if any)</th>
        <th>Item Code</th>
        <th>Description</th>
        <th>Name</th>
        <th>Status</th>
        <th>Store Location</th>
        <th>Qty</th>
        <th>Debit (Cost Price)</th>
        <th>Credit (Cost Price)</th>
        <th>Running Balance</th>
        <th>Notes</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($all_records as $row)
        <tr>
          <td></td>
          <td>{{ $row['docu_type'] }}</td>
          <td>{{ \Carbon\Carbon::parse($row['date'])->format('d-M-Y') }}</td>
          <td>{{ $row['document_no'] }}</td>
          <td>{{ $row['tracking_no'] }}</td>
          <td>{{ $row['item_code'] }}</td>
          <td>{{ $row['description'] }}</td>
          <td>{{ $row['name'] }}</td>
          <td>{{ $row['status'] }}</td>
          <td>{{ $row['store_location'] }}</td>
          <td>{{ $row['qty'] }}</td>
          <td>{{ $row['debit_cost'] }}</td>
          <td>{{ $row['credit_cost'] }}</td>
          <td>{{ $row['running_balance'] }}</td>
          <td>{{ $row['notes'] }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot class="tfoot active">
      <th colspan="10">Total</th>
      <th>{{ $all_records->sum('qty') }}</th>
      <th>{{ $all_records->sum('debit_cost') }}</th>
      <th>{{ $all_records->sum('credit_cost') }}</th>
      <th>{{ optional($all_records->first())['running_balance'] ?? 0 }}</th>
      <th>Notes</th>
    </tfoot>
  </table>
</div>

    {{-- <div class="table-responsive">
        <table id="sale-table" class="table sale-list" style="width: 100%">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>Docu Type</th>
                    <th>Date</th>
                    <th>Document No</th>
                    <th>Tracking (if any)</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Store Location</th>
                    <th>Qty</th>
                    <th>Debit (Cost Price)</th>
                    <th>Credit (Cost Price)</th>
                    <th>Running Balance</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($all_records as $record)
                    <tr>
                        <th class="not-exported"></th>
                        <td>{{ $record->type }}</td>
                        <td>{{ $record->created_at }}</td>
                        <td>{{ $record->reference_no }}</td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot class="tfoot active">
                <th colspan="10">Total</th>
                <th>2</th>
                <th>5940</th>
                <th>2500</th>
                <th>3440</th>
                <th>Notes</th>
            </tfoot>
        </table>
    </div> --}}
</section>
@endsection