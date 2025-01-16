@extends('layout.master')
@section('title', 'Auto Dialer | Uploaded CSVs')

@section('style')
    <style>
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

@section('content')
    <div class="container mt-5">
        <h1 class="mb-4">Data for File: {{ $file->file_name }}</h1>

        <!-- Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th>Mobile</th>
                            <th>Provider</th>
                            <th>Extension</th>
                            <th>Status</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($uploadedData as $row)
                            <tr>
                                <td>{{ $row->mobile }}</td>
                                <td>{{ $row->provider }}</td>
                                <td>{{ $row->extension }}</td>
                                <td>{{ $row->state }}</td>
                                <td>{{ $row->from }}</td>
                                <td>{{ $row->to }}</td>
                                <td>{{ $row->date }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Pagination Links -->
                <div class="d-flex flex-column align-items-center mt-4">
                    <!-- Pagination Info -->
                    <div class="mb-2">
                        <span class="text-muted">Showing
                            <strong>{{ $uploadedData->firstItem() }}</strong> to
                            <strong>{{ $uploadedData->lastItem() }}</strong> of
                            <strong>{{ $uploadedData->total() }}</strong> results
                        </span>
                    </div>

                    <!-- Pagination Controls -->
                    <div>
                        {!! $uploadedData->appends(request()->except('page'))->links('pagination::bootstrap-5') !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
