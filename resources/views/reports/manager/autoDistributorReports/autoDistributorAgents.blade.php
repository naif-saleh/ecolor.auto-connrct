@extends('layout.main')
@section('title', 'Manager | Auto Distributor Reports per Providers')
@section('content')

    <div class="container">

        {{-- Filter Section --}}
        <div class="d-flex justify-content-between align-items-center mb-3">


            {{-- Date Filter --}}
            <div class="d-flex gap-2">
                <input type="date" id="date-from" class="form-control" value="{{ request('date_from') }}">
                <input type="date" id="date-to" class="form-control" value="{{ request('date_to') }}">
                <button class="btn btn-success" id="filter-date-btn">Filter</button>
            </div>

            {{-- Provider Filter
            <div class="d-flex gap-2">
                <input type="text" id="provider-name" class="form-control" placeholder="Filter by Provider Name"
                    value="{{ request('provider_name') }}">
                <button class="btn btn-success" id="filter-btn">Filter</button>
            </div> --}}
        </div>

        {{-- Providers List --}}
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle text-center">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-phone-volume"></i> Extension</th>
                        <th><i class="fa-solid fa-user"></i> Agent Name</th>
                        <th><i class="fa-solid fa-phone"></i> Answered</th>
                        <th><i class="fa-solid fa-phone-slash"></i> Unanswered</th>
                        <th><i class="fa-solid fa-user-xmark"></i> Emplooyee Unanswered</th>
                        <th><i class="fa-solid fa-phone-volume"></i> Total Calls</th>
                        <th><i class="fa-solid fa-hashtag"></i> Total Numbers</th>
                        <th><i class="fa-solid fa-file-arrow-up"></i> Uploads Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report as $data)
                        <tr>
                            <td>{{ $data->extension }}</td>
                            <td>{{ $data->provider }}</td>
                            <td>{{ $data->answered }}</td>
                            <td>{{ $data->unanswered }}</td>
                            <td>{{ $data->failed }}</td>
                            <td>{{ $data->total_calls }}</td>
                            <td>{{ $data->total_numbers }}</td>
                            <td>{{ $data->uploads_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="pagination-wrapper d-flex justify-content-center mt-4">
            <ul class="pagination">
                {{-- Preserve Filters in Pagination --}}
                @php
                    $queryParams = request()->except('page'); // Keep all query parameters except 'page'
                @endphp

                <li class="page-item {{ $report->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $report->appends($queryParams)->previousPageUrl() }}" tabindex="-1"
                        aria-disabled="true">Previous</a>
                </li>

                @foreach ($report->getUrlRange(1, $report->lastPage()) as $page => $url)
                    <li class="page-item {{ $report->currentPage() == $page ? 'active' : '' }}">
                        <a class="page-link"
                            href="{{ $url . '&' . http_build_query($queryParams) }}">{{ $page }}</a>
                    </li>
                @endforeach

                <li class="page-item {{ $report->hasMorePages() ? '' : 'disabled' }}">
                    <a class="page-link" href="{{ $report->appends($queryParams)->nextPageUrl() }}">Next</a>
                </li>
            </ul>
        </div>


    </div>

    {{-- JavaScript for Filtering --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Date Range Filtering
            document.getElementById('filter-date-btn').addEventListener('click', function() {
                let from = document.getElementById('date-from').value;
                let to = document.getElementById('date-to').value;
                let url = new URL(window.location.href);
                if (from) url.searchParams.set('date_from', from);
                if (to) url.searchParams.set('date_to', to);
                window.location.href = url.toString(); // This will reload the page with new filters
                from = '';
                to = '';
            });

            // Apply filters for provider name
            document.getElementById('filter-btn').addEventListener('click', function() {
                let providerName = document.getElementById('provider-name').value;
                let url = new URL(window.location.href);

                if (providerName) url.searchParams.set('provider_name', providerName);

                window.location.href = url.toString(); // This will reload the page with new filters
            });
        });
    </script>

@endsection
