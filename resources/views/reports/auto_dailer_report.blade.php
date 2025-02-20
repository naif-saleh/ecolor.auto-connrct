@extends('layout.main')

@section('title', 'Auto Dailer | Report')
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
            <h2 class="fw-bold text-primary">Auto Dailer Report</h2>
            {{-- <p class="text-muted">View and manage detailed reports on call activity.</p> --}}
        </div>

        <!-- Filters Section -->
        <div class="mb-4">
            <!-- First Line: Export and Filter Buttons -->
            <div class="filter-buttons">
                <!-- Export Button -->
                <a href="{{ route('auto_dailer.report.export', ['filter' => $filter, 'extension_from' => request('extension_from'), 'extension_to' => request('extension_to')]) }}"
                    class="btn btn-modern-export" id="download-autoDailer-csv-button">
                    <i class="fas fa-file-export me-2"></i> Export as CSV
                </a>

                <!-- State Filters (All, Answered, No Answer, Today) -->
                <a href="{{ url('auto-dailer-report') }}"
                    class="btn btn-modern-filter {{ !$filter || $filter === 'all' ? 'active' : '' }}" data-filter="all">
                    <i class="fas fa-list me-1"></i> All
                </a>
                <a href="{{ url('auto-dailer-report?filter=answered') }}"
                    class="btn btn-modern-filter {{ $filter === 'answered' ? 'active' : '' }}" data-filter="answered">
                    <i class="fas fa-phone me-1"></i> Answered
                </a>
                <a href="{{ url('auto-dailer-report?filter=no answer') }}"
                    class="btn btn-modern-filter {{ $filter === 'no answer' ? 'active' : '' }}" data-filter="no answer">
                    <i class="fas fa-phone-slash me-1"></i> No Answer
                </a>
                <a href="{{ url('auto-dailer-report?filter=today') }}"
                    class="btn btn-modern-filter {{ $filter === 'today' ? 'active' : '' }}" data-filter="today">
                    <i class="fas fa-calendar-day me-1"></i> Today
                </a>

            </div>

            <!-- Second Line: Filters Form -->
            <form method="GET" action="{{ url('auto-dailer-report') }}" class="filter-form">
                <!-- Keep current filter value -->
                <input type="hidden" name="filter" value="{{ $filter }}">

                <!-- Extension Inputs -->
                <input type="number" name="extension_from" class="form-modern" placeholder="Extension From"
                    value="{{ request('extension_from') }}">
                <input type="number" name="extension_to" class="form-modern" placeholder="Extension To"
                    value="{{ request('extension_to') }}">

                <!-- Provider Dropdown - Remove onchange="this.form.submit()" -->
                <select name="provider" class="form-modern">
                    <option value="">All Providers</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->name }}"
                            {{ request('provider') == $provider->name ? 'selected' : '' }}>
                            {{ $provider->name }} - {{$provider->extension}}
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

        {{-- @if ($filter !== 'today')
            <!-- Today's Statistics -->
            <div class="row mb-5 text-center justify-content-center">
                <div class="col-12">
                    <h6 class="text-muted mb-4">Today's Statistics</h6>
                </div>
                <div class="col-md-2 col-sm-3">
                    <div class="card shadow-sm border-0 text-center">
                        <div class="card-body">
                            <h5 class="text-primary fs-6">Today's Total</h5>
                            <h3 class="fw-bold fs-5">{{ $todayTotalCount }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-3">
                    <div class="card shadow-sm border-0 text-center">
                        <div class="card-body">
                            <h5 class="text-success fs-6">Today's Answered</h5>
                            <h3 class="fw-bold fs-5">{{ $todayAnsweredCount }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-3">
                    <div class="card shadow-sm border-0 text-center">
                        <div class="card-body">
                            <h5 class="text-warning fs-6">Today's No Answer</h5>
                            <h3 class="fw-bold fs-5">{{ $todayNoAnswerCount }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        @endif --}}
        <!-- Statistics Section -->
        <div class="row mb-5 text-center justify-content-center">
            <!-- Total Calls -->
            <div class="col-md-2 col-sm-3">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="bi bi-telephone-fill text-primary fs-4"></i>
                        </div>
                        <h5 class="text-primary fs-6">
                            <i class="fa-solid fa-phone-volume"></i>
                            Total Calls
                        </h5>
                        <h3 class="fw-bold fs-5">{{ $totalCount }}</h3>
                    </div>
                </div>
            </div>

            <!-- Answered Calls -->
            <div class="col-md-2 col-sm-3">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        </div>
                        <h5 class="text-success fs-6">
                            <i class="fa-solid fa-phone"></i>
                            Answered
                        </h5>
                        <h3 class="fw-bold fs-5">{{ $answeredCount }}</h3>
                    </div>
                </div>
            </div>

            <!-- No Answer Calls -->
            <div class="col-md-2 col-sm-3">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="bi bi-x-circle-fill text-warning fs-4"></i>
                        </div>
                        <h5 class="text-warning fs-6">
                            <i class="fa-solid fa-phone-slash"></i>
                            No Answer
                        </h5>
                        <h3 class="fw-bold fs-5">{{ $noAnswerCount }}</h3>
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
                                <th><i class="fa-solid fa-phone-volume"></i> Extension</th>
                                <th><i class="fa-solid fa-phone"></i>|<i class="fa-solid fa-phone-slash"></i> Status</th>
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
                                            $status = in_array($report->status, [
                                                'Wextension',
                                                'Wexternalline',
                                                'Talking',
                                            ])
                                                ? 'answered'
                                                : 'no answer';
                                            $badgeClass = match ($status) {
                                                'answered'
                                                    => 'badge bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill',
                                                'no answer'
                                                    => 'badge bg-danger-subtle border border-danger-subtle text-danger-emphasis rounded-pill',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $badgeClass }}">
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>
                                    <td>{{ $report->duration_time ? $report->duration_time : '-' }}</td>
                                    <td>{{ $report->duration_routing ? $report->duration_routing : '-' }}</td>
                                    <td>{{ $report->created_at->format('Y-m-d') }}</td> <!-- For Date -->
                                    <td>{{ $report->created_at->format('H:i:s') }}</td> <!-- For Time -->

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">No reports found for the given filter.</td>
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
        document.getElementById('download-autoDailer-csv-button').addEventListener('click', function(event) {
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
                    link.download = "Auto Dailer Report";
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

        document.addEventListener('DOMContentLoaded', function() {
            // Get all filter buttons
            const filterButtons = document.querySelectorAll('.filter-buttons a');

            filterButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Get current form values
                    const form = document.querySelector('.filter-form');
                    const formData = new FormData(form);

                    // Update the filter value
                    formData.set('filter', this.dataset.filter);

                    // Build the URL with all parameters
                    const params = new URLSearchParams(formData);
                    window.location.href =
                        `${this.href}${window.location.search ? '&' : '?'}${params.toString()}`;
                });
            });
        });
    </script>

@endsection
