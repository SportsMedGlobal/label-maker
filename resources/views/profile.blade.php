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
            <canvas id="myChart" width="400" height="50"></canvas>
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

    <script>
        var ctx = document.getElementById("myChart").getContext("2d");
        var myChart = new Chart(ctx, {
            type: 'line',
            maintainAspectRatio: true,
            responsive: true,
            data: {
                labels: ["{!! implode('","', $graphStats['labels']) !!}"],
                datasets: [
                {
                    label: '# of Failed Code Reviews',
                    backgroundColor: "rgba(179,181,198,0.5)",
                    borderColor: "rgba(179,181,198,1)",
                    pointBackgroundColor: "rgba(179,181,198,1)",
                    pointBorderColor: "#fff",
                    pointHoverBackgroundColor: "#fff",
                    pointHoverBorderColor: "rgba(179,181,198,1)",
                    data: [{!! implode(',', $graphStats['crs_failed']) !!}]
                },
                {
                    label: '# of Failed Tests',
                    backgroundColor: "rgba(255,99,132,0.5)",
                    borderColor: "rgba(255,99,132,1)",
                    pointBackgroundColor: "rgba(255,99,132,1)",
                    pointBorderColor: "#fff",
                    pointHoverBackgroundColor: "#fff",
                    pointHoverBorderColor: "rgba(255,99,132,1)",
                    data: [{!! implode(',', $graphStats['testing_failed']) !!}]
                },
                {
                    label: '# of Completed Tasks',
                    fillColor: "rgba(70,191,189,0.2)",
                    strokeColor: "rgba(70,191,189,1)",
                    pointColor: "rgba(70,191,189,1)",
                    pointStrokeColor: "#fff",
                    pointHighlightFill: "#fff",
                    pointHighlightStroke: "rgba(70,191,189,0.8)",
                    data: [{!! implode(',', $graphStats['completed']) !!}]
                }
                ]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero:true
                        }
                    }]
                }
            }
        });
    </script>
@endsection