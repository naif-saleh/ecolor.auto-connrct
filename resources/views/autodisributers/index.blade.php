@extends('layout.master')

@section('content')
    <div class="container">
        <h1 class="mb-4">Uploaded Auto Distributers Files</h1>

        @if (session('error'))
            <script>
                Swal.fire({
                    title: 'Error!',
                    text: "{{ session('error') }}",
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            </script>
        @endif

        <!-- Form to Upload CSV File -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Auto Distributers</div>
            <div class="card-body">
                <form action="{{ route('autodistributers.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="file_name">File Name</label>
                        <input type="text" name="file_name" class="form-control" required>
                    </div>
                    <div class="form-group mt-3">
                        <label for="file">CSV File</label>
                        <input type="file" name="file" class="form-control-file" required>
                    </div>
                    <button type="submit" class="btn btn-success mt-4">Upload</button>
                    <a href="{{ route('auto_distributer.call.click') }}" class="btn btn-dark mt-4">Call Auto Distributer</a>
                </form>
                @if (Auth::check() && Auth::user()->isSuperUser())
                    <form action="{{ route('auto-distributers.deleteAll') }}" method="POST" class="delete-form text-end">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger" onclick="confirmDelete(this)">Delete All
                            Files</button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Display Uploaded Files -->
        @if (count($files) > 0)
            @foreach ($files as $file)
                <div class="card mb-3">
                    <div class="card-body">
                        <h5>{{ $file->file_name }}</h5>
                        <p><strong>Uploaded by:</strong> {{ $file->user->name }}</p>
                        <p><strong>Uploaded on:</strong> {{ $file->created_at->format('Y-m-d') }}</p>

                        <!-- View Button -->
                        <a href="{{ route('autodistributers.show', $file->id) }}" class="btn btn-info btn-sm">View</a>

                        <!-- Edit Button -->
                        <a href="{{ route('autodistributers.edit', $file->id) }}" class="btn btn-warning btn-sm">Edit</a>

                        <!-- Delete Form -->
                        <form action="{{ route('autodistributers.destroy', $file->id) }}" method="POST"
                            class="d-inline delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger btn-sm"
                                onclick="confirmDelete(this)">Delete</button>
                        </form>

                        <!-- Download Button -->
                        <a href="{{ route('auto_distributers.download', $file->id) }}"
                            class="btn btn-success btn-sm">Download</a>
                    </div>
                </div>
            @endforeach
        @else
            <div class="alert alert-warning">No Files Uploaded. Please Upload File.</div>
        @endif

    </div>

    <script>
        function confirmDelete() {
            return confirm("Are you sure you want to delete this file?");
        }
    </script>
@endsection
