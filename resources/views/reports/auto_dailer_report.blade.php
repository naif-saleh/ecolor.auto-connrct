@extends('layout.master')

@section('content')
    <div class="container report-container">
        <div class="row">
            <!-- Heading and Stats -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header text-center">
                        <h3>Auto Dailer Report</h3>
                    </div>
                    <div class="card-body">

                        <!-- Filter and Export Section -->
                        <div class="d-flex justify-content-between mb-4">
                            <!-- Filter Buttons -->
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
                                   class="btn btn-outline-danger {{ $filter === 'no answer' ? 'active' : '' }}">
                                    No Answer
                                </a>
                                <a href="{{ url('auto-dailer-report?filter=called') }}"
                                   class="btn btn-outline-info {{ $filter === 'called' ? 'active' : '' }}">
                                    Called
                                </a>
                            </div>

                            <!-- Export Button -->
                            <div>
                                <a href="{{ route('auto_dailer.report.export', ['filter' => $filter]) }}"
                                   class="btn btn-success">
                                    Export as CSV
                                </a>
                            </div>
                        </div>

                        <!-- Stats Section -->
                        <div class="row stats-container mb-4">
                            <div class="col-md-4">
                                <div class="alert alert-success text-center">
                                    <h5>Answered</h5>
                                    <p class="fw-bold fs-3">{{ $answeredCount }}</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-danger text-center">
                                    <h5>No Answer</h5>
                                    <p class="fw-bold fs-3">{{ $noAnswerCount }}</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-info text-center">
                                    <h5>Called</h5>
                                    <p class="fw-bold fs-3">{{ $calledCount }}</p> <!-- Display "called" count -->
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-primary">
                                    <tr>
                                        <th>#</th>
                                        <th>Mobile</th>
                                        <th>Provider</th>
                                        <th>Extension</th>
                                        <th>State</th>
                                        <th>Called At</th>
                                        <th>Declined At</th>
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
                                                @if ($report->state === 'True')
                                                    <span class="badge bg-success">answred</span>
                                                @else
                                                    <span class="badge bg-danger">no answer</span>
                                                @endif
                                            </td>
                                            <td>{{ $report->called_at }}</td>
                                            <td>{{ $report->declined_at }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No records found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            {{ $reports->appends(['filter' => $filter])->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

