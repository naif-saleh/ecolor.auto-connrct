@extends('layout.master')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-primary">Auto Distributers Users</h1>
        <a href="{{ route('autoDistributers.create') }}" class="btn btn-success">Add New User</a>
    </div>

    @if($providers->isEmpty())
        <div class="alert alert-info">
            No Auto Distributers User found. Click "Create New Provider" to add one.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="bg-primary text-white">
                    <tr>
                        <th>Name</th>
                        <th>Extension</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($providers as $provider)
                        <tr>
                            <td>{{ $provider->name }}</td>
                            <td>{{ $provider->extension }}</td>
                            <td>
                                <a href="{{ route('autoDistributers.show', $provider->id) }}" class="btn btn-info btn-sm">Feeds</a>
                                <a href="{{ route('autoDistributers.edit', $provider->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                <a href="{{ route('autoDistributers.createFeed', $provider->id) }}" class="btn btn-primary btn-sm">Add Feed</a>

                                <form action="{{ route('autoDistributers.destroy', $provider->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this provider?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
