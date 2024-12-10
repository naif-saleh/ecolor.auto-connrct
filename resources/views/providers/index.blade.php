@extends('layout.master')

@section('content')
    <h1>Providers</h1>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <a href="{{ route('providers.create') }}" class="btn btn-primary mb-3">Add New Provider</a>

    @if (count($providers) > 0)
        @foreach ($providers as $provider)
            <div class="card mb-3">
                <div class="card-body">
                    <h5>{{ $provider->name }}</h5>
                    <p>Extension: {{ $provider->extension }}</p>
                    <p>Created by: {{ $provider->user->name }}</p>
                    <p>Created At: {{ $provider->created_at }}</p>
                    <a href="{{ route('providers.edit', $provider->id) }}" class="btn btn-warning ">Edit</a>
                    <form action="{{ route('providers.destroy', $provider->id) }}" method="POST" class="d-inline" onsubmit="confirmDelete()">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        @endforeach
    @else
        <div class="alert alert-warning">No Porividers Add. Please Add Provides.</div>
    @endif

    <script>
        function confirmDelete() {
            return confirm("Are you sure you want to delete this provider?");
        }
    </script>
@endsection
