@extends('layout.main')

@section('title', 'Auto Distributor | Not Called')
@section('content')
<div class="container">


    <!-- Page Header -->
    <div class="d-flex justify-content-between text-center mb-2">
        <h2 class="fw-bold text-primary">Auto Distributor - Numbers Not Called <u>{{$count}}</u></h2>
        <span>
            <a href="/auto-dailer-report" class="btn btn-dark">Back</a>
            <a href="{{ route('auto_distributer.report.notCalled.exportTodayCSV') }}" class="btn btn-primary">Export as
                CSV</a>
        </span>
    </div>

    <!--Not Called Numbers Table -->
    <div class="card shadow-sm border-0 rounded">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                    <thead class="bg-light-primary text-primary">
                        <tr>
                            <th><i class="fa-solid fa-hashtag"></i></th>
                            <th><i class="fa-solid fa-mobile"></i> Mobile</th>
                            <th><i class="fa-brands fa-nfc-directional"></i> Status</th>
                            <th><i class="fa-solid fa-upload"></i> Uploaded At</th>
                        </tr>
                    </thead>
                    @if (!$notCalled->isEmpty())
                    <tbody>
                        @foreach ($notCalled as $index => $report)
                        <tr>
                            <td>{{ $notCalled->firstItem() + $index }}</td>
                            <td>{{ $report->mobile }}</td>
                            <td>{{ $report->state }}</td>
                            <td>{{ $report->created_at }}</td>
                        </tr>

                        @endforeach
                    </tbody>

                    @else
                    <div class="alert alert-warning">Only To Day Not Called Numbers You Can Export !!</div>
                    @endif

                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {!! $notCalled->links('pagination::bootstrap-5') !!}
    </div>


</div>




@endsection