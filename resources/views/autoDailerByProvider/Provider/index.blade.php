@extends('layout.main')
@section('title', 'Dialer | Providers')
@section('content')
     <div class="container">
        <h1 class="mt-4 mb-4 mdern-welcome-text">Providers</h1>
         <!-- Button to Navigate to Add Provider Page -->
        <a href="javascript:void(0);" class="btn btn-outline-dark" onclick="showProviderForm()"><i class="fa fa-plus"></i> Add Provider</a>
        {{-- Upload CSV File --}}
        <a href="#" class="btn btn-outline-secondary btn-icon-text" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="ti-upload btn-icon-prepend"></i> Upload File</a>
        <a href="larg_auto_diler_file.csv" class="btn btn-info" download><i class="fa-solid fa-download"></i> Auto Dialer Demo File</a>
        @include('autoDailerByProvider.Provider.__Dil_dropZoneUploadFile')

        @if ($providers->isEmpty())
<br>
         <i> '</i><div class="alert alert-warning"><i class="fa-solid fa-circle-exclamation mt-4"></i> No Providers Created. Please Create New One</div> </div>
        @else
            <!-- Providers List -->
            <ul class="list-group mt-3">
                @include('autoDailerByProvider.__error_message')
                <div class="table-responsive">
                    <table class="table text-center">
                        <thead>
                            <tr>
                                <th><i class="fa-brands fa-nfc-directional"></i> Provider Name</th>
                                <th><i class="fa-solid fa-phone-volume"></i> Provider Extension</th>
                                <th><i class="fa-solid fa-calendar-plus"></i> Created</th>
                                <th><i class="fa-solid fa-square-pen"></i> Last Update</th>
                                <th><i class="fa-solid fa-gear"></i> Actions</th>
                            </tr>
                        </thead>
                        @foreach ($providers as $provider)
                            <tbody>
                                <tr>
                                    <td>{{ $provider->name }}</td>
                                    <td>{{ $provider->extension ?? 'No Extension' }}</td>
                                    <td>{{ $provider->created_at->addHour(3) }}</td>
                                    <td>{{ $provider->updated_at->addHour(3) }}</td>
                                    <td>
                                        <!-- Add File Button -->
                                        <!-- Add File Button with Icon -->
                                        <a href="{{ route('provider.files.create', $provider) }}"
                                            class="btn btn-dark btn-sm ml-2">
                                            <i class="fa fa-plus"></i>
                                        </a>

                                        <!-- View Files Button with Icon -->
                                        <a href="{{ route('provider.files.index', $provider) }}"
                                            class="btn btn-info btn-sm ml-2">
                                            <i class="fa fa-eye"></i>
                                        </a>

                                        {{-- Edit Provider --}}
                                        <a href="#" class="btn btn-warning btn-sm ml-2 edit-btn"
                                            data-id="{{ $provider->id }}" data-name="{{ $provider->name }}"
                                            data-extension="{{ $provider->extension ?? '' }}">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>


                                        <!-- Delete Provider Button -->
                                        <form action="{{ route('providers.delete', $provider) }}" method="POST"
                                            class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-danger btn-sm ml-2 delete-btn"
                                                data-provider="{{ $provider->id }}">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>



                                    </td>
                                </tr>

                            </tbody>
                        @endforeach
                    </table>
                    <div class="pagination-wrapper d-flex justify-content-center mt-4">
                        <ul class="pagination">
                            <li class="page-item {{ $providers->onFirstPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $providers->previousPageUrl() }}" tabindex="-1"
                                    aria-disabled="true">Previous</a>
                            </li>
                            @foreach ($providers->getUrlRange(1, $providers->lastPage()) as $page => $url)
                                <li class="page-item {{ $providers->currentPage() == $page ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endforeach
                            <li class="page-item {{ $providers->hasMorePages() ? '' : 'disabled' }}">
                                <a class="page-link" href="{{ $providers->nextPageUrl() }}">Next</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </ul>
        @endif

    </div>




    <script>
        //SweetAlert2

        //Confirm Deletion Provider Using Sweet Alert
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    let form = this.closest('form'); // Get the form
                    let provider = this.getAttribute('data-provider');

                    Swal.fire({
                        title: "Are you sure?",
                        text: "You won't be able to revert this!",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#d33",
                        cancelButtonColor: "#3085d6",
                        confirmButtonText: "Yes, delete it!"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit(); // Submit the form after confirmation
                        }
                    });
                });
            });
        });

        // Create New Provider
        function showProviderForm() {
            Swal.fire({
                title: 'Add New Provider',
                html: `
                <input type="text" id="providerName" class="swal2-input" placeholder="Provider Name">
                <input type="text" id="providerExtension" class="swal2-input" placeholder="Provider Extension">
            `,
                showCancelButton: true,
                confirmButtonText: 'Create Provider',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    // Get the input values
                    const name = document.getElementById('providerName').value;
                    const extension = document.getElementById('providerExtension').value;

                    // Validate inputs (client-side)
                    if (!name) {
                        Swal.showValidationMessage('Provider Name is required');
                        return false;
                    }if (!extension) {
                        Swal.showValidationMessage('Provider Extension is required');
                        return false;
                    }

                    return new Promise((resolve) => {
                        // AJAX request to store provider
                        $.ajax({
                            url: '{{ route('providers.store') }}',
                            type: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                name: name,
                                extension: extension,
                            },
                            success: function(response) {
                                Swal.fire(
                                    'Success!',
                                    'The provider has been created.',
                                    'success'
                                ).then(() => {
                                    // Redirect to provider list or update the UI
                                    location
                                        .reload(); // or use window.location.href = '/providers';
                                });
                            },
                            error: function(xhr) {
                                Swal.fire(
                                    'Error!',
                                    'There was an error creating the provider.',
                                    'error'
                                );
                            }
                        });
                    });
                }
            });
        }


        //Update Provider Using sweet Alert
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    let providerId = this.getAttribute('data-id');
                    let providerName = this.getAttribute('data-name');
                    let providerExtension = this.getAttribute('data-extension');

                    Swal.fire({
                        title: 'Edit Provider',
                        html: `
                        <input id="provider-name" class="swal2-input" placeholder="Provider Name" value="${providerName}">
                        <input id="provider-extension" class="swal2-input" placeholder="Extension (optional)" value="${providerExtension}">
                    `,
                        showCancelButton: true,
                        confirmButtonText: 'Update',
                        cancelButtonText: 'Cancel',
                        preConfirm: () => {
                            return {
                                name: document.getElementById('provider-name').value
                                    .trim(),
                                extension: document.getElementById('provider-extension')
                                    .value.trim()
                            };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch(`/providers/${providerId}/update`, {
                                    method: "PUT",
                                    headers: {
                                        "Content-Type": "application/json",
                                        "X-CSRF-TOKEN": document.querySelector(
                                            'meta[name="csrf-token"]').getAttribute(
                                            'content')
                                    },
                                    body: JSON.stringify(result.value)
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire("Updated!",
                                                "Provider has been updated.", "success")
                                            .then(() => {
                                                location.reload();
                                            });
                                    } else {
                                        Swal.fire("Error!", data.error, "error");
                                    }
                                })
                                .catch(error => {
                                    Swal.fire("Error!", "Something went wrong.",
                                        "error");
                                });
                        }
                    });
                });
            });
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
