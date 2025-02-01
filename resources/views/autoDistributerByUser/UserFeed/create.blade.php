@extends('layout.master')




@section('content')
    <div class="container">
        <h2 class="mb-4">Add File for User: {{ $user->displayName }}</h2>

        <form action="" method="POST" enctype="multipart/form-data">
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
        </form>
    </div>
@endsection


{{-- @section('content')
<div class="container py-5">
    <h1>Create Feed for Provider: {{ $provider->name }}</h1>



    <form action="{{ route('autoDialerProviders.storeFeed', $provider->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="timezone" id="timezone">

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

        </div>

        <div class="mb-3">
            <label for="csv_file" class="form-label">Upload CSV (Mobile Numbers)</label>
            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
        </div>

        <button type="submit" class="btn btn-primary">Create Feed</button>
    </form>
    @php
        use Carbon\Carbon;

        $utcTime = Carbon::now('UTC'); // Current UTC time
        $userTimezone = session('user_timezone', 'UTC'); // Default to UTC if not set
        $localTime = $utcTime->setTimezone($userTimezone);
    @endphp
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            document.getElementById('timezone').value = timezone;
        });
    </script>
    <a href="{{ route('autoDialerProviders.index') }}" class="btn btn-secondary mt-4">Back to Providers</a>
</div>
@endsection --}}
