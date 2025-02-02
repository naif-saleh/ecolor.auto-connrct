@extends('layout.main')
@section('title', 'Distributor | Agents')
 
@section('content')
    <div class="container py-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <h2 class="mb-0 text-center text-md-left">Auto Distributerer Agents</h2>

            {{-- Actions Section --}}
            <div class="d-flex justify-content-between align-items-center">
                {{-- Search Users Field --}}
                <input type="text" id="search-input" class="form-control form-control-lg" placeholder="Search by name..." />



            </div>

        </div>

        {{-- Alert for No Users --}}
        @if ($agents->isEmpty())
            <div class="alert alert-warning text-center">
                No Auto Distributerer Agent found. Click "Import Users" to add one.
            </div>
        @else
            {{-- Users Table --}}
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>Extension</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>



                    <tbody id="users-table-body">
                        @foreach ($agents as $agent)
                            <tr class="user-row">
                                <td class="name">{{ $agent->displayName }}</td>
                                <td class="extension">{{ $agent->extension }}</td>
                                <td class="status {{ $agent->status === 'Available' ? 'text-success' : 'text-warning' }}">
                                    {{ $agent->status }}</td>

                                <td class="d-flex justify-content-start gap-2">
                                    <a href=" {{ route('users.files.create', $agent->id) }}" class="btn btn-primary btn-sm">
                                        <i class="fa fa-plus"></i>
                                    </a>
                                    <a href=" {{ route('users.files.index', $agent->id) }}" class="btn btn-info btn-sm">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        @endif

        <div class="pagination-wrapper d-flex justify-content-center mt-4">
            <ul class="pagination">
                <li class="page-item {{ $agents->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $agents->previousPageUrl() }}" tabindex="-1"
                        aria-disabled="true">Previous</a>
                </li>
                @foreach ($agents->getUrlRange(1, $agents->lastPage()) as $page => $url)
                    <li class="page-item {{ $agents->currentPage() == $page ? 'active' : '' }}">
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    </li>
                @endforeach
                <li class="page-item {{ $agents->hasMorePages() ? '' : 'disabled' }}">
                    <a class="page-link" href="{{ $agents->nextPageUrl() }}">Next</a>
                </li>
            </ul>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('search-input');
            const tableRows = document.querySelectorAll('.user-row'); // Add class 'user-row' to each row

            searchInput.addEventListener('input', function() {
                const searchValue = searchInput.value.toLowerCase();

                tableRows.forEach(row => {
                    const name = row.querySelector('.name').textContent.toLowerCase();
                    const extension = row.querySelector('.extension').textContent.toLowerCase();
                    const status = row.querySelector('.status').textContent.toLowerCase();

                    // Show row if any field matches search value
                    if (name.includes(searchValue) || extension.includes(searchValue) || status
                        .includes(searchValue)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Confirm Delete Function
            window.confirmDelete = function(id) {
                if (confirm('Are you sure you want to delete this user?')) {
                    document.getElementById('delete-form-' + id).submit();
                }
            };
        });
    </script>

@endsection
