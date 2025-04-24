@extends('layout.main')
@section('title', 'Distributor | Agents')
@include('autoDistributerByUser.Agent.__updateFeedModal')
@include('autoDistributerByUser.Agent.__Dis_dropZoneUploadFile')
@section('content')
<div class="container">
    <h1>Auto Distributer Agents</h1>


    {{-- Actions Section --}}
    <div class="row mb-3">
        <div class="col-md-8 d-flex gap-2">
            <a href="#" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fa fa-upload"></i> Upload File
            </a>
            <a href="larg_auto_dist_file .csv" class="btn btn-info">
                <i class="fa fa-file"></i> Auto Distributor Demo File
            </a>
            <button class="btn btn-warning" onclick="fetchTodayFeeds()">
                <i class="fa fa-rss"></i> Manage Today Feeds
            </button>
        </div>
        <div class="col-md-4">
            {{-- Search Users Field --}}
            <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search by name..." />
        </div>
    </div>

    @if($error)
    <div class="alert alert-danger">
        <strong>Error:</strong> {{ $error }}
    </div>
    @endif

    @if(isset($maxAgents) && $isLicenseValid && !$error)
    <div class="alert alert-info">
        <strong>License Information:</strong> You can activate up to {{ $maxAgents }} agents.
        Currently <span id="activeAgentsCount">{{ $activeAgentsCount }}</span> out of {{ $totalAgents }} agents are
        active.
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="5%">Active</th>
                        <th><i class="fa fa-user"></i> Agent Name</th>
                        <th><i class="fa fa-phone"></i> Extension</th>
                        <th><i class="fa fa-signal"></i> Status</th>
                        <th><i class="fa fa-list"></i> Queue</th>
                        <th><i class="fa fa-tools"></i> Action</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    @forelse($allAgents as $agent)
                    <tr class="user-row {{ $agent->is_active ? 'table-success' : ''}}" >
                        <td>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input agent-toggle" data-id="{{ $agent->id }}"
                                    {{ $agent->is_active ? 'checked' : '' }}
                                {{ (!$isLicenseValid || $maxAgents <= 0) ? 'disabled' : '' }}>
                            </div>
                        </td>
                        <td class="name">{{ $agent->displayName }}</td>
                        <td class="extension">{{ $agent->extension }}</td>
                        <td class="status">
                            @if($agent->status == 'Available')
                            <span class="text-success">Available <i class="fa fa-check"></i></span>
                            @else
                            <span class="text-danger">{{ $agent->status }}</span>
                            @endif
                        </td>
                        <td>{{ $agent->QueueStatus }}</td>
                        <td>
                            @if($agent->is_active)
                            <div class="btn-group">
                                <a href="{{ route('users.files.create', $agent->id) }}" class="btn btn-sm btn-primary">
                                    <i class="fa fa-plus"></i>
                                </a>
                                <a href="{{ route('users.files.index', $agent->id) }}" class="btn btn-sm btn-info">
                                    <i class="fa fa-eye"></i>
                                </a>
                            </div>
                            @else
                            <span class="text-danger">Agent is Inactive <i class="fa fa-x"></i></span>

                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center">No agents found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>


