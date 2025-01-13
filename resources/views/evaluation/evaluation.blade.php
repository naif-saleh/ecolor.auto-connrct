@extends('layout.master')
@section('style')
    <style>
        /* General Styles */
        body {
            font-family: 'Arial', sans-serif;
        }

        .text-primary {
            color: #0056b3;
        }

        .text-muted {
            color: #6c757d;
        }

        .text-center {
            text-align: center;
        }

        /* Export Button */
        .btn-modern-export {
            background: linear-gradient(to right, #4caf50, #81c784);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-modern-export:hover {
            background: linear-gradient(to right, #388e3c, #66bb6a);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Filter Buttons */
        .btn-modern-filter {
            background: #f1f3f4;
            color: #555;
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
            border-radius: 50px;
            padding: 8px 16px;
            font-size: 0.9rem;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s;
            width: 100%;
            max-width: 200px;
            margin: 5px;
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
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s, box-shadow 0.3s;
        }

        .btn-modern-apply:hover {
            background: linear-gradient(to right, #0056b3, #003580);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
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

        /* Card Styling */
        .card {
            border-radius: 16px;
            overflow: hidden;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 20px;
        }

        .card h5 {
            font-size: 1.2rem;
            font-weight: 500;
            color: #6c757d;
        }

        .card h3 {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
        }

        /* Statistics Cards */
        .card .text-primary {
            color: #007bff;
        }

        .card .text-success {
            color: #28a745;
        }

        .card .text-warning {
            color: #ffc107;
        }

        /* Flexbox for Filters */
        .filter-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .filter-buttons .btn {
            margin: 0;
        }

        /* Table Styling */
        .table-hover {
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
        }

        .table th,
        .table td {
            padding: 15px;
            font-size: 0.95rem;
            vertical-align: middle;
        }

        .table th {
            background-color: #f1f3f4;
            color: #007bff;
        }

        .table-striped tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }

        .badge {
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.85rem;
        }

        /* Pagination */
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .pagination .page-item {
            margin: 0;
        }

        .pagination .page-link {
            padding: 10px 20px;
            border-radius: 50px;
            border: 1px solid #ddd;
            font-size: 14px;
            color: #007bff;
            transition: background-color 0.3s, color 0.3s;
        }

        .pagination .page-link:hover {
            background-color: #007bff;
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
@section('title', 'Reports | Evaluation')
@section('content')
    <div class="container">
        <!-- Page Header -->
        <div class="text-center mb-5">
            <h2 class="fw-bold text-primary">Evaluation Calls</h2>
            <p class="text-muted">View detailed evaluation for calls activity.</p>
        </div>

        <!-- Filters Section -->
        <div class="mb-4">
            <div class="filter-buttons">
                <a href="{{ route('evaluation.export', [
                    'filter' => $filter,
                    'date_from' => request('date_from'),
                    'date_to' => request('date_to'),
                ]) }}"
                    class="btn btn-modern-export">
                    <i class="fas fa-file-export me-2"></i> Export as CSV
                </a>

                <a href="{{ url('reports/evaluation') }}" class="btn btn-modern-filter {{ !$filter ? 'active' : '' }}">
                    <i class="fas fa-list me-1"></i> All
                </a>
                <a href="{{ url('reports/evaluation?filter=satisfied') }}"
                    class="btn btn-modern-filter {{ $filter === 'satisfied' ? 'active' : '' }}">
                    <i class="fas fa-check-circle me-1"></i> Satisfied
                </a>
                <a href="{{ url('reports/evaluation?filter=unsatisfied') }}"
                    class="btn btn-modern-filter {{ $filter === 'unsatisfied' ? 'active' : '' }}">
                    <i class="fas fa-times-circle me-1"></i> Unsatisfied
                </a>
            </div>

            <!-- Filters Form -->
            <form method="GET" action="{{ url('reports/evaluation') }}" class="filter-form">
                {{-- <input type="number" name="extension_from" class="form-modern" placeholder="Extension From"
                    value="{{ request('extension_from') }}">
                <input type="number" name="extension_to" class="form-modern" placeholder="Extension To"
                    value="{{ request('extension_to') }}">
                <select name="provider" class="form-modern" onchange="this.form.submit()">
                    <option value="">All Providers</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->provider }}"
                            {{ request('provider') == $provider->provider ? 'selected' : '' }}>
                            {{ $provider->provider }}
                        </option>
                    @endforeach
                </select> --}}
                <input type="date" name="date_from" class="form-modern" placeholder="From Date"
                    value="{{ request('date_from') }}">
                <input type="date" name="date_to" class="form-modern" placeholder="To Date"
                    value="{{ request('date_to') }}">
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

            <!-- Satisfied Calls -->
            <div class="col-md-3">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-body">
                        <h5 class="text-success">Satisfied</h5>
                        <h3 class="fw-bold">{{ $satisfiedCount }}</h3>
                    </div>
                </div>
            </div>

            <!-- Unsatisfied Calls -->
            <div class="col-md-3">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-body">
                        <h5 class="text-warning">Unsatisfied</h5>
                        <h3 class="fw-bold">{{ $unsatisfiedCount }}</h3>
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
                                <th>No</th>
                                <th>Mobile</th>
                                <th>Is Satisfied</th>
                                <th>Called At - Day</th>
                                <th>Called At - Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reports as $index => $report)
                                <tr>
                                    <td>{{ $reports->firstItem() + $index }}</td>
                                    <td>{{ $report->mobile }}</td>
                                    <td>
                                        <span
                                            class="badge {{ $report->is_satisfied ? 'badge bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill' : 'badge bg-danger-subtle border border-danger-subtle text-danger-emphasis rounded-pill' }}">
                                            {{ $report->is_satisfied ? 'Satisfied' : 'Unsatisfied' }}
                                        </span>
                                    </td>
                                    <td>{{ $report->created_at->addHours(3)->format('Y-m-d') }}</td>
                                    <td>{{ $report->created_at->addHours(3)->format('H:i:s') }}</td>
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
