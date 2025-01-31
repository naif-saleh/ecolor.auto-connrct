@extends('layout.master')
@section('title', 'Auto Distributor')
@section('content')

    @if (session('skip'))
        <div class="alert alert-warning">
            <strong>Skipped Numbers:</strong><br>
            {{ session('skip') }}
        </div>
    @endif


    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
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
        <h1 class="mb-4"><i class="bi bi-telephone"></i> Auto Distributor Files List</h1>

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
                        <i class="bi bi-plus"></i> Select File
                    </label>
                @endif

                <!-- Upload Button (Initially Hidden) -->
                <button type="submit" id="uploadLink" class="btn btn-success" style="display: none;">
                    <i class="bi bi-upload"></i> Upload File
                </button>
            </div>

            <div>

                @if ($threeCxUsers->count() == 0)
                    <a href="{{ route('distributor.import.users') }}" class="btn btn-warning" id="importUsersButton">
                        <i class="bi bi-arrow-repeat"></i> Synchronize Users
                    </a>
                @endif
                @if ($threeCxUsers->count() != 0)
                    <a id="openModalButton" class="btn btn-danger">
                        <i class="fas fa-phone-slash"></i> Stop Calls
                    </a>

                    <a href="{{ route('distributor.import.users') }}" class="btn btn-warning" id="importUsersButton">
                        <i class="bi bi-arrow-repeat"></i> Resynchronize Users
                    </a>
                    <a href="/autodistributor.csv" class="btn btn-info" download="example.csv">
                        <i class="bi bi-file-earmark-text"></i> Auto Distributor - Demo
                    </a>
                @endif



                <div>
                    <!-- Modal -->
                    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content border-0 rounded-3 shadow-lg">
                                <div class="modal-header border-0">
                                    <h5 class="modal-title" id="userModalLabel">Select Users to Run/Stop</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <form action="" method="GET">
                                    <div class="modal-body py-4">
                                        <!-- Search Bar -->
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="searchUser"
                                                placeholder="Search for users...">
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover" id="userTable">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Select</th>
                                                        <th>User Name</th>
                                                        <th>Extension</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($threeCxLiveUsers as $user)
                                                        <tr
                                                            class="{{ $user['CurrentProfileName'] == 'Available' ? 'table-success' : 'table-danger' }}">
                                                            <td class="text-center">
                                                                <input class="form-check-input user-checkbox"
                                                                    type="checkbox" name="users[]"
                                                                    value="{{ $user['Id'] }}" id="user{{ $user['Id'] }}"
                                                                    data-user-id="{{ $user['Id'] }}"
                                                                    {{ $user['CurrentProfileName'] == 'Available' ? 'checked' : '' }}>
                                                            </td>
                                                            <td class="user-name">{{ $user['DisplayName'] }}</td>
                                                            <td class="user-extension">{{ $user['Number'] }}</td>
                                                            <td><strong
                                                                    id="status{{ $user['Id'] }}">{{ $user['CurrentProfileName'] }}</strong>
                                                            </td>
                                                        </tr>
                                                    @endforeach



                                                </tbody>
                                            </table>
                                        </div>
                                    </div>


                                </form>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
    </div>

    </form>

    <div class="table-responsive shadow-sm rounded">
        @if ($files->isEmpty())
            <div class="alert alert-info text-center" role="alert">
                <i class="bi bi-info-circle"></i> No files available.
            </div>
        @else
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
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
                            <td>{{ $file->date }}</td>
                            <td>{{ $file->created_at->addHours(3)->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $file->user->name ?? 'Unknown' }}</td>
                            <td class="d-flex justify-content-between">
                                <!-- Switch for Allow (moved to start) -->
                                <form action="{{ route('distributor.files.allow', $file->slug) }}" method="POST"
                                    id="allowForm{{ $file->slug }}">
                                    @csrf
                                    <div class="form-check form-switch form-check-lg">
                                        <input class="form-check-input" type="checkbox" id="allowSwitch{{ $file->slug }}"
                                            name="allow" {{ $file->allow ? 'checked' : '' }}
                                            data-file-id="{{ $file->slug }}" onchange="this.form.submit()">
                                        <span
                                            class="badge {{ $file->allow ? 'bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill' : 'bg-danger-subtle border border-danger-subtle text-danger-emphasis rounded-pill' }}">
                                            <i
                                                class="bi {{ $file->allow ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i>
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
                                    <a href="{{ route('distributor.download.processed.file', $file->id) }}"
                                        class="btn btn-sm bg-primary mx-1" title="Download File">
                                        <i class="bi bi-download"></i>
                                    </a>

                                    <!-- View Button -->
                                    <a href="{{ route('distributor.files.show', $file->slug) }}"
                                        class="btn btn-info btn-sm mx-1" title="View File">
                                        <i class="bi bi-eye"></i>
                                    </a>


                                    <!-- Edit Button (Opens Modal) -->
                                    <button type="button" class="btn btn-warning btn-sm mx-1" data-bs-toggle="modal"
                                        data-bs-target="#editFileModal{{ $file->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>


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

                        <!-- Edit File Modal -->
                        <div class="modal fade" id="editFileModal{{ $file->id }}" tabindex="-1"
                            aria-labelledby="editFileModalLabel{{ $file->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Time & Date</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST" action="{{ route('distributor.update', $file->id) }}">
                                            @csrf
                                            @method('PUT')

                                            <input type="hidden" name="file_id" value="{{ $file->id }}">

                                            <div class="mb-3">
                                                <label for="file_name" class="form-label">File Name:</label>
                                                <input type="text" class="form-control" name="file_name"
                                                    value="{{ old('file_name', $file->file_name) }}" required>
                                            </div>
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

