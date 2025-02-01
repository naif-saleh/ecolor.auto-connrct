@extends('layout.master')

@section('content')
    <div class="container">
        <h2 class="mb-4">Files for Provider: {{ $provider->name }}</h2>

        <!-- Files List -->
        <ul class="list-group">
            @foreach ($files as $file)
                <li class="list-group-item">
                    {{ $file->file_name }}
                    <!-- Optionally, you can link to download/view the file -->
                    <a href="{{ Storage::url($file->file_name) }}" class="btn btn-link" target="_blank">View</a>
                </li>
            @endforeach
        </ul>

        <!-- Back to provider list -->
        <a href="{{ route('providers.index') }}" class="btn btn-primary mt-3">Back to Providers</a>
    </div>
@endsection
