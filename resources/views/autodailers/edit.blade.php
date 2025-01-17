@extends('layout.master')

@section('content')
    <div class="container">
        {{-- <h1>Edit File: {{ $file->file_name }}</h1> --}}

        <form action="{{ route('autoDailer.update', $file->id) }}" method="POST">
            @csrf
            @method('PUT')

            <label for="mobile">Mobile:</label>
            <input type="text" name="mobile" value="{{ $file->mobile }}" required>

            <label for="provider">Provider:</label>
            <input type="text" name="provider" value="{{ $file->provider }}" required>

            <label for="extension">Extension:</label>
            <input type="text" name="extension" value="{{ $file->extension }}" required>

            <label for="from">From:</label>
            <input type="time" name="from" value="{{ $file->from }}" required>

            <label for="to">To:</label>
            <input type="time" name="to" value="{{ $file->to }}" required>

            <label for="date">Date:</label>
            <input type="date" name="date" value="{{ $file->date }}" required>

            <button type="submit">Update</button>
        </form>

    </div>
@endsection
