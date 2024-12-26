@extends('layout.master')

@section('content')
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">AutoDialer Provider Details</h3>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <h5><strong>Name:</strong></h5>
                    <p>{{ $provider->name }}</p>
                </div>
                <div class="col-md-6">
                    <h5><strong>Extension:</strong></h5>
                    <p>{{ $provider->extension }}</p>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <h5><strong>Uploaded by User:</strong></h5>
                    <p>{{ $provider->user->name }}</p>
                </div>
                <div class="col-md-6">
                    <h5><strong>File Sound:</strong></h5>
                    @if($provider->file_sound)
                        <audio controls class="w-100">
                            <source src="{{ asset('storage/' . $provider->file_sound) }}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    @else
                        <p class="text-muted">Not Uploaded</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-footer text-center">
            <a href="{{ route('autoDialerProviders.index') }}" class="btn btn-secondary">Back to List</a>
            <a href="{{ route('autoDialerProviders.edit', $provider->id) }}" class="btn btn-warning">Edit</a>
            <form action="{{ route('autoDialerProviders.destroy', $provider->id) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>
@endsection
