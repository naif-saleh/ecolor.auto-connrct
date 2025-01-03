@extends('layout.master')

@section('content')
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="display-4">Auto Distributerer Users</h1>
            <a href="{{ route('auto_distributerer_extensions.create') }}" class="btn btn-primary btn-lg">
                <i class="bi bi-plus-circle"></i> Add New Extension
            </a>
            <a href="{{ route('auto_distributerer_extensions.import') }}" class="btn btn-success btn-lg">
                <i class="bi bi-plus-circle"></i> Import users
            </a>
        </div>

        @if($extensions->isEmpty())
        <div class="alert alert-warning">
            No Auto Distributerer Users found. Click "Create New User" to add one.
        </div>
        @else
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Extension</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($extensions as $extension)
                            <tr>
                                <td>{{ $extension->name }}</td>
                                <td>{{ $extension->extension }}</td>
                                <td>{{ $extension->user->name }}</td>
                                <td class="d-flex justify-content-start">
                                    <a href="{{ route('auto_distributerer_extensions.show', $extension->id) }}" class="btn btn-info btn-sm me-2">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="{{ route('auto_distributerer_extensions.edit', $extension->id) }}" class="btn btn-warning btn-sm me-2">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="{{ route('auto_distributerer_extensions.createFeed', $extension->id) }}" class="btn btn-primary btn-sm me-2">
                                        <i class="bi bi-plus-lg"></i> Add Feed
                                    </a>
                                    <form action="{{ route('auto_distributerer_extensions.destroy', $extension->id) }}" method="POST" class="d-inline" id="delete-form-{{ $extension->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete({{ $extension->id }})">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
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


