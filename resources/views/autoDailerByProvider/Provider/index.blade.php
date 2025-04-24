@extends('layout.main')
@section('title', 'Dialer | Providers')
@include('autoDailerByProvider.Provider.__Dil_dropZoneUploadFile')


@section('content')
<div class="container">
    <div class="header-section">
        <h1 class="mb-3">Providers Management</h1>
        <p class="text-muted mb-4">Manage your auto dialer providers, add new ones, or upload provider data via CSV.</p>

        @if($maxProviders > 0)
        <div class="btn-action-group">
            <button class="btn btn-primary btn-icon" onclick="showProviderForm()">
                <i class="fa fa-plus"></i> Add Provider
            </button>

            <button class="btn btn-outline-secondary btn-icon" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="ti-upload"></i> Upload File
            </button>

            <a href="larg_auto_diler_file.csv" class="btn btn-info btn-icon" download>
                <i class="fa-solid fa-download"></i> Download Demo File
            </a>
        </div>
        @endif



        @if(isset($maxProviders) && $isLicenseValid)
        <div class="alert license-alert info">
            @if ($maxProviders > 0)
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-info-circle me-2"></i>
                <strong>License Information: </strong>
                <div class="text-success"> <b> You can activate up to <u>{{ $maxProviders }}</u> providers.</b></d>
                </div>
                @else
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <strong>License Information: </strong>
                    <div class="text-danger"><b>Maximum provider limit reached. Please upgrade your license.</b></d>
                    </div>
                    @endif
                </div>
                @elseif($isLicenseExpaired)
                <div class="alert license-alert danger">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-exclamation-circle me-2"></i>
                        <strong>License Information:</strong> Your license has expired. You cannot add new providers.
                        Please
                        upgrade your license.
                    </div>
                </div>
                @else
                <div class="alert license-alert danger">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <strong>License Information:</strong> Your license is invalid. You cannot add new providers.
                        Please
                        contact us for assistance.
                    </div>
                </div>
                @endif
            </div>

            @include('autoDailerByProvider.__error_message')

            @if ($providers->isEmpty())
            <div class="alert alert-warning">
                <i class="fa-solid fa-circle-exclamation me-2"></i> No providers created. Please create a new one.
            </div>
            @else
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover text-center">
                        <thead>
                            <tr>
                                <th><i class="fa-brands fa-nfc-directional me-1"></i> Provider Name</th>
                                <th><i class="fa-solid fa-phone-volume me-1"></i> Provider Extension</th>
                                <th><i class="fa-solid fa-calendar-plus me-1"></i> Created</th>
                                <th><i class="fa-solid fa-square-pen me-1"></i> Last Update</th>
                                <th class="text-center"><i class="fa-solid fa-gear me-1"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($providers as $provider)
                            <tr class="provider-card">
                                <td>{{ $provider->name }}</td>
                                <td>{{ $provider->extension ?? 'No Extension' }}</td>
                                <td>{{ $provider->created_at }}</td>
                                <td>{{ $provider->updated_at }}</td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <!-- Add File Button -->
                                        <a href="{{ route('provider.files.create', $provider) }}"
                                            class="action-btn btn-add" title="Add File">
                                            <i class="fa fa-plus"></i>
                                        </a>

                                        <!-- View Files Button -->
                                        <a href="{{ route('provider.files.index', $provider) }}"
                                            class="action-btn btn-view" title="View Files">
                                            <i class="fa fa-eye"></i>
                                        </a>

                                        @if (Auth::check() && (Auth::user()->isSuperUser() || Auth::user()->isAdmin()))
                                        <!-- Edit Provider Button -->
                                        <button class="edit-btn action-btn btn-edit" onclick="updateProvider(this)"
                                            data-id="{{ $provider->id }}" data-name="{{ $provider->name }}"
                                            data-extension="{{ $provider->extension ?? '' }}" title="Edit Provider">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>


                                        <!-- Delete Provider Button -->
                                        <form action="{{ route('providers.delete', $provider->id) }}" method="POST"
                                            class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="action-btn btn-delete delete-btn"
                                                onclick="deleteProvider(this)" data-provider="{{ $provider->id }}"
                                                title="Delete Provider">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

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
            @endif
        </div>

        @section('scripts')
        <script>
            // Configure Toastr global options
    toastr.options = {
        closeButton: true,
        newestOnTop: true,
        progressBar: true,
        positionClass: "toast-top-right",
        preventDuplicates: false,
        onclick: null,
        showDuration: "300",
        hideDuration: "1000",
        timeOut: "5000",
        extendedTimeOut: "1000",
        showEasing: "swing",
        hideEasing: "linear",
        showMethod: "fadeIn",
        hideMethod: "fadeOut"
    };

    // Show toasts based on URL parameters or session messages
    document.addEventListener("DOMContentLoaded", function() {
        // Check for flash messages
        @if(session('success'))
            toastr.success("{{ session('success') }}");
        @endif

        @if(session('error'))
            toastr.error("{{ session('error') }}");
        @endif

        @if(session('warning'))
            toastr.warning("{{ session('warning') }}");
        @endif

        @if(session('info'))
            toastr.info("{{ session('info') }}");
        @endif
    });

    // Confirm Deletion Provider Using Sweet Alert with Toastr
    function deleteProvider(button) {
    let form = button.closest('form');
    let provider = button.getAttribute('data-provider');

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
            const formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    toastr.success(data.message || "Provider deleted successfully!");
                    location.reload();
                    const row = button.closest('tr');
                    row.style.transition = 'opacity 0.5s ease';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        if (document.querySelectorAll('tbody tr').length === 0) {
                            location.reload();
                        }
                    }, 500);
                } else {
                    toastr.error(data.message || "Failed to delete provider");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error("Something went wrong");
            });
        }
    });
}


    // Create New Provider with Toastr
    function showProviderForm() {
        Swal.fire({
            title: 'Add New Provider',
            html: `
                <div class="mb-3">
                    <label for="providerName" class="form-label">Provider Name</label>
                    <input type="text" id="providerName" class="form-control" placeholder="Enter provider name">
                </div>
                <div class="mb-3">
                    <label for="providerExtension" class="form-label">Provider Extension</label>
                    <input type="text" id="providerExtension" class="form-control" placeholder="Enter extension number">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Create Provider',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false,
            preConfirm: () => {
                const name = document.getElementById('providerName').value;
                const extension = document.getElementById('providerExtension').value;

                if (!name) {
                    Swal.showValidationMessage('Provider Name is required');
                    return false;
                }
                if (!extension) {
                    Swal.showValidationMessage('Provider Extension is required');
                    return false;
                }

                return { name, extension };
            },
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("providers.store") }}',
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        name: result.value.name,
                        extension: result.value.extension,
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            toastr.success('Provider created successfully!');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            toastr.warning(response.message || 'You need to upgrade your license.');
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'There was an error creating the provider.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        toastr.error(errorMessage);
                    }
                });
            }
        });
    }

    // Update Provider with Toastr
    function updateProvider(button) {
    let providerId = button.getAttribute('data-id');
    let providerName = button.getAttribute('data-name');
    let providerExtension = button.getAttribute('data-extension');

    Swal.fire({
        title: 'Edit Provider',
        html: `
            <div class="mb-3 text-start">
                <label for="provider-name" class="form-label">Provider Name</label>
                <input id="provider-name" class="form-control" value="${providerName}">
            </div>
            <div class="mb-3 text-start">
                <label for="provider-extension" class="form-label">Provider Extension</label>
                <input id="provider-extension" class="form-control" value="${providerExtension}">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Update',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false,
        preConfirm: () => {
            return {
                name: document.getElementById('provider-name').value.trim(),
                extension: document.getElementById('provider-extension').value.trim()
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/providers/${providerId}/update`, {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(result.value)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success("Provider updated successfully!");

                    // Update the row with new data without page refresh
                    const row = button.closest('tr');
                    row.cells[0].textContent = result.value.name;
                    row.cells[1].textContent = result.value.extension || 'No Extension';

                    // Update data attributes for future edits
                    button.setAttribute('data-name', result.value.name);
                    button.setAttribute('data-extension', result.value.extension || '');

                    // Highlight the updated row
                    row.style.backgroundColor = '#f8f9fa';
                    setTimeout(() => {
                        row.style.transition = 'background-color 1s ease';
                        row.style.backgroundColor = '';
                    }, 100);
                } else {
                    toastr.error(data.error || "Failed to update provider");
                }
            })
            .catch(error => {
                toastr.error("Something went wrong");
            });
        }
    });
}


    // Enhanced Dropzone with Toastr notifications
    Dropzone.options.fileDropzone = {
        paramName: "file",
        maxFilesize: 40,
        acceptedFiles: ".csv",
        dictDefaultMessage: `
            <div class="text-center p-4">
                <i class="fa fa-cloud-upload fa-3x mb-2"></i>
                <h5>Drop your CSV file here or click to upload</h5>
                <p class="text-muted">Supports CSV files up to 40MB</p>
            </div>
        `,
        dictInvalidFileType: "Only CSV files are allowed!",
        dictFileTooBig: "File is too large! Max size: 40MB",

        init: function() {
            let progressContainer = document.createElement("div");
            progressContainer.innerHTML = `
                <div id="uploadProgress" style="display: none;">
                    <div id="uploadProgressBar"></div>
                </div>
            `;
            document.body.appendChild(progressContainer);

            let progressBar = document.getElementById("uploadProgressBar");

            this.on("sending", function(file) {
                document.getElementById("uploadProgress").style.display = "block";
                progressBar.style.width = "5%";
            });

            this.on("uploadprogress", function(file, progress) {
                let simulatedProgress = Math.min(progress + 10, 95);
                progressBar.style.width = simulatedProgress + "%";
            });

            this.on("success", function(file, response) {
                let message = response.message || "CSV uploaded successfully!";
                let errorMessages = response.errors || [];

                let interval = setInterval(() => {
                    let currentWidth = parseInt(progressBar.style.width.replace('%', ''));
                    if (currentWidth < 100) {
                        progressBar.style.width = (currentWidth + 2) + "%";
                    } else {
                        clearInterval(interval);
                    }
                }, 50);

                setTimeout(() => {
                    if (errorMessages.length > 0) {
                        toastr.warning("Upload completed with issues");
                        let errorList = errorMessages.map(error => `• ${error}`).join("<br>");
                        Swal.fire({
                            icon: "warning",
                            title: "Upload Completed with Issues",
                            html: errorList,
                            confirmButtonText: "OK"
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        toastr.success(message);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                }, 800);
            });

            this.on("error", function(file, response) {
                let errorMessage = "Upload failed";

                if (typeof response === "object" && response.errors) {
                    errorMessage = response.errors.map(error => `• ${error}`).join("<br>");
                } else if (typeof response === "string") {
                    errorMessage = response;
                }

                toastr.error("File upload failed");

                Swal.fire({
                    icon: "error",
                    title: "Upload Error",
                    html: errorMessage,
                    confirmButtonText: "OK"
                });

                this.removeFile(file);
            });

            this.on("queuecomplete", function() {
                setTimeout(() => {
                    document.getElementById("uploadProgress").style.display = "none";
                }, 1500);
            });
        }
    };

    // Check for license status to show appropriate toasts
    document.addEventListener("DOMContentLoaded", function() {
        @if(isset($isLicenseExpaired) && $isLicenseExpaired)
            toastr.warning("Your license has expired. Please renew to add new providers.");
        @endif

        @if(isset($isLicenseValid) && !$isLicenseValid && !isset($isLicenseExpaired))
            toastr.error("Your license is invalid. Please contact support for assistance.");
        @endif
    });
        </script>
        @endsection
        @endsection
