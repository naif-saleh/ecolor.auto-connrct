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
    </style>
@endsection
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
            <p class="text-muted">View and manage detailed reports on call activity.</p>
            {{-- <div class="text-md-start">
                <a href="{{ route('auto_dailer.report.export', ['filter' => $filter, 'extension_from' => request('extension_from'), 'extension_to' => request('extension_to')]) }}"
                    class="btn btn-soft-primary">
                    <i class="fas fa-file-export me-2"></i> Export as CSV
                </a>
            </div> --}}
        </div>

        <!-- Filters Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Export Button -->
            <div>
                <a href="{{ route('auto_dailer.report.export', ['filter' => $filter, 'extension_from' => request('extension_from'), 'extension_to' => request('extension_to')]) }} "
                    class="btn btn-modern-export" id="download-csv-button">
                    <i class="fas fa-file-export me-2"></i> Export as CSV
                </a>
            </div>

            <!-- Filters Form -->
            <form method="GET" action="{{ url('auto-dailer-report') }}" class="d-flex align-items-center gap-2 flex-wrap">
                <!-- State Filters -->
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
                                <th>State</th>
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
                                                'answered' => 'success',
                                                'no answer' => 'warning',
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
