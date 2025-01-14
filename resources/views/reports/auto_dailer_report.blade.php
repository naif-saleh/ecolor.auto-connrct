@extends('layout.master')
@section('style')
    <style>
        /* Export Button */
        .btn-modern-export {
            background: linear-gradient(to right, #4caf50, #81c784);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s, box-shadow 0.3s;
        }

        .btn-modern-export:hover {
            background: linear-gradient(to right, #388e3c, #66bb6a);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        /* Filter Buttons */
        .btn-modern-filter {
            background: #f1f3f4;
            color: #555;
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-modern-filter:hover {
            background: #e0e0e0;
            border-color: #ccc;
        }

        .btn-modern-filter.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        /* Form Inputs and Dropdown */
        .form-modern {
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.9rem;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s;
        }

        .form-modern:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }

        /* Apply Button */
        .btn-modern-apply {
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s, box-shadow 0.3s;
        }

        .btn-modern-apply:hover {
            background: linear-gradient(to right, #0056b3, #003580);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .btn-soft-primary {
            background-color: #eaf4ff;
            color: #007bff;
            border: 1px solid #007bff;
            transition: all 0.3s ease;
        }

        .btn-soft-primary:hover {
            background-color: #007bff;
            color: #fff;
        }

        /* Centering the statistics */
        .row.mb-5.text-center.justify-content-center {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* Each card styling */
        .card {
            border-radius: 12px;
            overflow: hidden;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
        }

        .card-body {
            padding: 20px;
        }

        .card h5 {
            font-size: 1.2rem;
            font-weight: 500;
        }

        .card h3 {
            font-size: 2.5rem;
            font-weight: bold;
        }

        /* Specific color styling for the cards */
        .card .text-primary {
            color: #007bff;
        }

        .card .text-success {
            color: #28a745;
        }

        .card .text-warning {
            color: #ffc107;
        }

        /* Flexbox for the first row */
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Styling for the form buttons and filters */
        .filter-buttons .btn,
        .filter-form .form-modern,
        .filter-form button {
            margin: 5px;
        }

        /* Custom Pagination Styles */
        .pagination {
            margin-top: 10px;
            display: flex;
            justify-content: center;
            gap: 5px;
        }

        .pagination .page-item {
            margin: 0;
        }

        .pagination .page-link {
            border-radius: 50px;
            /* Rounded corners */
            border: 1px solid #dee2e6;
            /* Light border */
            padding: 8px 16px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .pagination .page-link:hover {
            background-color: #007bff;
            /* Blue background on hover */
            color: white;
        }

        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }

        .pagination .page-item.disabled .page-link {
            background-color: #f8f9fa;
            color: #6c757d;
        }
    </style>
@endsection
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
                <a href="{{ url('auto-dailer-report') }}" class="btn btn-modern-filter {{ !$filter ? 'active' : '' }}">
                    <i class="fas fa-list me-1"></i> All
                </a>
                <a href="{{ url('auto-dailer-report?filter=answered') }}"
                    class="btn btn-modern-filter {{ $filter === 'answered' ? 'active' : '' }}">
                    <i class="fas fa-phone me-1"></i> Answered
                </a>
                <a href="{{ url('auto-dailer-report?filter=no answer') }}"
                    class="btn btn-modern-filter {{ $filter === 'no answer' ? 'active' : '' }}">
                    <i class="fas fa-phone-slash me-1"></i> No Answer
                </a>
                <a href="{{ url('auto-dailer-report?filter=today') }}"
                    class="btn btn-modern-filter {{ $filter === 'today' ? 'active' : '' }}">
                    <i class="fas fa-calendar-day me-1"></i> Today
                </a>
            </div>

            <!-- Second Line: Filters Form -->
            <form method="GET" action="{{ url('auto-dailer-report') }}" class="filter-form">
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

        <!-- Statistics -->
        <div class="row mb-5 text-center justify-content-center">
            <!-- Total Calls -->
            <div class="col-md-3">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-body">
                        <h5 class="text-primary">Total Calls</h5>
                        <h3 class="fw-bold">{{ $totalCount }}</h3>
                    </div>
                </div>
            </div>

            <!-- Answered Calls -->
            <div class="col-md-3">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-body">
                        <h5 class="text-success">Answered</h5>
                        <h3 class="fw-bold">{{ $answeredCount }}</h3>
                    </div>
                </div>
            </div>

            <!-- No Answer Calls -->
            <div class="col-md-3">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-body">
                        <h5 class="text-warning">No Answer</h5>
                        <h3 class="fw-bold">{{ $noAnswerCount }}</h3>
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
                                <th>#</th>
                                <th>Mobile</th>
                                <th>Provider</th>
                                <th>Extension</th>
                                <th>Status</th>
                                <th>Called At - Day</th>
                                <th>Called At - Time</th>
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
                                    <td>{{ $report->created_at->addHours(3)->format('Y-m-d') }}</td> <!-- For Date -->
                                    <td>{{ $report->created_at->addHours(3)->format('H:i:s') }}</td> <!-- For Time -->

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
    </script>

@endsection
