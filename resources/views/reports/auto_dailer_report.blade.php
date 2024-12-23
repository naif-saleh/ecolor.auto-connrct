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
                    <div class="col-auto">
                        <a href="{{ url('auto-dailer-report?filter=called') }}"
                            class="btn btn-outline-info {{ $filter === 'called' ? 'active' : '' }}">
                            <i class="fas fa-phone-volume"></i> Called
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="{{ url('auto-dailer-report?filter=declined') }}"
                            class="btn btn-outline-danger {{ $filter === 'declined' ? 'active' : '' }}">
                            <i class="fas fa-times-circle"></i> Declined
                        </a>
                    </div>


                    <!-- Extension Range Inputs -->
                    <div class="col-auto">
                        <input type="number" name="extension_from" class="form-control" placeholder="From Ext"
                            value="{{ request('extension_from') }}">
                    </div>
                    <div class="col-auto">
                        <input type="number" name="extension_to" class="form-control" placeholder="To Ext"
                            value="{{ request('extension_to') }}">
                    </div>

                    <div class="col-auto">
                        <select name="provider" class="form-select">
                            <option value="">All Providers</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider }}"
                                    {{ request('provider') == $provider ? 'selected' : '' }}>
                                    {{ ucfirst($provider) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </form>

                <!-- Export Button -->
                <div class="text-md-end">
                    <a href="{{ route('auto_dailer.report.export', ['filter' => $filter, 'extension_from' => request('extension_from'), 'extension_to' => request('extension_to')]) }}"
                        class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export as CSV
                    </a>
                </div>
            </div>

        </div>

        <!-- Statistics -->
        <div class="row mb-5 text-center">
            @foreach ([['Answered', 'text-success', $answeredCount], ['No Answer', 'text-warning', $noAnswerCount], ['Called', 'text-info', $calledCount], ['Declined', 'text-danger', $declinedCount]] as $stat)
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
                                        <span
                                            class="badge bg-{{ match ($report->state) {
                                                'answered' => 'success',
                                                'no answer' => 'warning',
                                                'called' => 'info',
                                                'declined' => 'danger',
                                                default => 'secondary',
                                            } }}">
                                            {{ ucfirst($report->state) }}
                                        </span>
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
                    {{ $reports->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection
