@extends('layout.master')

@section('content')
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="display-4">Edit Extension</h1>
            <a href="{{ route('auto_distributerer_extensions.index') }}" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <form action="{{ route('auto_distributerer_extensions.update', $autoDistributererExtension->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $autoDistributererExtension->name) }}" required placeholder="Enter extension name">
                    </div>
                    <div class="mb-3">
                        <label for="extension" class="form-label">Extension</label>
                        <input type="text" class="form-control" id="extension" name="extension" value="{{ old('extension', $autoDistributererExtension->extension) }}" required placeholder="Enter extension number">
                    </div>
                    <div class="mb-3">
                        <label for="user_id" class="form-label">User</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" {{ $agent->id == $autoDistributererExtension->user_id ? 'selected' : '' }}>
                                    {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg mt-4 w-100">
                        <i class="bi bi-check-circle"></i> Update Extension
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('styles')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
@endsection
