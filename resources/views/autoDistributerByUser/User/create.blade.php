@extends('layout.master')

@section('content')
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Create New Auto Distributers User</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('autoDistributers.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="Enter provider name" required>
                </div>
                <div class="mb-3">
                    <label for="extension" class="form-label">Extension</label>
                    <input type="text" name="extension" id="extension" class="form-control" placeholder="Enter extension" required>
                </div>

                <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
