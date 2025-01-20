@extends('layout.master')
@section('title', 'Providers')
@section('content')
    <div class="container mt-5">
        <h1 class="mb-4"><i class="bi bi-telephone"></i> Providers Files</h1>

        <!-- Grouping the uploaded data by extension -->
        @foreach ($uploadedData as $data  )
            <h4 class="mt-4">Extension: {{ $extension }}</h4>

            <table class="table table-striped table-hover table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>File Name</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($files as $data)
                        <tr>
                            <td>{{ $data->file->file_name }}</td>
                            <td>{{ $data->file->from }}</td> <!-- Display From time -->
                            <td>{{ $data->file->to }}</td> <!-- Display To time -->
                            <td class="d-flex justify-content-between">
                                <!-- View Button -->
                                <a href="{{ route('autodailers.files.show', $data->file->slug) }}"
                                    class="btn btn-info btn-sm mx-1" title="View File">
                                    <i class="bi bi-eye"></i>
                                </a>

                                <!-- Edit Button (Opens Modal) -->
                                <button type="button" class="btn btn-warning btn-sm mx-1" data-bs-toggle="modal"
                                    data-bs-target="#editFileModal{{ $data->file->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <!-- Delete Button -->
                                <form action="{{ route('autodailers.files.delete', $data->file->slug) }}" method="POST"
                                    style="display: inline;" id="deleteForm{{ $data->file->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-danger btn-sm mx-1" title="Delete File"
                                        onclick="confirmDeleteAction('{{ $data->file->id }}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>

    {{-- Modal for Editing File Information --}}
    <!-- Example for Edit Modal, one per file -->
    @foreach ($groupedData as $extension => $files)
        @foreach ($files as $data)
            <div class="modal fade" id="editFileModal{{ $data->file->id }}" tabindex="-1"
                aria-labelledby="editFileModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editFileModalLabel">Edit File - {{ $data->file->file_name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <form action="{{ route('autodailers.files.update', $data->file->slug) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="from" class="form-label">From:</label>
                                    <input type="time" class="form-control" name="from"
                                        value="{{ $data->file->from }}" required>
                                </div>

                                <div class="mb-3">
                                    <label for="to" class="form-label">To:</label>
                                    <input type="time" class="form-control" name="to" value="{{ $data->file->to }}"
                                        required>
                                </div>

                                <div class="mb-3">
                                    <label for="date" class="form-label">Date:</label>
                                    <input type="date" class="form-control" name="date"
                                        value="{{ $data->file->date }}" required>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach
@endsection

@section('scripts')
    <script>
        // JavaScript functions for deletion confirmation
        function confirmDeleteAction(fileId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    document.getElementById('deleteForm' + fileId).submit();
                }
            });
        }
    </script>
@endsection
