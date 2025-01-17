@extends('layout.master')
@section('title', 'Auto Dialer')
@section('content')
    <div class="container mt-5">
        <h1 class="mb-4"><i class="bi bi-telephone"></i> Auto Dialers Files List</h1>

        <!-- Upload Button -->
        <form action="{{ route('autodailers.upload.csv') }}" method="POST" enctype="multipart/form-data"
            class="mb-2 d-flex justify-content-between" id="uploadForm">
            @csrf
            <div>
                <!-- Hidden File Input -->
                <input type="file" name="file" id="uploadButton" style="display: none" accept=".csv">

                <!-- Trigger link wrapped inside a label with the 'for' attribute -->
                <label for="uploadButton" class="btn btn-secondary">
                    <i class="bi bi-plus"></i> Add File
                </label>

                <!-- Upload Button (Initially Hidden) -->
                <button type="submit" id="uploadLink" class="btn btn-success" style="display: none;">
                    <i class="bi bi-upload"></i> Upload New File
                </button>
            </div>

            <!-- Example CSV Download Link -->
            <a href="/example.csv" class="btn btn-info" download="example.csv">
                <i class="bi bi-download"></i> Example CSV Structure
            </a>
        </form>

        <div class="table-responsive shadow-sm rounded">
            @if ($files->isEmpty())
                <div class="alert alert-info text-center" role="alert">
                    <i class="bi bi-info-circle"></i> No files available.
                </div>
            @else
                <table class="table table-striped table-hover table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th>File Name</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td>{{ $file->file_name }}</td>
                                <td>{{ $file->user->name ?? 'Unknown' }}</td>
                                <td>{{ $file->created_at->addHours(3)->format('Y-m-d') }}</td> <!-- For Date -->
                                <td>{{ $file->created_at->addHours(3)->format('H:i:s') }}</td> <!-- For Time -->
                                <td class="d-flex justify-content-between">
                                    <!-- Switch for Allow (moved to start) -->
                                    <form action="{{ route('autodailers.files.allow', $file->slug) }}" method="POST"
                                        id="allowForm{{ $file->slug }}">
                                        @csrf
                                        <div class="form-check form-switch form-check-lg">
                                            <input class="form-check-input" type="checkbox"
                                                id="allowSwitch{{ $file->slug }}" name="allow"
                                                {{ $file->allow ? 'checked' : '' }} data-file-id="{{ $file->slug }}"
                                                onchange="this.form.submit()">
                                            <span id="statusText{{ $file->slug }}"
                                                class="{{ $file->allow ? 'badge bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill' : 'badge bg-danger-subtle border border-danger-subtle text-danger-emphasis rounded-pill' }}">
                                                <i
                                                    class="{{ $file->allow ? 'bi bi-check-circle' : 'bi bi-x-circle' }}"></i>
                                                {{ $file->allow ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </form>

                                    <div>
                                        <span
                                            class="{{ $file->is_done ? 'badge bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill' : 'badge bg-warning-subtle border border-warning-subtle text-warning-emphasis rounded-pill' }}">
                                            <i
                                                class="{{ $file->is_done ? 'bi bi-check-circle' : 'bi bi-exclamation-circle' }}"></i>
                                            {{ $file->is_done ? 'All Numbers Called' : 'Not Called Yet' }}
                                        </span>
                                    </div>

                                    <!-- View and Delete Buttons (moved to end) -->
                                    <div>
                                        <!-- Download Button -->
                                        <a href="{{ url('auto-dailer/download-processed-file', $file->id) }}"
                                            class="btn btn-sm bg-primary mx-1" title="Download File">
                                            <i class="bi bi-download"></i>
                                        </a>

                                        <!-- View Button -->
                                        <a href="{{ route('autodailers.files.show', $file->slug) }}"
                                            class="btn btn-info btn-sm mx-1" title="View File">
                                            <i class="bi bi-eye"></i>
                                        </a>


                                        <!-- Edit Time & Date Modal -->
                                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#editFileModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>



                                        <!-- Delete Button -->
                                        <form action="{{ route('autodailer.delete', $file->slug) }}" method="POST"
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


                            {{-- pop-up edit file --}}
                            <!-- Bootstrap Modal -->
                            <!-- Modal -->
                            <div class="modal fade" id="editFileModal" tabindex="-1" aria-labelledby="editFileModalLabel"
                                aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editFileModalLabel">Update Time & Date</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <form method="POST" action="{{ route('autoDailer.update', $file->id) }}">
                                                @csrf
                                                @method('PUT')

                                                <!-- Hidden input to pass file_id -->
                                                <input type="hidden" name="file_id" value="{{ $file->id }}">

                                                <div class="mb-3">
                                                    <label for="from" class="form-label">From:</label>
                                                    <input type="time" class="form-control" name="from"
                                                        value="{{ old('from', $file->from) }}" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="to" class="form-label">To:</label>
                                                    <input type="time" class="form-control" name="to"
                                                        value="{{ old('to', $file->to) }}" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="date" class="form-label">Date:</label>
                                                    <input type="date" class="form-control" name="date"
                                                        value="{{ old('date', $file->date) }}" required>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

        // Show SweetAlert loading when form is submitted
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

        // Listen for changes in the file input
        uploadButton.addEventListener('change', function() {
            // If a file is selected, show the upload button
            if (uploadButton.files.length > 0) {
                uploadLink.style.display = 'inline-block';
            } else {
                uploadLink.style.display = 'none';
            }
        });

        // Delete Confirm
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
