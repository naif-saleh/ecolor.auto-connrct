@extends('layout.main')
@section('title', 'Distributor | Agents')

@section('content')
    <div class="container py-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <div class="d-flex flex-column flex-md-column align-items-center align-items-md-start">
                <h2 class="mb-0 text-center text-md-left mdern-welcome-text">Auto Distributer Agents</h2>
                <span>
                    {{-- Upload CSV File --}}
                    <a href="#" class="btn btn-outline-secondary w-auto mt-4" data-bs-toggle="modal"
                        data-bs-target="#uploadModal"><i class="ti-upload btn-icon-prepend"></i> Upload File</a>
                    <a href="larg_auto_distributor_file.csv" class="btn btn-info mt-4" download><i
                            class="fa-solid fa-download"></i> Auto
                        Distributor Demo File</a>

                    @include('autoDistributerByUser.Agent.__Dis_dropZoneUploadFile')
                </span>
            </div>

            {{-- Actions Section --}}
            <div class="d-flex justify-content-between align-items-center">
                {{-- Search Users Field --}}
                <input type="text" id="search-input" class="form-control form-control-lg"
                    placeholder="Search by name..." />
            </div>

        </div>

        {{-- Alert for No Users --}}
        @if ($agents->isEmpty())
            <div class="alert alert-warning text-center">
                <i class="fa-solid fa-circle-exclamation"></i>  No Auto Distributerer Agent found. Click "Import Users" to add one.
            </div>
        @else
            {{-- Users Table --}}
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-user-large"></i> Agent Name</th>
                            <th><i class="fa-solid fa-phone-volume"></i> Agent Extension</th>
                            <th><i class="fa-solid fa-user-check"></i> Status</th>
                            <th><i class="fa-solid fa-gear"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody>



                    <tbody id="users-table-body">
                        @foreach ($agents as $agent)
                            <tr class="user-row">
                                <td class="name">{{ $agent->displayName }}</td>
                                <td class="extension">{{ $agent->extension }}</td>
                                <td class="status {{ $agent->status === 'Available' ? 'text-success' : 'text-warning' }}">
                                    <b>{{ $agent->status }} <i class="{{ $agent->status === 'Available' ? 'fa-solid fa-check' : 'fa-solid fa-exclamation' }}"></i></b></td>

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

        Dropzone.options.fileDropzone = {
            paramName: "file",
            maxFilesize: 40, // Max size 40MB
            acceptedFiles: ".csv", // Accept only CSV files
            dictDefaultMessage: "Drop your CSV file here or click to upload",
            dictInvalidFileType: "Only CSV files are allowed!",
            dictFileTooBig: "File is too large! Max size: 40MB",

            init: function() {
                let progressContainer = document.createElement("div");
                progressContainer.innerHTML = `
            <div id="uploadProgress" style="display: none; width: 100%; background: #f3f3f3; border-radius: 5px; margin-top: 10px;">
                <div id="uploadProgressBar" style="width: 0%; height: 20px; background: #4caf50; border-radius: 5px; text-align: center; color: white; font-weight: bold;">0%</div>
            </div>
        `;
                document.body.appendChild(progressContainer);

                let progressBar = document.getElementById("uploadProgressBar");

                this.on("sending", function(file) {
                    document.getElementById("uploadProgress").style.display = "block"; // Show progress bar
                    progressBar.style.width = "5%"; // Start at 5%
                    progressBar.innerText = "5%";
                });

                this.on("uploadprogress", function(file, progress) {
                    let simulatedProgress = Math.min(progress + 10, 95); // Simulate smooth movement
                    progressBar.style.width = simulatedProgress + "%";
                    progressBar.innerText = Math.round(simulatedProgress) + "%";
                });

                this.on("success", function(file, response) {
                    let message = response.message || "CSV uploaded successfully!";
                    let errorMessages = response.errors || [];

                    let interval = setInterval(() => {
                        let currentWidth = parseInt(progressBar.style.width);
                        if (currentWidth < 100) {
                            progressBar.style.width = (currentWidth + 2) + "%";
                            progressBar.innerText = (currentWidth + 2) + "%";
                        } else {
                            clearInterval(interval);
                        }
                    }, 50); // Move smoothly to 100%

                    setTimeout(() => {
                        if (errorMessages.length > 0) {
                            let errorList = errorMessages.map(error => `• ${error}`).join("<br>");
                            Swal.fire({
                                icon: "warning",
                                title: "Upload Completed with Issues",
                                html: errorList,
                                confirmButtonText: "OK"
                            }).then(() => {
                                location.reload(); // Refresh after clicking OK
                            });
                        } else {
                            Swal.fire({
                                icon: "success",
                                title: "Upload Successful",
                                text: message,
                                confirmButtonText: "OK"
                            }).then(() => {
                                location.reload(); // Refresh after clicking OK
                            });
                        }
                    }, 800); // Short delay before showing the message
                });

                this.on("error", function(file, response) {
                    let errorMessage = "Upload failed";

                    if (typeof response === "object" && response.errors) {
                        errorMessage = response.errors.map(error => `• ${error}`).join("<br>");
                    } else if (typeof response === "string") {
                        errorMessage = response;
                    }

                    Swal.fire({
                        icon: "error",
                        title: "Upload Error",
                        html: errorMessage,
                        confirmButtonText: "OK"
                    });

                    this.removeFile(file); // Remove file if there's an error
                });

                this.on("queuecomplete", function() {
                    setTimeout(() => {
                        document.getElementById("uploadProgress").style.display =
                            "none"; // Hide progress bar
                    }, 1500);
                });
            }
        };
    </script>

@endsection
