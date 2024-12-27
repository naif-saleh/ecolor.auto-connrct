@extends('layout.master')

@section('content')
    <div class="container mt-5">
        <h1 class="text-center text-primary mb-4">Provider: {{ $provider->name }}</h1>

        <h3 class="text-center mb-4">Feed Files</h3>

        <div class="list-group">
            @foreach ($provider->feedFiles as $feedFile)
                <a href="{{ route('autoDialerProviders.show', $feedFile->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    {{ $feedFile->file_name }}
                    <span class="badge bg-primary rounded-pill">View</span>
                </a>
            @endforeach
        </div>

        @if ($provider->feedFiles->isEmpty())
            <div class="alert alert-warning text-center mt-4">
                No feed files found for this provider.
            </div>
        @endif
    </div>
@endsection