@section('scripts')
    <script>
        const uploadButton = document.getElementById('uploadButton');
        const uploadLink = document.getElementById('uploadLink');

        // Listen for changes in the file input
        uploadButton.addEventListener('change', function() {
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
                    document.getElementById('deleteForm' + fileId).submit();
                }
            });
        }

        document.getElementById('importUsersButton').addEventListener('click', function(event) {
            event.preventDefault();

            Swal.fire({
                title: 'Please wait...',
                text: 'Importing users. This might take a few moments.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    window.location.href = "{{ route('distributor.import.users') }}";
                }
            });
        });



        document.getElementById('openModalButton').addEventListener('click', function() {
            var myModal = new bootstrap.Modal(document.getElementById('userModal'));
            myModal.show();
        });

        document.getElementById("searchUser").addEventListener("keyup", function() {
            let input = this.value.toLowerCase();
            let rows = document.querySelectorAll("#userTable tbody tr");

            rows.forEach(row => {
                let name = row.querySelector(".user-name").textContent.toLowerCase();
                let extension = row.querySelector(".user-extension").textContent.toLowerCase();

                if (name.includes(input) || extension.includes(input)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });


        // update user status via ajax
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                let userId = this.getAttribute('data-user-id');
                let newStatus = this.checked ? 'Available' :
                    'Away'; // Update status based on checkbox state

                // Send the PATCH request
                fetch('/update-users-status', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        },
                        body: JSON.stringify({
                            users: {
                                [userId]: newStatus
                            }
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the status in the table without reloading
                            document.getElementById('status' + userId).innerText = newStatus;
                            let row = document.getElementById('user' + userId).closest('tr');
                            if (newStatus === 'Available') {
                                row.classList.remove('table-danger');
                                row.classList.add('table-success');
                            } else {
                                row.classList.remove('table-success');
                                row.classList.add('table-danger');
                            }
                        } else {
                            // Handle any errors here
                            alert('Failed to update status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating status');
                    });
            });
        });


        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                let userId = this.getAttribute('data-user-id');
                let newStatus = this.checked ? 'Available' :
                    'Out of office'; // Update status based on checkbox state

                // Send the PATCH request
                fetch('/update-users-status', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        },
                        body: JSON.stringify({
                            users: {
                                [userId]: newStatus
                            }
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the status in the table without reloading
                            document.getElementById('status' + userId).innerText = newStatus;
                            let row = document.getElementById('user' + userId).closest('tr');
                            if (newStatus === 'Available') {
                                row.classList.remove('table-danger');
                                row.classList.add('table-success');
                            } else {
                                row.classList.remove('table-success');
                                row.classList.add('table-danger');
                            }
                        } else {
                            // Handle any errors here
                            alert('Failed to update status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating status');
                    });
            });
        });
    </script>
@endsection
