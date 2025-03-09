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
    <!-- First Line: Export and Filter Buttons -->
    <!-- First Row: Export Button & Filters -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <!-- Export Button -->
        <a href="{{ route('auto_dailer.report.export', [
        'filter' => $filter,
        'extension_from' => request('extension_from'),
        'extension_to' => request('extension_to'),
        'provider' => request('provider'),
        'date_from' => request('date_from'),
        'date_to' => request('date_to'),
        'time_from' =>request('time_from'),
        'time_to' =>request('time_to')

    ]) }}" class="btn btn-success d-flex align-items-center">
            <i class="fas fa-file-export me-2"></i> Export as CSV
        </a>

        <!-- Filter Buttons -->
        <div class="btn-group flex-wrap">
            <a href="{{ url('auto-dailer-report?filter=all') }}"
                class="{{ $filter === 'all' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-list me-1"></i> All
            </a>
            <a href="{{ url('auto-dailer-report?filter=answered') }}"
                class="{{ $filter === 'answered' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-phone me-1"></i> Answered
            </a>
            <a href="{{ url('auto-dailer-report?filter=no answer') }}"
                class="{{ $filter === 'no answer' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-phone-slash me-1"></i> No Answer
            </a>
            <a href="{{ url('auto-dailer-report?filter=today') }}"
                class="{{ $filter === 'today' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-calendar-day me-1"></i> Today
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        @if($filter === 'all' || $filter === 'today')
        <div class="col-md-4">
            <div class="card text-center p-3 shadow-sm">
                <i class="fas fa-phone-volume text-primary fs-3"></i>
                <h5 class="mt-2">Total Calls</h5>
                <p class="fw-bold fs-4">{{ $totalCount }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3 shadow-sm">
                <i class="fas fa-phone text-success fs-3"></i>
                <h5 class="mt-2">Answered</h5>
                <p class="fw-bold fs-4">{{ $answeredCount }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3 shadow-sm">
                <i class="fas fa-phone-slash text-warning fs-3"></i>
                <h5 class="mt-2">No Answer</h5>
                <p class="fw-bold fs-4">{{ $noAnswerCount }}</p>
            </div>
        </div>
        @elseif($filter === 'answered')
        <div class="col-md-6">
            <div class="card text-center p-3 shadow-sm">
                <i class="fas fa-phone text-success fs-3"></i>
                <h5 class="mt-2">Answered Calls</h5>
                <p class="fw-bold fs-4">{{ $answeredCount }}</p>
            </div>
        </div>
        @elseif($filter === 'no answer')
        <div class="col-md-6">
            <div class="card text-center p-3 shadow-sm">
                <i class="fas fa-phone-slash text-warning fs-3"></i>
                <h5 class="mt-2">No Answer Calls</h5>
                <p class="fw-bold fs-4">{{ $noAnswerCount }}</p>
            </div>
        </div>
        @endif
    </div>


    <!-- Filters Form -->
    <form method="GET" action="{{ url('auto-dailer-report') }}">
        <input type="hidden" name="filter" value="{{ $filter }}">

        <div class="row g-3">
            <!-- Extension Filters -->
            <div class="col-md-3">
                <input type="number" name="extension_from" class="form-control" placeholder="Extension From"
                    value="{{ request('extension_from') }}">
            </div>
            <div class="col-md-3">
                <input type="number" name="extension_to" class="form-control" placeholder="Extension To"
                    value="{{ request('extension_to') }}">
            </div>

            <!-- Provider Dropdown -->
            <div class="col-md-3">
                <select name="provider" class="form-control">
                    <option value="">All Providers</option>
                    @foreach ($providers as $provider)
                    <option value="{{ $provider->name }}" {{ request('provider')==$provider->name ? 'selected' : '' }}>
                        {{ $provider->name }} - {{ $provider->extension }}
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Date Filters -->
            <div class="col-md-3">
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-3">
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>

            <!-- Time Filters -->
            <div class="col-md-3">
                <input type="time" name="time_from" class="form-control" id="time_from"
                    value="{{ request('time_from') }}">
            </div>
            <div class="col-md-3">
                <input type="time" name="time_to" class="form-control" id="time_to" value="{{ request('time_to') }}">
            </div>

            <!-- Apply Button -->
            <div class="col-md-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Apply
                </button>
            </div>
        </div>
    </form>


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
                                // : ($report->status === 'Dialing'
                                // ? 'falid call'
                                : 'no answer';

                                $badgeClass = match ($status) {
                                'answered'
                                => 'badge bg-success-subtle border border-success-subtle text-success-emphasis
                                rounded-pill',
                                'no answer'
                                => 'badge bg-warning-subtle border border-warning-subtle text-warning-emphasis
                                rounded-pill',
                                default
                                => 'badge bg-danger-subtle border border-danger-subtle text-danger-emphasis
                                rounded-pill',
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
