@extends('layout')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="col-md-12 text-center"><h1><a href="/?date={!! $date->copy()->subMonth()->toDateTimeString() !!}">&lt;</a> {!! $date->format('F Y') !!} <a href="/?date={!! $date->copy()->addMonth()->toDateTimeString() !!}">&gt;</a></h1></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="col-md-12 text-center"><h3>Team Stats</h3></div>
                <table class="table table-striped" id="userstats">
                    <thead>
                        <tr>
                            <th>Developer</th>
                            <th>Tickets Assigned</th>
                            <th>Tickets Failed CR</th>
                            <th>Tickets Failed Testing</th>
                            <th>Tickets Completed</th>
                            <th>CRs Finalized</th>
                            <th>Testing Finalized</th>
                            <th>Avg to complete ticket</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($monthlyUserStats as $row)
                        <tr>
                            <td><a href="/user/{!! $row->id !!}">{!! $row->full_name !!}</a></td>
                            <td>{!! $row->assigned !!}</td>
                            <td>{!! $row->failed_cr !!}</td>
                            <td>{!! $row->failed_testing !!}</td>
                            <td>{!! $row->completed !!}</td>
                            <td>{!! $row->crs_actioned !!}</td>
                            <td>{!! $row->testing_actioned !!}</td>
                            <td>{!! round($row->avg_completed_time, 2) !!} hours</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="row">&nbsp;</div>
        <div class="row">
            <div class="col-md-12">
                <div class="col-md-12 text-center"><h3>Ticket Stats</h3></div>
                <table class="table table-striped" id="ticket_stats">
                    <thead>
                    <tr>
                        <th>Task</th>
                        <th>Assigned To</th>
                        <th>PR</th>
                        <th>Status</th>
                        <th>Last Update</th>
                        <th>Last Update Raw</th>
                        <th># Failed CR</th>
                        <th># Failed Testing</th>
                        <th>Time on Ticket</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($monthlyTaskStats as $row)
                        <tr>
                            <td><a title="{!! $row->title !!}" href="https://sportsmed.atlassian.net/browse/{!! $row->key !!}" target="_blank">{!! $row->key !!}</a></td>
                            <td><a href="/user/{!! $row->user_id !!}">{!! $row->full_name !!}</a></td>
                            <td>@if (!empty($row->pr_link)) <a href="{!! $row->pr_link !!}" target="_blank">Github</a> @else - @endif </td>
                            <td>{!! $row->state !!}</td>
                            <td>{!! $row->updated_at->diffForHumans(null, false) !!}</td>
                            <td>{!! $row->updated_at->toDateTimeString() !!}</td>
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
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#userstats').DataTable({
                "search":   false
            });
            $('#ticket_stats').DataTable({
                "search":   false,
                "columnDefs": [
                    {
                        "targets": [ 5 ],
                        "visible": false
                    },
                    {
                        "orderData":[ 5 ],   "targets": [ 4 ]
                    }
                ]

            });
        } );
    </script>
@endsection
