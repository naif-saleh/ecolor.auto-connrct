@extends('layout.master')

@section('content')
<div class="container py-5">
    <h1>Create Feed for Provider: {{ $provider->name }}</h1>

    <form action="{{ route('autoDialerProviders.storeFeed', $provider->id) }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="from" class="form-label">From (Time)</label>
                <input type="time" name="from" id="from" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="to" class="form-label">To (Time)</label>
                <input type="time" name="to" id="to" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" name="date" id="date" class="form-control" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="on" class="form-label">On</label>
                <select name="on" id="on" class="form-select" required>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="off" class="form-label">Off</label>
                <select name="off" id="off" class="form-select" required>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label for="csv_file" class="form-label">Upload CSV (Mobile Numbers)</label>
            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
        </div>

        <button type="submit" class="btn btn-primary">Create Feed</button>
    </form>

    <a href="{{ route('autoDialerProviders.index') }}" class="btn btn-secondary mt-4">Back to Providers</a>
</div>
@endsection
