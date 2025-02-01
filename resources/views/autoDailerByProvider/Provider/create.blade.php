@extends('layout.master')

@section('content')
<div class="container">
    <h2 class="mb-4">Add Provider</h2>

    <form action="{{ route('providers.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="providerName" class="form-label">Provider Name</label>
            <input type="text" class="form-control" id="providerName" name="name" required>
        </div>
        <div class="mb-3">
            <label for="providerExtension" class="form-label">Extension</label>
            <input type="text" class="form-control" id="providerExtension" name="extension">
        </div>
        <button type="submit" class="btn btn-success">Save</button>
    </form>
</div>
@endsection
