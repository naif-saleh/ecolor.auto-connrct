@extends('layout.master')

@section('content')
<h1>Create New Provider</h1>
<form action="{{ route('providers.store') }}" method="POST">
    @csrf
    <div class="form-group">
        <label for="name">Provider Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="extension">Extension</label>
        <input type="text" name="extension" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-success mt-2">Save</button>
</form>
@endsection
