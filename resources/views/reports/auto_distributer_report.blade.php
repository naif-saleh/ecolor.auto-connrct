@extends('layout.main')

@section('title', 'Auto Distributor | Report')
@section('content')
    <div class="container">
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
            <h2 class="fw-bold text-primary">Auto Distributer Report</h2>
            {{-- <p class="text-muted">View and manage detailed reports on call activity.</p> --}}
        </div>

        <!-- Filters Section -->
        <div class="mb-4">
            <!-- First Line: Export and Filter Buttons -->
            <div class="filter-buttons">
                <!-- Export Button -->
                <a href="{{ route('auto_distributer.report.export', ['filter' => $filter, 'extension_from' => request('extension_from'), 'extension_to' => request('extension_to')]) }}"
                    class="btn btn-modern-export" id="download-autoDistributer-csv-button">
                    <i class="fas fa-file-export me-2"></i> Export as CSV
                </a>


                <!-- State Filters (All, Answered, No Answer, Today) -->
                <a href="{{ url('auto-distributer-report?filter=all') }}" class="btn btn-modern-filter {{ $filter === 'all' ? 'active' : '' }}">
                    <i class="fas fa-list me-1"></i> All
                </a>

                <a href="{{ url('auto-distributer-report?filter=answered') }}"
                    class="btn btn-modern-filter {{ $filter === 'answered' ? 'active' : '' }}">
                    <i class="fas fa-phone me-1"></i> Answered
                </a>
                <a href="{{ url('auto-distributer-report?filter=no answer') }}"
                    class="btn btn-modern-filter {{ $filter === 'no answer' ? 'active' : '' }}">
                    <i class="fas fa-phone-slash me-1"></i> No Answer
                </a>
                <a href="{{ url('auto-distributer-report?filter=employee_unanswer') }}"
                    class="btn btn-modern-filter {{ $filter === 'employee_unanswer' ? 'active' : '' }}">
                    <i class="fas fa-user-times me-1"></i> Employee Unanswer
                </a>
                <a href="{{ url('auto-distributer-report?filter=today') }}"
                    class="btn btn-modern-filter {{ $filter === 'today' ? 'active' : '' }}">
                    <i class="fas fa-calendar-day me-1"></i> Today
                </a>

            </div>

            <!-- Second Line: Filters Form -->
            <form method="GET" action="{{ url('auto-distributer-report') }}" class="filter-form">
                <!-- Extension Inputs -->
                <input type="number" name="extension_from" class="form-modern" placeholder="Extension From"
                    value="{{ request('extension_from') }}">
                <input type="number" name="extension_to" class="form-modern" placeholder="Extension To"
                    value="{{ request('extension_to') }}">

                <!-- Provider Dropdown -->
                <select name="provider" class="form-modern" onchange="this.form.submit()">
                    <option value="">All Providers</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->provider }}"
                            {{ request('provider') == $provider->provider ? 'selected' : '' }}>
                            {{ $provider->provider }}
                        </option>
                    @endforeach
                </select>

                <!-- Date Filters -->
                <input type="date" name="date_from" class="form-modern" placeholder="From Date"
                    value="{{ request('date_from') }}">
                <input type="date" name="date_to" class="form-modern" placeholder="To Date"
                    value="{{ request('date_to') }}">

                <!-- Apply Button -->
                <button type="submit" class="btn btn-modern-apply">
                    <i class="fas fa-filter me-2"></i> Apply
                </button>
            </form>
        </div>

        <!-- Statistics section -->
        <div class="row mb-5 text-center justify-content-center">
            <div class="col-md-2 col-sm-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="text-center text-primary fs-6"><i class="fa-solid fa-phone-volume"></i> Total Calls</h5>
                        <h3 class="fw-bold fs-5 text-center">{{ $filter === 'today' ? $todayTotalCount : $totalCount }}
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="text-center text-success fs-6"><i class="fa-solid fa-phone m-1"></i> Answered</h5>
                        <h3 class="fw-bold fs-5 text-center">
                            {{ $filter === 'today' ? $todayAnsweredCount : $answeredCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="text-center text-warning fs-6"><i class="fa-solid fa-phone-slash"></i> No Answer</h5>
                        <h3 class="fw-bold fs-5 text-center">
                            {{ $filter === 'today' ? $todayNoAnswerCount : $noAnswerCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="text-center text-danger fs-6"><i class="fa-solid fa-user-xmark"></i> Unanswered</h5>
                        <h3 class="fw-bold fs-5 text-center">
                            {{ $filter === 'today' ? $todayEmployeeUnanswerCount : $employeeUnanswerCount }}</h3>
                    </div>
                </div>
            </div>
        </div>








        <!-- Report Table -->
        <div class="card shadow-sm border-0 rounded">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center">
                        <thead class="bg-light-primary text-primary">
                            <tr>
                                <th><i class="fa-solid fa-hashtag"></i></th>
                                <th><i class="fa-solid fa-mobile"></i> Mobile</th>
                                <th><i class="fa-brands fa-nfc-directional"></i> Provider</th>
                                <th><i class="fa-solid fa-phone-volume"></i>  Extension</th>
                                <th><i class="fa-solid fa-phone m-1"></i>/<i class="fa-solid fa-phone-slash"></i> Status</th>
                                <th><i class="fa-solid fa-circle-radiation"></i> Talking</th>
                                <th><i class="fa-solid fa-circle-radiation"></i> Ringing</th>
                                <th><i class="fa-solid fa-calendar-days"></i> Called At - Date</th>
                                <th><i class="fa-solid fa-clock"></i> Called At - Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reports as $index => $report)
                                <tr>
                                    <td>{{ $reports->firstItem() + $index }}</td>
                                    <td>{{ $report->phone_number }}</td>
                                    <td>{{ $report->provider }}</td>
                                    <td>{{ $report->extension }}</td>
                                    <td>
                                        @php
                                            $status = '';
                                            if ($report->status === 'Talking') {
                                                $status = 'answered';
                                            } elseif ($report->status === 'Routing' || $report->status === 'Dialing') {
                                                $status = 'no answer';
                                            } else {
                                                $status = 'Employee not answer';
                                            }

                                            $badgeClass = match ($status) {
                                                'answered'
                                                    => 'badge bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill',
                                                'no answer'
                                                    => 'badge bg-warning-subtle border border-warning-subtle text-warning-emphasis rounded-pill',
                                                default
                                                    => 'badge bg-danger-subtle border border-danger-subtle text-danger-emphasis rounded-pill',
                                            };
                                        @endphp


                                        <span class="badge bg-{{ $badgeClass }}">
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>

                                    <td>{{$report->duration_time ? $report->duration_time : '-'}}</td>
                                    <td>{{$report->duration_routing ? $report->duration_routing : '-'}}</td>
                                    <td>{{ $report->created_at->addHours(3)->format('Y-m-d') }}</td> <!-- For Date -->
                                    <td>{{ $report->created_at->addHours(3)->format('H:i:s') }}</td> <!-- For Time -->

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">No reports found for the given filter</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center">
            {!! $reports->appends(request()->except('page'))->links('pagination::bootstrap-5') !!}
        </div>

    </div>
@endsection

@section('scripts')

    <script>
        document.getElementById('download-autoDistributer-csv-button').addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default action to manage it manually

            const url = this.href;

            Swal.fire({
                title: 'Preparing your file...',
                text: 'Please wait while we generate your CSV.',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Simulate file download process
            fetch(url)
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    } else {
                        throw new Error('Failed to download file');
                    }
                })
                .then(blob => {
                    const link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);
                    link.download = "Auto Distributor Report";
                    link.click();

                    Swal.fire({
                        title: 'Download Ready!',
                        text: 'Your CSV file has been successfully downloaded.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while preparing your file. Please try again later.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        });
    </script>

@endsection
