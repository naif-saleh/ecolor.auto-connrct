@extends('layout.main')


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


@section('title', 'Reports | Evaluation')

@section('content')
    <div class="container my-5">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-primary">Evaluation Calls Report</h2>
            {{-- <p class="text-muted">Review the satisfaction levels from recent evaluations.</p> --}}
        </div>

        <div class="mb-4">
            <!-- First Line: Export and Filter Buttons -->
            <div class="filter-buttons">
                <!-- Export Button -->
                <a href="{{ route('evaluation.export', ['filter' => $filter, 'date_from' => request('date_from'), 'date_to' => request('date_to')]) }}"
                    class="btn btn-modern-export" id="download-evaluation-csv-button">
                    <i class="fas fa-file-export me-2"></i> Export as CSV
                </a>

                <!-- State Filters (All, Satisfied, Unsatisfied, Today) -->
                <a href="{{ url('reports/evaluation?filter=all') }}"
                    class="btn btn-modern-filter {{ $filter === 'all' ? 'active' : '' }}">
                    <i class="fas fa-list me-1"></i> All
                </a>
                <a href="{{ url('reports/evaluation?filter=satisfied') }}"
                    class="btn btn-modern-filter {{ $filter === 'satisfied' ? 'active' : '' }}">
                    <i class="fas fa-smile me-1"></i> Satisfied
                </a>
                <a href="{{ url('reports/evaluation?filter=unsatisfied') }}"
                    class="btn btn-modern-filter {{ $filter === 'unsatisfied' ? 'active' : '' }}">
                    <i class="fas fa-frown me-1"></i> Unsatisfied
                </a>
                <a href="{{ url('reports/evaluation?filter=today') }}"
                    class="btn btn-modern-filter {{ $filter === 'today' ? 'active' : '' }}">
                    <i class="fas fa-calendar-day me-1"></i> Today
                </a>


            </div>

            <!-- Second Line: Filters Form -->
            <form method="GET" action="{{ url('reports/evaluation') }}" class="filter-form">


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

        <div class="row text-center mb-5">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="text-primary"><i class="fa-solid fa-phone-volume"></i> Total Calls</h5>
                        <h3 class="fw-bold">{{ $totalCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="text-success"><i class="fa-solid fa-thumbs-up"></i> Satisfied</h5>
                        <h3 class="fw-bold">{{ $satisfiedCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="text-danger"><i class="fa-solid fa-thumbs-down"></i> Unsatisfied</h5>
                        <h3 class="fw-bold">{{ $unsatisfiedCount }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th><i class="fa-solid fa-hashtag"></i></th>
                                <th><i class="fa-solid fa-mobile"></i> Mobile</th>
                                <th><i class="fa-solid fa-thumbs-up m-1"></i> \ <i class="fa-solid fa-thumbs-down"></i> Is Satisfied</th>
                                <th><i class="fa-solid fa-calendar-days"></i> Called At - Date</th>
                                <th><i class="fa-solid fa-clock"></i> Called At - Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reports as $index => $report)
                                <tr>
                                    <td>{{ $reports->firstItem() + $index }}</td>
                                    <td>{{ $report->mobile }}</td>
                                    <td>
                                        <span
                                            class="badge {{ $report->is_satisfied === 'YES' ? 'badge bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill' : 'badge bg-danger-subtle border border-danger-subtle text-danger-emphasis rounded-pill' }}">
                                            {{ $report->is_satisfied === 'YES' ? 'Satisfied' : 'Unsatisfied' }}
                                        </span>
                                    </td>
                                    <td>{{ $report->created_at->format('Y-m-d') }}</td>
                                    <td>{{ $report->created_at->format('H:i:s') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">No reports found for the given filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="pagination">
            {!! $reports->appends(request()->except('page'))->links('pagination::bootstrap-5') !!}
        </div>
    </div>
@endsection


@section('scripts')

    <script>
        document.getElementById('download-evaluation-csv-button').addEventListener('click', function(event) {
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
                    link.download = "Evaluation Report";
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
