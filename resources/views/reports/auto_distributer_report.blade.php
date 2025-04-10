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

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <!-- Export Button -->
        <a href="{{ route('auto_distributer.report.export', [
                'filter' => $filter,
                'extension_from' => request('extension_from'),
                'extension_to' => request('extension_to'),
                'provider' => request('provider'),
                'date_from' => request('date_from'),
                'date_to' => request('date_to'),
                'time_from' => request('time_from'),
                'time_to' => request('time_to'),
            ]) }}" class="btn btn-success d-flex align-items-center">
            <i class="fas fa-file-export me-2"></i> Export as CSV
        </a>

        <!-- Filter Buttons -->
        <div class="btn-group flex-wrap">
            <a href="{{ url('auto-distributer-report?filter=all') }}"
                class="{{ $filter === 'all' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-list me-1"></i> All
            </a>
            <a href="{{ url('auto-distributer-report?filter=answered') }}"
                class="{{ $filter === 'answered' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-phone me-1"></i> Answered
            </a>
            <a href="{{ url('auto-distributer-report?filter=no answer') }}"
                class="{{ $filter === 'no answer' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-phone-slash me-1"></i> No Answer
            </a>
            <a href="{{ url('auto-distributer-report?filter=emplooyee no answer') }}"
                class="{{ $filter === 'emplooyee no answer' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-phone-slash me-1"></i> Em.No Answer
            </a>
            <a href="{{ url('auto-dailer-report?filter=queue no answer') }}"
                class="{{ $filter === 'queue no answer' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fa-solid fa-right-left me-1"></i>Queue Unanswered
            </a>
            <a href="{{ url('auto-distributer-report?filter=today') }}"
                class="{{ $filter === 'today' ? 'btn btn-primary' : 'btn btn-light' }}">
                <i class="fas fa-calendar-day me-1"></i> Today
            </a>
        </div>
    </div>
    <!-- Statistics Cards -->
    <div class="d-flex flex-wrap gap-3 mb-4">
        @if($filter === 'all' || $filter === 'today')
                @if (!empty($totalCount))
                <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                    <i class="fas fa-phone-volume text-primary fs-1 mb-2"></i>
                    <h6 class="fw-semibold">Total Calls</h6>
                    <p class="fw-bold fs-4">{{ $totalCount }}</p>
                </div>
                @endif

                @if (!empty($answeredCount))
                <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                    <i class="fas fa-phone text-success fs-1 mb-2"></i>
                    <h6 class="fw-semibold">Answered</h6>
                    <p class="fw-bold fs-4">{{ $answeredCount }}</p>
                </div>
                @endif

                @if (!empty($noAnswerCount))
                <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                    <i class="fas fa-phone-slash text-warning fs-1 mb-2"></i>
                    <h6 class="fw-semibold">Unanswered</h6>
                    <p class="fw-bold fs-4">{{ $noAnswerCount }}</p>
                </div>
                @endif


                @if (!empty($noAnswerQueueCount))
                <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                    <i class="fas fa-phone-slash text-primary fs-1 mb-2"></i>
                    <h6 class="fw-semibold">Unanswered Queue</h6>
                    <p class="fw-bold fs-4">{{ $noAnswerQueueCount }}</p>
                </div>
                @endif


                @if (!empty($todayEmployeeUnanswerCount))
                <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                    <i class="fas fa-phone-slash text-danger fs-1 mb-2"></i>
                    <h6 class="fw-semibold">Em-Unanswered</h6>
                    <p class="fw-bold fs-4">{{ $todayEmployeeUnanswerCount }}</p>
                </div>
                @endif





            @elseif($filter === 'answered')
            <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                <i class="fas fa-phone text-success fs-1 mb-2"></i>
                <h6 class="fw-semibold">Answered</h6>
                <p class="fw-bold fs-4">{{ $answeredCount }}</p>
            </div>

            @elseif($filter === 'no answer')
            <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                <i class="fas fa-phone-slash text-warning fs-1 mb-2"></i>
                <h6 class="fw-semibold">No Answer</h6>
                <p class="fw-bold fs-4">{{ $noAnswerCount }}</p>
            </div>

            @elseif($filter === 'queue no answer')
            <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                <i class="fas fa-phone-slash text-primary fs-1 mb-2"></i>
                <h6 class="fw-semibold">Unanswered Queue</h5>
                <p class="fw-bold fs-4">{{ $noAnswerQueueCount }}</p>
            </div>


            @elseif($filter === 'emplooyee no answer')
            <div class="card text-center p-4 shadow-sm border-0 rounded-3 flex-fill" style="min-width: 200px;">
                <i class="fas fa-phone-slash text-danger fs-1 mb-2"></i>
                <h6 class="fw-semibold">Em.No Answer</h6>
                <p class="fw-bold fs-4">{{ $todayEmployeeUnanswerCount }}</p>
            </div>
        @endif
    </div>




    <!-- Filters Form -->
    <form method="GET" action="{{ url('auto-distributer-report') }}">
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
                            <th><i class="fa-solid fa-phone m-1"></i>/<i class="fa-solid fa-phone-slash"></i> Status
                            </th>
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
                                    $statusMap = [
                                        'Talking' => 'Answered',
                                        'Routing' => 'Unanswered',
                                        'Dialing' => 'Unanswered',
                                        'Transferring' => 'Queue Unanswered',
                                        'Rerouting' => 'Queue Unanswered',
                                    ];

                                    $status = $statusMap[$report->status] ?? 'Employee Unanswered';

                                    $badgeClass = match ($status) {
                                        'Answered' => 'badge bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill',
                                        'Unanswered' => 'badge bg-warning-subtle border border-warning-subtle text-warning-emphasis rounded-pill',
                                        'Queue Unanswered' => 'badge bg-secondary-subtle border border-secondary-subtle text-secondary-emphasis rounded-pill',
                                        default => 'badge bg-danger-subtle border border-danger-subtle text-danger-emphasis rounded-pill',
                                    };
                                @endphp


                                <span class="{{ $badgeClass }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            <td>{{$report->duration_time ? $report->duration_time : '-'}}</td>
                            <td>{{$report->duration_routing ? $report->duration_routing : '-'}}</td>
                            <td>{{ $report->created_at->format('Y-m-d') }}</td> <!-- For Date -->
                            <td>{{ $report->created_at->format('H:i:s') }}</td> <!-- For Time -->

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
