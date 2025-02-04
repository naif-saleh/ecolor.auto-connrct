@extends('layout.main')
@section('title', 'Manager | Auto Dialers Reports per Providers')
@section('content')

    <div class="container">

        {{-- Filter Section --}}
        <div class="d-flex justify-content-between align-items-center mb-3">


            {{-- Date Filter --}}
            <div class="d-flex gap-2">
                <input type="date" id="date-from" class="form-control" name="date_from" value="{{ request('date_from') }}">
                <input type="date" id="date-to" class="form-control" name="date_to" value="{{ request('date_to') }}">

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

                        <th><i class="fa-brands fa-nfc-directional"></i> File name</th>
                        <th><i class="fa-solid fa-clock"></i> timing</th>
                        <th><i class="fa-solid fa-phone"></i> | <i class="fa-solid fa-phone-slash"></i> Status</th>
                        <th><i class="fa-solid fa-phone-volume"></i> NO.Extentions</th>
                        <th><i class="fa-solid fa-phone-volume"></i> provider</th>
                        <th><i class="fa-solid fa-phone"></i> answered</th>
                        <th><i class="fa-solid fa-phone-slash"></i>unanswered </th>
                        <th><i class="fa-solid fa-user-xmark"></i> failed </th>
                        <th><i class="fa-solid fa-square-phone"></i> Total calls</th>
                        <th><i class="fa-solid fa-hashtag"></i> Total Numbers</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($campaignReports->isEmpty())
                        <tr>
                            <td colspan="10" class="text-center text-danger">
                                <strong>No records found for the selected filters.</strong>
                            </td>
                        </tr>
                    @else
                        @foreach ($campaignReports as $report)
                            <tr>
                                <td>{{ $report['file_name'] }}</td>
                                <td>{{ $report['from'] }} - {{ $report['to'] }}</td>
                                <td>{{ $report['status'] }}</td>
                                <td>{{ $report['number_of_extensions'] }}</td>
                                <td>{{ $report['provider'] }}</td>
                                <td>{{ $report['answered'] }}</td>
                                <td>{{ $report['unanswered'] }}</td>
                                <td>{{ $report['failed'] }}</td>
                                <td>{{ $report['total_calls'] }}</td>
                                <td>{{ $report['total_numbers'] }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>

            </table>
        </div>

        {{-- Pagination --}}
        @if ($reports->hasPages())
            <div class="pagination-wrapper d-flex justify-content-center mt-4">
                <ul class="pagination">
                    @php
                        $queryParams = request()->except('page'); // Preserve filters
                    @endphp

                    {{-- Previous Button --}}
                    <li class="page-item {{ $reports->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $reports->appends($queryParams)->previousPageUrl() }}"
                            aria-disabled="true">Previous</a>
                    </li>

                    {{-- Page Numbers --}}
                    @for ($page = 1; $page <= $reports->lastPage(); $page++)
                        <li class="page-item {{ $reports->currentPage() == $page ? 'active' : '' }}">
                            <a class="page-link"
                                href="{{ $reports->appends($queryParams)->url($page) }}">{{ $page }}</a>
                        </li>
                    @endfor

                    {{-- Next Button --}}
                    <li class="page-item {{ $reports->hasMorePages() ? '' : 'disabled' }}">
                        <a class="page-link" href="{{ $reports->appends($queryParams)->nextPageUrl() }}">Next</a>
                    </li>
                </ul>
            </div>
        @endif



    </div>

    {{-- JavaScript for Filtering --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('filter-date-btn').addEventListener('click', function() {
                let from = document.getElementById('date-from').value;
                let to = document.getElementById('date-to').value;
                let url = new URL(window.location.href);

                if (from) url.searchParams.set('date_from', from);
                else url.searchParams.delete('date_from');

                if (to) url.searchParams.set('date_to', to);
                else url.searchParams.delete('date_to');

                window.location.href = url.toString(); // Reload the page with new filters
            });
        });
    </script>


@endsection
