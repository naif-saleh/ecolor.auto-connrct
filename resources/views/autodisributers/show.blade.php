@extends('layout.master')
@section('title', 'Auto Distributor | File')

@section('style')
    <style>
        /* Style the pagination container */
        .pagination {
            border-radius: 0.375rem;
            background-color: #f8f9fa;
            padding: 10px;
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        /* Customize the appearance of the pagination links */
        .pagination .page-link {
            color: #007bff;
            margin: 0 5px;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }

        /* Change the color of the active page */
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
            color: #fff;
        }

        /* Change the color for hovered links */
        .pagination .page-item .page-link:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: #fff;
        }

        /* Disabled pagination links */
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        /* Aligning pagination info */
        .pagination-info {
            text-align: center;
            margin-bottom: 15px;
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
                            <th>User</th>
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

                <!-- Pagination Info -->
                <div class="pagination-info">
                    <span class="text-muted">Showing
                        <strong>{{ $uploadedData->firstItem() }}</strong> to
                        <strong>{{ $uploadedData->lastItem() }}</strong> of
                        <strong>{{ $uploadedData->total() }}</strong> results
                    </span>
                </div>

                <!-- Pagination Controls -->
                <nav aria-label="Page navigation">
                    <ul class="pagination">
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
                                <a class="page-link" href="{{ $uploadedData->nextPageUrl() }}" aria-label="Next">Next</a>
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
@endsection
