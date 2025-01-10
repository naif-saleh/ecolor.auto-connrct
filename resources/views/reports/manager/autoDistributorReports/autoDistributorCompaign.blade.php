@extends('layout.autoDistributer')

@section('title', 'Manager | Auto Distributors Reports')

@section('content')
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="card shadow border-0 rounded-lg">
                    <div class="card-header bg-gradient text-white text-center"
                        style="background-color: #1a73e8; color: #fff;">
                        <h3 class="mb-0">Auto Distributor Report Per Campaign</h3>
                    </div>
                    <div class="card-body">
                        <!-- Report Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle text-center">
                                <thead class="table-primary">
                                    <tr>
                                        <th scope="col">File Name</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Number of Extensions</th>
                                        <th scope="col">Users</th>
                                        <th scope="col">Answered</th>
                                        <th scope="col">Unanswered</th>
                                        <th scope="col">Unanswered (Employee)</th>
                                        <th scope="col">Total Calls</th>
                                        <th scope="col">Total Numbers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($query as $data)
                                        <tr>
                                            <td class="font-weight-bold">{{ $data->file->file_name ?? 'N/A' }}</td>
                                            <td class="status-column">
                                                <span
                                                    class="badge
                                                    @if ($data->status == 'Not Started Yet') badge-secondary
                                                    @elseif($data->status == 'Ringing')
                                                        bg-warning
                                                    @elseif($data->status == 'Partial Completion')
                                                        bg-primary
                                                    @elseif($data->status == 'Called')
                                                        bg-success
                                                    @else
                                                        bg-light  <!-- Default badge if no matching status --> @endif">
                                                    {{ $data->status }}
                                                </span>
                                            </td>

                                            <td>{{ $data->total_extensions }}</td>
                                            <td>
                                                @foreach ($data->users as $user)
                                                    <span
                                                        class="badge bg-light text-dark p-1 m-1">{{ $user }}</span>
                                                @endforeach
                                            </td>
                                            <td>{{ $data->answered }}</td>
                                            <td>{{ $data->unanswered }}</td>
                                            <td>{{ $data->unanswered_employee }}</td>
                                            <td>{{ $data->total_calls }}</td>
                                            <td>{{ $data->total_numbers }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer with total records -->
                    <div class="card-footer text-muted text-center">
                        <small>Total Records: {{ $query->total() }}</small>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-4">
                {{ $query->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <style>
        /* Card styling */
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            padding: 20px;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
        }

        .table-bordered th,
        .table-bordered td {
            padding: 12px;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: #f1f1f1;
        }

        .status-column .badge {
            font-size: 14px;
            padding: 8px 16px;
        }

        .pagination .page-link {
            border-radius: 5px;
            background-color: #1a73e8;
            color: white;
        }

        .pagination .page-link:hover {
            background-color: #155bb5;
        }

        /* Custom Styling for Users */
        .badge.bg-light {
            background-color: #f8f9fa !important;
            color: #495057;
            font-size: 12px;
        }

        .badge.bg-light.text-dark {
            color: #343a40;
        }

        /* Footer styling */
        .card-footer {
            background-color: #f8f9fa;
            padding: 15px;
            border-top: 1px solid #e4e4e4;
        }
    </style>
@endsection
