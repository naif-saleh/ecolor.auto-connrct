@extends('layout.main')

@section('title', 'Auto Dailer | Report')
@section('content')
<div class="container">


    <!-- Page Header -->
    <div class="text-center mb-5">
        <h2 class="fw-bold text-primary">Auto Dailer - Numbers Not Called</h2>
        {{-- <p class="text-muted">View and manage detailed reports on call activity.</p> --}}
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




