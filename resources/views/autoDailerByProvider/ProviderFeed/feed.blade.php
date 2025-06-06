@extends('layout.main')
@section('title', 'Dialer | Files for ' . $provider->name)

@section('content')
    @include('layout.__activetion_message')
    <div class="container">
        <h2 class="mb-4">Files for Provider: <u>{{ $provider->name }}</u></h2>
        <a href="{{ route('provider.files.create', $provider) }}" class="btn btn-primary  mb-2"><i
                class="fa-solid fa-plus"></i>Add File</a>
        <!-- Back to provider list -->
        <a href="{{ route('providers.index') }}" class="btn btn-dark mb-2">Back to Providers</a>
        <!-- Files List -->
        <div class="table-responsive shadow-sm rounded">
            @if ($files->isEmpty())
                <div class="alert alert-info text-center" role="alert">
                    <i class="fa fa-info-circle"></i> No files available.
                </div>
            @else
                <table class="table table-striped table-hover table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th><i class="fa-solid fa-file"></i> File Name</th>
                            <th><i class="fa fa-clock"></i> From</th>
                            <th><i class="fa fa-clock"></i> To</th>
                            <th><i class="fa fa-calendar-day"></i> Date</th>
                            <th><i class="fa fa-user"></i> Uploaded By</th>
                            <th><i class="fa-solid fa-power-off"></i> On\Off</th>
                            <th><i class="fa-solid fa-phone"></i> File Status</th>
                            <th><i class="fa fa-cogs"></i> Actions</th>
                            <th><i class="fa fa-calendar-plus"></i> Uploaded At</th>

                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td>{{ $file->file_name }}</td>
                                <td>{{ $file->from }}</td>
                                <td>{{ $file->to }}</td>
                                <td>{{ $file->date }}</td> <!-- For Date -->
                                <td>{{ $file->user->name ?? 'Unknown' }}</td>

                                <td>
                                    <form action="{{ route('autodailers.files.allow', $file->slug) }}" method="POST"
                                        id="allowForm{{ $file->slug }}">
                                        @csrf
                                        <button type="button" class="toggle-btn {{ $file->allow ? 'btn-on' : 'btn-off' }}"
                                            id="toggleButton{{ $file->slug }}"
                                            onclick="toggleStatus('{{ $file->slug }}')">
                                            <i
                                                class="{{ $file->allow ? 'fa fa-check-circle' : 'fa-solid fa-circle-xmark' }}"></i>
                                            <span
                                                id="statusText{{ $file->slug }}">{{ $file->allow ? 'On' : 'Off' }}</span>
                                        </button>
                                        <input type="hidden" name="allow" id="hiddenInput{{ $file->slug }}"
                                            value="{{ $file->allow ? 1 : 0 }}">
                                    </form>
                                </td>



                                <td>
                                    <div>
                                        <span
                                        class="{{ $file->is_done == false || $file->is_done == 0 || $file->is_done == 'false' ? 'badge bg-secondary text-white rounded-pill' : ($file->is_done === 'calling' ? 'badge bg-primary text-white rounded-pill' : ($file->is_done === 'not_called' ? 'badge bg-danger text-white rounded-pill' : 'badge bg-success text-white rounded-pill')) }}">
                                        <i
                                            class="{{ $file->is_done == false ? 'fa-solid fa-circle' : ($file->is_done === 'calling' ? 'fa-solid fa-phone' : ($file->is_done === 'not_called' ? 'fa-solid fa-times-circle' : 'fa-solid fa-check-circle')) }}"></i>
                                        @if ($file->is_done == false || $file->is_done == 0 || $file->is_done == 'false')
                                            Not Started
                                        @elseif ($file->is_done === 'calling')
                                            Calling ...
                                        @elseif ($file->is_done === 'not_called')
                                            Not Completed
                                        @elseif ($file->is_done === 'called')
                                            Completed
                                        @endif
                                    </span>
                                    </div>
                                </td>
                                </td>



                                <td class="d-flex justify-content-between">




                                    <!-- View and Delete Buttons (moved to end) -->
                                    <div>
                                        <a href="{{ route('provider.files.download', ['provider' => $provider->id, 'file' => $file->id]) }}"
                                            class="btn btn-primary btn-sm mx-1">
                                             <i class="fas fa-download"></i>
                                         </a>

                                        <!-- view Button -->
                                        <a href="{{ route('provider.files.show', $file->slug) }}" type="button"
                                            class="btn btn-info btn-sm mx-1">
                                            <i class="fa fa-eye"></i>
                                        </a>



                                        <!-- Edit Button (Opens Modal) -->
                                        <button type="button" class="btn btn-warning btn-sm mx-1" data-bs-toggle="modal"
                                            data-bs-target="#editFileModal{{ $file->slug }}">
                                            <i class="fa fa-pencil"></i>
                                        </button>


                                        <!-- Delete Button (Form) -->
                                        <form id="deleteForm{{ $file->slug }}"
                                            action="{{ route('autodailer.delete', $file->slug) }}" method="POST"
                                            style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-danger btn-sm mx-1" title="Delete File"
                                                onclick="confirmDeleteAction('{{ $file->slug }}')">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>



                                    </div>

                                </td>
                                <td>{{ $file->created_at }}</td>
                            </tr>


                            <!-- Edit Modal -->
                            <div class="modal fade" id="editFileModal{{ $file->slug }}" tabindex="-1"
                                aria-labelledby="editFileModalLabel{{ $file->slug }}" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Time & Date</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form method="POST" action="{{ route('autoDailer.update', $file->slug) }}">
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
        <div class="pagination-wrapper d-flex justify-content-center mt-4">
            <ul class="pagination">
                <li class="page-item {{ $files->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $files->previousPageUrl() }}" tabindex="-1"
                        aria-disabled="true">Previous</a>
                </li>
                @foreach ($files->getUrlRange(1, $files->lastPage()) as $page => $url)
                    <li class="page-item {{ $files->currentPage() == $page ? 'active' : '' }}">
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    </li>
                @endforeach
                <li class="page-item {{ $files->hasMorePages() ? '' : 'disabled' }}">
                    <a class="page-link" href="{{ $files->nextPageUrl() }}">Next</a>
                </li>
            </ul>
        </div>

    </div>


    <script>
        function confirmDeleteAction(slug) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You won\'t be able to revert this!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'No, cancel!',
                preConfirm: () => {
                    return new Promise((resolve) => {
                        // Find the form and submit it
                        document.getElementById('deleteForm' + slug).submit();
                    });
                }
            });
        }

        function toggleStatus(fileSlug) {
            let button = document.getElementById(`toggleButton${fileSlug}`);
            let statusText = document.getElementById(`statusText${fileSlug}`);
            let hiddenInput = document.getElementById(`hiddenInput${fileSlug}`);

            if (button.classList.contains("btn-on")) {
                // Change to OFF state
                button.classList.remove("btn-on");
                button.classList.add("btn-off");
                statusText.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Off`;
                hiddenInput.value = 0;
            } else {
                // Change to ON state
                button.classList.remove("btn-off");
                button.classList.add("btn-on");
                statusText.innerHTML = `<i class="fa fa-check-circle"></i> On`;
                hiddenInput.value = 1;
            }

            // Submit the form automatically
            document.getElementById(`allowForm${fileSlug}`).submit();
        }
    </script>


@endsection
