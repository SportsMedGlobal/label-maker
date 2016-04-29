<!DOCTYPE html>
<html>
<head>
    <title>SM Developer Stats</title>
    <script src="https://code.jquery.com/jquery-2.2.3.min.js"></script>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="col-md-12 text-center"><h1><a href="/?date={!! $date->copy()->subMonth()->toDateTimeString() !!}">&lt;</a> {!! $date->format('F Y') !!} <a href="/?date={!! $date->copy()->addMonth()->toDateTimeString() !!}">&gt;</a></h1></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="col-md-12 text-center"><h3>Team Stats</h3></div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Developer</th>
                            <th>Tickets Assigned</th>
                            <th>Tickets Failed CR</th>
                            <th>Tickets Failed Testing</th>
                            <th>Tickets Completed</th>
                            <th>CRs Finalized</th>
                            <th>Testing Finalized</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($monthlyUserStats as $row)
                        <tr>
                            <td>{!! $row->full_name !!}</td>
                            <td>{!! $row->assigned !!}</td>
                            <td>{!! $row->failed_cr !!}</td>
                            <td>{!! $row->failed_testing !!}</td>
                            <td>{!! $row->completed !!}</td>
                            <td>{!! $row->crs_actioned !!}</td>
                            <td>{!! $row->testing_actioned !!}</td>
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
                    @foreach ($monthlyTaskStats as $row)
                        <tr>
                            <td><a href="https://sportsmed.atlassian.net/browse/{!! $row->key !!}" target="_blank">{!! $row->key !!}</a></td>
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
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
