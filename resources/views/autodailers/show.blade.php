@extends('layout.master')

@section('content')
    <div class="container">
        <h1 class="mb-4">File Details: {{ $file->file_name }}</h1>

        <!-- Display File Info -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">File Information</div>
            <div class="card-body">
                <p><strong>Uploaded by:</strong> {{ $file->user->name }}</p>
                <p><strong>Uploaded on:</strong> {{ $file->created_at->format('Y-m-d') }}</p>
            </div>
        </div>

        <!-- Display CSV Data -->
        <h3>CSV Data</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col">Mobile</th>
                    <th scope="col">Provider Name</th>
                    <th scope="col">Extension</th>
                    <th scope="col">State</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($autodailerData as $data)
                    <tr>
                        <td>{{ $data->mobile }}</td>
                        <td>{{ $data->provider_name }}</td>
                        <td>{{ $data->extension }}</td>
                        <td>{{ $data->state }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <a href="{{ route('autodailers.index') }}" class="btn btn-secondary btn-sm">Back to Files</a>

        <div class="d-flex justify-content-center mt-4">
            {{ $autodailerData->links('pagination::bootstrap-5') }}
        </div>

    </div>
@endsection
