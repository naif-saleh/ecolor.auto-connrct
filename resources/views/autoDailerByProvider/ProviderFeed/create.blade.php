@extends('layout.main')

@section('title', 'Dialer |Create New File for ' . $provider->name . ' Provider')
@section('content')
    <div class="container">
        <div class="d-flex justify-content-between">
            <h2 class="mb-4">Add File for Provider: {{ $provider->name }}</h2>
            <a href="/Auto Dialer Demo.csv" class="btn btn-info" download><i class="fa-solid fa-download"></i> Download Demo File</a>
        </div>

        <form action="{{ route('provider.files.store', $provider) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="file_name" class="form-label">File Name</label>
                <input type="text" class="form-control" id="file_name" name="file_name" required placeholder="File name">
            </div>

            <div class="mb-3">
                <label for="file_upload" class="form-label">Upload File</label>
                <input type="file" class="form-control" id="file_upload" name="file_upload" required>
            </div>

            <div class="mb-3">
                <label for="from" class="form-label">From</label>
                <input type="time" class="form-control" id="from" name="from">
            </div>

            <div class="mb-3">
                <label for="to" class="form-label">To</label>
                <input type="time" class="form-control" id="to" name="to">
            </div>


            <div class="mb-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="{{ now()->toDateString() }}"
                    required>
            </div>

            <button type="submit" class="btn btn-success">Save File</button>
            <a href="/providers" class="btn btn-dark">Back</a>
        </form>
    </div>
@endsection


