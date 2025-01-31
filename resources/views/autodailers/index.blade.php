@extends('layout.master')
@section('title', 'Auto Dialer')
@section('content')

@if(session('skip'))
   <div class="alert alert-warning">
       <strong>Skipped Numbers:</strong><br>
       {{ session('skip') }}
   </div>
@endif


@if($errors->any())
   <div class="alert alert-danger">
       <ul>
           @foreach($errors->all() as $error)
               <li>{{ $error }}</li>
           @endforeach
       </ul>
   </div>
@endif
<style>
   .alert {
   padding: 15px;
   margin-bottom: 20px;
   border: 1px solid transparent;
   border-radius: 4px;
}


.alert-success {
   color: #155724;
   background-color: #d4edda;
   border-color: #c3e6cb;
}


.alert-warning {
   color: #856404;
   background-color: #fff3cd;
   border-color: #ffeeba;
}


.alert-danger {
   color: #721c24;
   background-color: #f8d7da;
   border-color: #f5c6cb;
}
</style>


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
                    <i class="bi bi-plus"></i> Select File
                </label>

                <!-- Upload Button (Initially Hidden) -->
                <button type="submit" id="uploadLink" class="btn btn-success" style="display: none;">
                    <i class="bi bi-upload"></i> Upload File
                </button>
            </div>

            <div>
                <!-- Example CSV Download Link -->
                <a href="/autodailer.csv" class="btn btn-info" download="example.csv">
                    <i class="bi bi-download"></i> Auto Dailer File - Demo
                </a>
                {{-- <!-- All Providers -->
            <a href="{{route('autodailers.providers')}}" class="btn btn-primary" >
                <i class="bi bi-downloa"></i> Providers
            </a> --}}
            </div>
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
                            <th><i class="bi bi-file-earmark"></i> File Name</th>
                            <th><i class="bi bi-clock"></i> From</th>
                            <th><i class="bi bi-clock"></i> To</th>
                            <th><i class="bi bi-calendar"></i> Date</th>
                            <th><i class="bi bi-calendar"></i> Uploaded At</th>
                            <th><i class="bi bi-person"></i> Uploaded By</th>
                            <th><i class="bi bi-gear"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td>{{ $file->file_name }}</td>
                                <td>{{ $file->from }}</td>
                                <td>{{ $file->to }}</td>
                                <td>{{ $file->date }}</td> <!-- For Date -->
                                <td>{{ $file->created_at->addHours(3) }}</td> <!-- For Time -->
                                <td>{{ $file->user->name ?? 'Unknown' }}</td>
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


                                        <!-- Edit Button (Opens Modal) -->
                                        <button type="button" class="btn btn-warning btn-sm mx-1" data-bs-toggle="modal"
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
                                            <form action="{{route('autoDailer.update' , $file->slug)}}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" value="{{$file->slug}}">
                                                <div class="mb-3">
                                                    <label for="file_name" class="form-label">File Name:</label>
                                                    <input type="text" class="form-control" name="file_name" id="file_name"
                                                    value="{{ old('file_name', $file->file_name) }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="from" class="form-label">From:</label>
                                                    <input type="time" class="form-control" name="from" id="editFrom"
                                                    value="{{ old('file_name', $file->from) }}"  required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="to" class="form-label">To:</label>
                                                    <input type="time" class="form-control" name="to" id="editTo"
                                                    value="{{ old('file_name', $file->to) }}" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="date" class="form-label">Date:</label>
                                                    <input type="date" class="form-control" name="date"
                                                    value="{{ old('file_name', $file->date) }}" id="editDate" required>
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


    {{-- pop-up edit file --}}
    <!-- Bootstrap Modal -->
    <!-- Edit Time & Date Modal -->



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
