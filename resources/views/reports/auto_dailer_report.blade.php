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
        <div class="text-center mb-5">
            <h2 class="fw-bold text-primary">Auto Dialer Report</h2>
            <p class="text-muted">View and manage detailed reports on call activity.</p>
            <!-- Export Button -->
            <div class="text-md-start">
                <a href="{{ route('auto_dailer.report.export', ['filter' => $filter, 'extension_from' => request('extension_from'), 'extension_to' => request('extension_to')]) }}"
                    class="btn btn-success">
                    <i class="fas fa-file-export"></i> Export as CSV
                </a>
            </div>
        </div>

        <!-- Filters and Export -->
        <div class="row mb-4 g-3 align-items-center">
            <!-- Filters -->
            <div class="col-md-12">
                <form method="GET" action="{{ url('auto-dailer-report') }}" class="row g-2 align-items-center">
                    <!-- State Filters -->
                    <div class="col-auto">
                        <a href="{{ url('auto-dailer-report') }}"
                            class="btn btn-outline-primary {{ !$filter ? 'active' : '' }}">
                            <i class="fas fa-list"></i> All
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="{{ url('auto-dailer-report?filter=answered') }}"
                            class="btn btn-outline-success {{ $filter === 'answered' ? 'active' : '' }}">
                            <i class="fas fa-phone"></i> Answered
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="{{ url('auto-dailer-report?filter=no answer') }}"
                            class="btn btn-outline-warning {{ $filter === 'no answer' ? 'active' : '' }}">
                            <i class="fas fa-phone-slash"></i> No Answer
                        </a>
                    </div>



                    <!-- Extension Range Inputs -->
                    <div class="col-auto">
                        <input type="number" name="extension_from" class="form-control" placeholder="Provider From"
                            value="{{ request('extension_from') }}">
                    </div>
                    <div class="col-auto">
                        <input type="number" name="extension_to" class="form-control" placeholder="Provider To"
                            value="{{ request('extension_to') }}">
                    </div>


                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </form>


            </div>

        </div>

        <!-- Statistics -->
        <div class="row mb-5 text-center">
            @foreach ([['Answered', 'text-success', $answeredCount], ['No Answer', 'text-warning', $noAnswerCount]] as $stat)
                <div class="col-md-3">
                    <div class="card shadow-sm border-0" style="background: linear-gradient(120deg, #f8f9fa, #e9ecef);">
                        <div class="card-body">
                            <h5 class="{{ $stat[1] }}">{{ $stat[0] }}</h5>
                            <h3 class="fw-bold">{{ $stat[2] }}</h3>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Report Table -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>#</th>
                                <th>Mobile</th>
                                <th>Provider</th>
                                <th>State</th>
                                <th>Called At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reports as $index => $report)
                                <tr>
                                    <td>{{ $reports->firstItem() + $index }}</td>
                                    <td>{{ $report->phone_number }}</td>
                                    <td>{{ $report->provider }}</td>

                                    <td>
                                        @php

                                            $status = ($report->status == "Wextension") ? "answered" : "no answer";
                                        @endphp

                                        <span
                                            class="badge bg-{{ match ($status) {
                                                'answered' => 'success',
                                                'no answer' => 'warning',
                                                default => 'secondary',
                                            } }}">
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>
                                    <td>{{ $report->created_at }}</td>

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
                    {{ $reports->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection
