@extends('layout.master')

@section('content')
    <div class="container mt-5">
        <h1 class="mb-4">Auto Distributor Files List</h1>

        <!-- Upload Button -->
        <form action="{{ route('distributor.upload.csv') }}" method="POST" enctype="multipart/form-data"
            class="mb-2 d-flex justify-content-between" id="uploadForm">
            @csrf
            <div>
                <!-- Hidden File Input -->
                <input type="file" name="file" id="uploadButton" style="display: none" accept=".csv">

                @if ($threeCxUsers->count() != 0)
                    <!-- Trigger link wrapped inside a label with the 'for' attribute -->
                    <label for="uploadButton" class="btn btn-secondary">
                        <i class="bi bi-plus"></i>
                    </label>
                @endif

                @if ($threeCxUsers->count() === 0)
                    <a href="{{ route('distributor.import.users') }}" class="btn btn-warning" id="importUsersButton">Import
                        Users</a>
                @endif

                <!-- Upload Button (Initially Hidden) -->
                <button type="submit" id="uploadLink" class="btn btn-success" style="display: none;">
                    <i class="bi bi-upload"></i> Upload New File
                </button>
            </div>

            <!-- Example CSV Download Link -->
            @if ($threeCxUsers->count() != 0)
                <a href="/example.csv" class="btn btn-info" download="example.csv">Example CSV Structure</a>
            @endif
        </form>



        <div class="table-responsive shadow-sm rounded">
            @if ($files->isEmpty())
                <div class="alert alert-info text-center" role="alert">
                    No files available.
                </div>
            @else
                <table class="table table-striped table-hover table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th>File Name</th>
                            <th>Uploaded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td>{{ $file->file_name }}</td>
                                <td>{{ $file->user->name ?? 'Unknown' }}</td>
                                <td class="d-flex justify-content-between">
                                    <!-- Switch for Allow (moved to start) -->
                                    <form action="{{ route('distributor.files.allow', $file->slug) }}" method="POST"
                                        id="allowForm{{ $file->slug }}">
                                        @csrf
                                        <div class="form-check form-switch form-check-lg">
                                            <input class="form-check-input" type="checkbox"
                                                id="allowSwitch{{ $file->slug }}" name="allow"
                                                {{ $file->allow ? 'checked' : '' }} data-file-id="{{ $file->slug }}"
                                                onchange="this.form.submit()">
                                            <span id="statusText{{ $file->slug }}"
                                                class="{{ $file->allow ? 'badge bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill' : 'badge bg-danger-subtle border border-danger-subtle text-danger-emphasis rounded-pill' }}">
                                                {{ $file->allow ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </form>


                                    <div>

                                        <span
                                            class="{{ $file->is_done ? 'badge rounded-pill text-bg-success' : 'badge rounded-pill text-bg-warning' }}">{{ $file->is_done ? 'All Numbers Called' : 'Not Called Yet' }}</span>
                                    </div>



                                    <!-- View and Delete Buttons (moved to end) -->
                                    <div>
                                        <a href="{{ route('distributor.download.processed.file', $file->id) }}"
                                            class="btn btn-sm bg-primary mx-1" id="downloadLink{{ $file->id }}">
                                            <i class="bi bi-download"></i>
                                        </a>

                                        <!-- View Button -->
                                        <a href="{{ route('distributor.files.show', $file->slug) }}"
                                            class="btn btn-info btn-sm mx-1" title="View File">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- Delete Button -->
                                        <form action="{{ route('distributor.delete', $file->slug) }}" method="POST"
                                            style="display: inline;" id="deleteForm{{ $file->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-danger btn-sm mx-1" title="Delete File"
                                                onclick="confirmDeleteAction('{{ $file->id }}')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>


                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>


        <!-- Pagination Controls -->
        <div class="d-flex justify-content-between">
            <div>
                Showing {{ $files->firstItem() }} to {{ $files->lastItem() }} of {{ $files->total() }} results
            </div>
            <div>
                {{ $files->links('vendor.pagination.bootstrap-4') }}
            </div>
        </div>
    </div>
@endsection


{{-- upload file using ajax --}}
{{-- Update Active or not using ajax --}}
@section('scripts')
    <script>
        const uploadButton = document.getElementById('uploadButton');
        const uploadLink = document.getElementById('uploadLink');

        // Listen for changes in the file input
        uploadButton.addEventListener('change', function() {
            // If a file is selected, show the upload button
            if (uploadButton.files.length > 0) {
                uploadLink.style.display = 'inline-block';
            } else {
                uploadLink.style.display = 'none';
            }
        });


        document.getElementById('uploadForm').addEventListener('submit', function(event) {
            Swal.fire({
                title: 'Uploading...',
                text: 'Please wait while your file is being uploaded.',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

        // Show the upload button when a file is selected
        document.getElementById('uploadButton').addEventListener('change', function() {
            document.getElementById('uploadLink').style.display = 'inline-block';
        });

        // Delete Confirm..........................................................................
        function confirmDeleteAction(fileId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form if the user confirms
                    document.getElementById('deleteForm' + fileId).submit();
                }
            });
        }


        // Import users Loading...............................................................................
        document.getElementById('importUsersButton').addEventListener('click', function(event) {
            event.preventDefault(); // Prevent immediate navigation

            Swal.fire({
                title: 'Please wait...',
                text: 'Importing users. This might take a few moments.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    window.location.href =
                        "{{ route('distributor.import.users') }}"; // Redirect after showing the loader
                }
            });
        });





    </script>
@endsection
