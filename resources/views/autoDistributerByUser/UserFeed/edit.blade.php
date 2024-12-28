@extends('layout.master')

@section('content')
<div class="container">
    <h1 class="my-4">Edit Feed for {{ $provider->name }}</h1>

    <form action="{{ route('feeds.update', [$provider->id, $feed->id]) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="from" class="form-label">From Time</label>
            <input type="time" name="from" id="from" class="form-control" value="{{ $feed->from }}" required>
        </div>
        <div class="mb-3">
            <label for="to" class="form-label">To Time</label>
            <input type="time" name="to" id="to" class="form-control" value="{{ $feed->to }}" required>
        </div>
        <div class="mb-3">
            <label for="date" class="form-label">Date</label>
            <input type="date" name="date" id="date" class="form-control" value="{{ $feed->date }}" required>
        </div>
        <div class="mb-3">
            <label for="csv_file" class="form-label">Upload New CSV File (Optional)</label>
            <input type="file" name="csv_file" id="csv_file" class="form-control">
        </div>
        <div class="mb-3">
            <label for="on" class="form-label">On</label>
            <select name="on" id="on" class="form-select" required>
                <option value="1" {{ $feed->on ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ !$feed->on ? 'selected' : '' }}>No</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="off" class="form-label">Off</label>
            <select name="off" id="off" class="form-select" required>
                <option value="1" {{ $feed->off ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ !$feed->off ? 'selected' : '' }}>No</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Update Feed</button>
        <a href="{{ route('feeds.show', $provider->id) }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
