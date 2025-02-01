@extends('layout.master')

@section('content')
    <div class="container">
        <h2 class="mb-4">Files for Provider: {{ $provider->name }}</h2>

        <!-- Files List -->
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

                                        <!-- view Button -->
                                        <button type="button" class="btn btn-info btn-sm mx-1">
                                            <i class="bi bi-eye"></i>
                                        </button>



                                        <!-- Edit Button (Opens Modal) -->
                                        <button type="button" class="btn btn-warning btn-sm mx-1" data-bs-toggle="modal"
                                            data-bs-target="#editFileModal{{ $file->slug }}">
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

        <!-- Back to provider list -->
        <a href="{{ route('providers.index') }}" class="btn btn-primary mt-3">Back to Providers</a>
    </div>
@endsection
