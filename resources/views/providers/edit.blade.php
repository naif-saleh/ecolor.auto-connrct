@extends('layout.master')

@section('content')
<div class="container">
    <h2>Edit Provider</h2>

    <form action="{{ route('providers.update', $provider->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="name">Provider Name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $provider->name) }}" required>
        </div>

        <div class="form-group">
            <label for="extension">Provider Extension</label>
            <input type="text" class="form-control" id="extension" name="extension" value="{{ old('extension', $provider->extension) }}" required>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Update</button>
    </form>
</div>
@endsection
