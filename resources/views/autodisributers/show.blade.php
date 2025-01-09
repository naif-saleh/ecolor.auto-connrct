@extends('layout.master')
@section('title', 'Auto Dailer | Uploaded CSVs')
@section('style')
    <style>
        /* Add some custom spacing between the pagination controls and the table */
        .pagination {
            border-radius: 0.375rem;
            background-color: #f8f9fa;
        }

        /* Change the color of the active page */
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }

        /* Change the color for the hovered links */
        .pagination .page-item .page-link:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        /* Remove the bottom margin for pagination */
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
        }
    </style>
@endsection
@section('title', 'Auto Distributor | File')
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
                            <th>User</th>
                            <th>Extension</th>
                            <th>status</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Date</th>

                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($uploadedData as $row)
                            <tr>
                                <td>{{ $row->mobile }}</td>
                                <td>{{ $row->user }}</td>
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
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <!-- Pagination Info -->
                    <div>
                        <span class="text-muted">Showing
                            <strong>{{ $uploadedData->firstItem() }}</strong> to
                            <strong>{{ $uploadedData->lastItem() }}</strong> of
                            <strong>{{ $uploadedData->total() }}</strong> results
                        </span>
                    </div>

                    <!-- Pagination Controls -->
                    <div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-end mb-0">
                                <!-- Previous Page Link -->
                                @if ($uploadedData->onFirstPage())
                                    <li class="page-item disabled">
                                        <span class="page-link">Previous</span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $uploadedData->previousPageUrl() }}"
                                            aria-label="Previous">Previous</a>
                                    </li>
                                @endif

                                <!-- Page Numbers -->
                                @foreach ($uploadedData->getUrlRange(1, $uploadedData->lastPage()) as $page => $url)
                                    <li class="page-item {{ $uploadedData->currentPage() == $page ? 'active' : '' }}">
                                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                    </li>
                                @endforeach

                                <!-- Next Page Link -->
                                @if ($uploadedData->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $uploadedData->nextPageUrl() }}"
                                            aria-label="Next">Next</a>
                                    </li>
                                @else
                                    <li class="page-item disabled">
                                        <span class="page-link">Next</span>
                                    </li>
                                @endif
                            </ul>
                        </nav>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
