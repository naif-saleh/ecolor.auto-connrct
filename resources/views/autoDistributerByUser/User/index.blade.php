@extends('layout.master')

@section('style')
    <style>
        .damaged-button {
            margin-top: 10px;
            margin-right: 15px;
            margin-bottom: 5px;
            margin-left: 20px;
            transform: rotate(-5deg);
            /* Slightly tilt the button for the damaged effect */
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
            /* Add shadow for a damaged, rough look */
            border: 2px solid #b52a2a;
            /* Optional: make the border look more damaged */
        }

        .import-button {
            margin-top: 10px;
            margin-right: 15px;
            margin-bottom: 5px;
            margin-left: 20px;
            transform: rotate(-5deg);
            /* Slightly tilt the button for the damaged effect */
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
            /* Add shadow for a damaged, rough look */
            border: 2px solid #b52a2a;
            /* Optional: make the border look more damaged */
        }
    </style>
@endsection


@section('content')
    <div class="container py-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <h2 class="mb-0 text-center text-md-left">Auto Distributerer Users</h2>

            {{-- Actions Section --}}
            <div class="d-flex justify-content-between align-items-center">
                {{-- Search Users Field --}}
                <input type="text" id="search-input" class="form-control form-control-lg" placeholder="Search by name..." />

                {{-- Import Users Button
                @if ($extensions->isEmpty())
                    <a href="{{ route('auto_distributerer_extensions.import') }}"
                        class="btn btn-success btn-lg import-button">
                        <i class="bi bi-cloud-arrow-down"></i> Import Users
                    </a>
                @endif --}}


                    {{-- Delete All Users Button
                    @if (!$extensions->isEmpty())
                        <form action="{{ route('auto_distributerer_extensions.deleteAll') }}" method="POST"
                            id="delete-all-users-form" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger btn-lg damaged-button"
                                id="delete-all-users-button">
                                <i class="bi bi-trash"></i> Delete All Users
                            </button>

                        </form>
                    @endif --}}

            </div>

        </div>

        {{-- Alert for No Users --}}
        @if ($users->isEmpty())
            <div class="alert alert-warning text-center">
                No Auto Distributerer Users found. Click "Import Users" to add one.
            </div>
        @else
            {{-- Users Table --}}
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Extension</th>

                            <th>User Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        @foreach ($users as $extension)
                            <tr class="user-row">
                                <td class="name">{{ $extension->displayName }}  </td>
                                <td>{{ $extension->extension }}</td>
                                <td>{{ $extension->status }}</td>
                                <td class="d-flex justify-content-start gap-2">
                                    {{-- View Button --}}
                                    {{-- <a href="{{ route('auto_distributerer_extensions.show', $extension->id) }}"
                                        class="btn btn-info btn-sm">
                                        <i class="bi bi-eye"></i>
                                    </a> --}}

                                    {{-- Edit Button --}}
                                    {{-- <a href="{{ route('auto_distributerer_extensions.edit', $extension->id) }}"
                                        class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a> --}}


                                     <a href=" {{route('users.files.create', $extension->id)}}"
                                        class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus-lg"></i>
                                    </a>

                                    {{-- Delete Button --}}
                                    {{-- <form action="{{ route('auto_distributerer_extensions.destroy', $extension->id) }}"
                                        method="POST" class="d-inline" id="delete-form-{{ $extension->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="confirmDelete({{ $extension->id }})">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form> --}}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('search-input');
            const tableRows = document.querySelectorAll('.user-row');

            searchInput.addEventListener('input', function() {
                const searchValue = searchInput.value.toLowerCase();

                tableRows.forEach(row => {
                    const name = row.querySelector('.name').textContent.toLowerCase();

                    if (name.includes(searchValue)) {
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
