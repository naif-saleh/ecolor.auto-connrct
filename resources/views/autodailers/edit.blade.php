@extends('layout.master')

@section('content')
<div class="container">
    <h1>Edit File: {{ $file->file_name }}</h1>

    <!-- Edit Form -->
    <form action="{{ route('autodailers.update', $file->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="file_name">File Name</label>
            <input type="text" name="file_name" class="form-control" value="{{ old('file_name', $file->file_name) }}" required>
        </div>

        <button type="submit" class="btn btn-primary">Update File Name</button>
    </form>

    <a href="{{ route('autodailers.index') }}" class="btn btn-secondary mt-3">Back to Files</a>
</div>
@endsection
