@extends('layout.master')

@section('content')
    <div class="container my-4">
        <!-- Success Alert -->
        @if (session('success'))
            <script>
                Swal.fire({
                    title: 'Success!',
                    text: "{{ session('success') }}",
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
            </script>
        @endif

        <!-- Page Header -->
        <div class="text-center mb-4">
            <h2 class="fw-bold">Auto Dialer Report</h2>
            <p class="text-muted">View and manage detailed reports on call activity.</p>
        </div>

        <!-- Filters and Export -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Filters -->
            <div>
                <a href="{{ url('auto-dailer-report') }}"
                    class="btn btn-outline-primary me-2 {{ !$filter ? 'active' : '' }}">
                    All
                </a>
                <a href="{{ url('auto-dailer-report?filter=answered') }}"
                    class="btn btn-outline-success me-2 {{ $filter === 'answered' ? 'active' : '' }}">
                    Answered
                </a>
                <a href="{{ url('auto-dailer-report?filter=no answer') }}"
                    class="btn btn-outline-warning me-2 {{ $filter === 'no answer' ? 'active' : '' }}">
                    No Answer
                </a>
                <a href="{{ url('auto-dailer-report?filter=called') }}"
                    class="btn btn-outline-info me-2 {{ $filter === 'called' ? 'active' : '' }}">
                    Called
                </a>
                <a href="{{ url('auto-dailer-report?filter=declined') }}"
                    class="btn btn-outline-danger {{ $filter === 'declined' ? 'active' : '' }}">
                    Declined
                </a>
            </div>

            <!-- Export Button -->
            <div>
                <a href="{{ route('auto_dailer.report.export', ['filter' => $filter]) }}" class="btn btn-success" id="download-csv-button">
                    <i class="fas fa-file-export"></i> Export as CSV
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4 text-center">
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="text-success">Answered</h5>
                        <h3 class="fw-bold">{{ $answeredCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="text-warning">No Answer</h5>
                        <h3 class="fw-bold">{{ $noAnswerCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="text-info">Called</h5>
                        <h3 class="fw-bold">{{ $calledCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="text-danger">Declined</h5>
                        <h3 class="fw-bold">{{ $declinedCount }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>#</th>
                                <th>Mobile</th>
                                <th>Provider</th>
                                <th>Extension</th>
                                <th>State</th>
                                <th>Called At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reports as $index => $report)
                                <tr>
                                    <td>{{ $reports->firstItem() + $index }}</td>
                                    <td>{{ $report->mobile }}</td>
                                    <td>{{ $report->provider }}</td>
                                    <td>{{ $report->extension }}</td>
                                    <td>
                                        @if ($report->state === 'answered')
                                            <span class="badge bg-success">Answered</span>
                                        @elseif ($report->state === 'no answer')
                                            <span class="badge bg-warning text-dark">No Answer</span>
                                        @elseif ($report->state === 'called')
                                            <span class="badge bg-info">Called</span>
                                        @elseif ($report->state === 'declined')
                                            <span class="badge bg-danger">Declined</span>
                                        @else
                                            <span class="badge bg-secondary">Unknown</span>
                                        @endif
                                    </td>
                                    <td>{{ $report->called_at }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-3">
                    {{ $reports->appends(['filter' => $filter])->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection
