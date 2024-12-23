@extends('layout.master')

@section('content')

    {{-- @if (session('wrong'))
<script>
    window.onload = function() {
        Swal.fire({
            title: 'Success!',
            text: "{{ session('wrong') }}",
            icon: 'error',
            confirmButtonText: 'OK'
        });
    };
</script>
@endif --}}
    <div class="container">
        <h1 class="mb-4">Uploaded Auto Dailers Files</h1>


        <!-- Form to Upload CSV File -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Auto Dailers</div>
            @if (session('wrong'))
                <script>
                    Swal.fire({
                        title: 'Error!',
                        text: "{{ session('wrong') }}",
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                </script>
            @endif
            <div class="card-body">
                <form action="{{ route('autodailers.store') }}" method="POST" enctype="multipart/form-data">
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
                    <a href="{{ route('auto_dailer.call.click') }}" class="btn btn-dark mt-4">Call Auto Dailer</a>
                </form>
                @if (Auth::check() && Auth::user()->isSuperUser())
                    <form action="{{ route('auto-dailers.deleteAll') }}" method="POST" class="delete-form text-end">
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
                        <a href="{{ route('autodailers.show', $file->id) }}" class="btn btn-info btn-sm">View</a>

                        <!-- Edit Button -->
                        <a href="{{ route('autodailers.edit', $file->id) }}" class="btn btn-warning btn-sm">Edit</a>

                        <!-- Delete Form -->
                        <form action="{{ route('autodailers.destroy', $file->id) }}" method="POST"
                            class="d-inline delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger btn-sm"
                                onclick="confirmDelete(this)">Delete</button>
                        </form>


                        <!-- Download Button -->
                        <a href="{{ route('auto_dailer.download', $file->id) }}"
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