</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery === 'undefined') {
            console.error('jQuery not loaded');
            return;
        }

        $(document).ready(function() {
            // Maximum allowed agents (from license)
            const maxAgents = @json($maxAgents ?? 0);

            // Toggle agent active status
            $('.agent-toggle').on('change', function() {
                const agentId = $(this).data('id');
                const isChecked = $(this).prop('checked');
                const $row = $(this).closest('tr');
                const checkbox = this;

                $.ajax({
                    url: '{{ route("auto-distributor.toggle-agent") }}',
                    type: 'POST',
                    data: {
                        agent_id: agentId,
                        activate: isChecked,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (isChecked) {
                            $row.addClass('table-success');
                            setTimeout(()=>{
                                location.reload();
                            }, 1000)
                        } else {
                            $row.removeClass('table-success');
                        }

                        $('#activeAgentsCount').text(response.activeCount);
                        toastr.success(response.message);
                    },
                    error: function(xhr) {
                        // Revert checkbox state
                        $(checkbox).prop('checked', !isChecked);
                        $row.toggleClass('table-success', !isChecked);

                        const response = xhr.responseJSON;
                        toastr.error(response?.message || 'An error occurred while updating agent status.');
                    }
                });
            });

            // Enforce license limit before activating agent
                $('.agent-toggle').on('click', function(e) {
                // Check if we're trying to activate (not already checked)
                const willBeChecked = !$(this).prop('checked');

                if (!willBeChecked) {
                    return true; // We're unchecking, always allow
                }

                const currentlyActive = $('.agent-toggle:checked').length;

                // Since we're checking a new box, we need to see if this would exceed the limit
                if (currentlyActive >= maxAgents) {
                    e.preventDefault();
                    toastr.error(`License limit reached. Maximum ${maxAgents} agents allowed. Please upgrade your license.`);
                    return false;
                }

                if (willBeChecked) {
                setTimeout(() => {
                    location.reload();
                }, 1000); // Delay for smooth UX
            }

                return true;
            });
        });


        Dropzone.options.fileDropzone = {
            paramName: "file",
            maxFilesize: 40,
            acceptedFiles: ".csv",
            dictDefaultMessage: "Drop your CSV file here or click to upload",
            dictInvalidFileType: "Only CSV files are allowed!",
            dictFileTooBig: "File is too large! Max size: 40MB",

            init: function() {
                let progressBar = document.createElement("div");
                progressBar.innerHTML = `
            <div id="uploadProgress" style="display: none; width: 100%; background: #f3f3f3; border-radius: 5px; margin-top: 10px;">
                <div id="uploadProgressBar" style="width: 0%; height: 20px; background: #4caf50; border-radius: 5px; text-align: center; color: white; font-weight: bold;">0%</div>
            </div>
        `;
                document.body.appendChild(progressBar);

                let progress = document.getElementById("uploadProgressBar");

                this.on("sending", function(file) {
                    document.getElementById("uploadProgress").style.display = "block";
                    progress.style.width = "5%";
                    progress.innerText = "5%";
                });

                this.on("uploadprogress", function(file, progressValue) {
                    let simulatedProgress = Math.min(progressValue + 10, 95);
                    progress.style.width = simulatedProgress + "%";
                    progress.innerText = Math.round(simulatedProgress) + "%";
                });

                this.on("success", function(file, response) {
                    let message = response.message || "CSV uploaded successfully!";
                    let skippedNumbers = response.skippedNumbers || [];
                    let csvDownloadUrl = response.csv_url || null;

                    let interval = setInterval(() => {
                        let currentWidth = parseInt(progress.style.width);
                        if (currentWidth < 100) {
                            progress.style.width = (currentWidth + 2) + "%";
                            progress.innerText = (currentWidth + 2) + "%";
                        } else {
                            clearInterval(interval);
                        }
                    }, 50);

                    setTimeout(() => {
                        if (skippedNumbers.length > 0) {
                            let errorList = skippedNumbers.map(error => `• ${error}`).join("<br>");
                            Swal.fire({
                                icon: "warning",
                                title: "Upload Completed with Issues",
                                html: `<b>${skippedNumbers.length} numbers skipped:</b><br>${errorList}<br><br>${csvDownloadUrl ? '<a href="'+csvDownloadUrl+'" download class="btn btn-primary">Download Skipped Numbers</a>' : ''}`,
                                confirmButtonText: "OK"
                            }).then(() => {
                                location.reload();
                            });

                            // ✅ Auto-download CSV if available
                            if (csvDownloadUrl) {
                                let link = document.createElement('a');
                                link.href = csvDownloadUrl;
                                link.download = "skipped_numbers.csv";
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                            }
                        } else {
                            Swal.fire({
                                icon: "success",
                                title: "Upload Successful",
                                text: message,
                                confirmButtonText: "OK"
                            }).then(() => {
                                location.reload();
                            });
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



    });
</script>

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

    // Update feeds
    function fetchTodayFeeds() {
            fetch('/today-feeds')
                .then(response => response.json())
                .then(feeds => {
                    let feedList = document.getElementById('feedList');
                    feedList.innerHTML = '';

                    feeds.forEach(feed => {
                        feedList.innerHTML += `
                        <tr>
                            <td><input type="checkbox" class="feed-checkbox" value="${feed.id}"></td>
                            <td>${feed.file_name}</td>
                            <td>${feed.agent.extension}</td>
                            <td>${feed.from}</td>
                            <td>${feed.to}</td>
                            <td>${feed.date}</td>
                            <td class="${feed.allow === 1 ? "bg-green" : "bg-red"}"> ${feed.allow === 1 ? "Active" : "Disactive"}</td>
                        </tr>
                    `;
                    });

                    $('#feedModal').modal('show');
                });
        }

        function toggleSelectAll() {
            let checkboxes = document.querySelectorAll('.feed-checkbox');
            let selectAll = document.getElementById('selectAll').checked;
            checkboxes.forEach(checkbox => checkbox.checked = selectAll);
        }

        function updateStatus(status) {
            let selectedFeeds = [...document.querySelectorAll('.feed-checkbox:checked')].map(cb => cb.value);

            if (selectedFeeds.length === 0) {
                alert('Please select at least one file.');
                return;
            }

            fetch('/update-feed-status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        feed_ids: selectedFeeds,
                        status
                    })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    $('#feedModal').modal('hide');
                })
                .catch(error => console.error('Error:', error));
        }

        // Delete selected feeds
        function deleteFeeds() {
            let selectedFeeds = [...document.querySelectorAll('.feed-checkbox:checked')].map(cb => cb.value);

            if (selectedFeeds.length === 0) {
                alert('Please select at least one feed to delete.');
                return;
            }

            if (!confirm('Are you sure you want to delete the selected feeds?')) {
                return;
            }

            fetch('/delete-feeds', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        feed_ids: selectedFeeds
                    })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    $('#feedModal').modal('hide');
                    fetchTodayFeeds(); // Refresh the feed list
                })
                .catch(error => console.error('Error:', error));
        }
</script>

@endsection
