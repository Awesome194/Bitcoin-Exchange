@extends('admin')

@section('body')
    <div class="app-title">
        <div>
            <h1><i class="fa fa-money"></i> Deposit Log</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
            <li class="breadcrumb-item"><a href="{{url()->current()}}"> Deposit Log</a></li>
        </ul>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <h3 class="tile-title ">{{$page_title}}
                    <a href="{{route('ipn.block.btc')}}" class="btn btn-success btn-md pull-right">
                        <i class="fa fa-list"></i> Run Block.io Confirmation Check
                    </a>
                </h3>
                <div class="tile-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover order-column" id="">
                            <thead>
                            <tr>
                                <th>Username</th>
                                <th>#TRX</th>
                                <th>Gateway</th>
                                <th>Total Amount</th>
                                <th>Amount</th>
                                <th>Discount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($deposits as $data)
                                <tr>
                                    <td>
                                        <a href="{{route('user.single', $data->user->id)}}">
                                            {{$data->user->username}}
                                        </a>
                                    </td>

                                    <td>{{$data->trx}}</td>
                                    <td>{{$data->gateway->name}}</td>
                                    <td><strong>{{$data->amount + $data->discount}} {{$basic->currency}}</strong></td>
                                    <td>{{$data->amount}} {{$basic->currency}}</td>
                                    <td>{{$data->discount}} {{$basic->currency}}</td>
                                    <td>
                                        @if($data->status == 1)
                                            <span class="badge badge-success"> 
                                                <i class="fa fa-check"></i> Completed </span>
                                            </span>
                                        @else
                                            <span  class="badge badge-warning">Pending </span>
                                        @endif
                                    </td>
                                    <td>
                                        {{$data->updated_at}}
                                    </td>
                                </tr>
                            @endforeach
                            <tbody>
                        </table>

                        {!! $deposits->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection
