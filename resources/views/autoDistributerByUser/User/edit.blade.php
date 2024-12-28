@extends('layout.master')

@section('content')
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-white">
            <h3 class="mb-0">Edit Auto Distributers User</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('autoDistributers.update', $provider->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" id="name" class="form-control" value="{{ $provider->name }}" required>
                </div>

                <div class="mb-3">
                    <label for="extension" class="form-label">Extension</label>
                    <input type="text" name="extension" id="extension" class="form-control" value="{{ $provider->extension }}" required>
                </div>

                 

                <input type="hidden" name="user_id" value="{{ auth()->id() }}">

                <div class="d-flex justify-content-end">
                    <a href="{{ route('autoDistributers.index') }}" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
