@extends('layout.master')

@section('content')
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-white">
            <h3 class="mb-0">Edit AutoDialer Provider</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('autoDialerProviders.update', $provider->id) }}" method="POST" enctype="multipart/form-data">
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

                
                <div class="mb-3">
                    <label for="user_id" class="form-label">User</label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex justify-content-end">
                    <a href="{{ route('autoDialerProviders.index') }}" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
