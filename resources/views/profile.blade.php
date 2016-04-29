@extends('layout')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="col-md-12 text-center"><h1>{!! $user->full_name !!} ({!! $user->username !!})</h1></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="col-md-12 text-center"><h3>Ticket Stats</h3></div>
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Task</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Last Update</th>
                        <th># Failed CR</th>
                        <th># Failed Testing</th>
                        <th>Time on Ticket</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($stats as $key=>$row)
                        @if ($key !== 'totals')
                            <tr>
                                <td><a title="{!! $row->title !!}" href="https://sportsmed.atlassian.net/browse/{!! $row->key !!}" target="_blank">{!! $row->key !!}</a></td>
                                <td>{!! $row->full_name !!}</td>
                                <td>{!! $row->state !!}</td>
                                <td>{!! $row->updated_at->diffForHumans(null, false) !!}</td>
                                <td>{!! $row->cr_failed !!}</td>
                                <td>{!! $row->testing_failed !!}</td>
                                <td>
                                    @if (empty($row->completed_at))
                                        {!! $row->created_at->diffForHumans(\Carbon\Carbon::now(), true) !!}
                                    @else
                                        {!! $row->created_at->diffForHumans($row->completed_at, true) !!}
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>{!! $stats['totals']['cr_failed'] !!}</td>
                            <td>{!! $stats['totals']['testing_failed'] !!}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection