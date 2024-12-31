@extends('layout.master')

@section('content')
<div class="container py-5">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="display-4">Create New Provider</h1>
            <a href="{{ route('autoDialerProviders.index') }}" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
        <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('autoDialerProviders.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="Enter provider name" required>
                </div>
                <div class="mb-3">
                    <label for="extension" class="form-label">Extension</label>
                    <input type="text" name="extension" id="extension" class="form-control" placeholder="Enter extension" required>
                </div>
                <div class="mb-3">
                    <label for="user_id" class="form-label">User</label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                 
                <button type="submit" class="btn btn-success btn-lg mt-4 w-100">
                    <i class="bi bi-check-circle"></i> Create Provider
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
