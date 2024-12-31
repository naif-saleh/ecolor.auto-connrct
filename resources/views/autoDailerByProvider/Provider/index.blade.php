@extends('layout.master')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="display-4">Auto Dialer Providers </h1>
        <a href="{{ route('autoDialerProviders.create') }}" class="btn btn-primary btn-lg">
            <i class="bi bi-plus-circle"></i> Add New Extension
        </a>
    </div>

    @if($providers->isEmpty())
        <div class="alert alert-warning">
            No Auto Dialer Providers found. Click "Create New Provider" to add one.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark text-white">
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
                                <a href="{{ route('autoDialerProvider.show', $provider->id) }}" class="btn btn-info btn-sm"> <i class="bi bi-eye"></i> View</a>
                                <a href="{{ route('autoDialerProviders.edit', $provider->id) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                                <a href="{{ route('autoDialerProviders.createFeed', $provider->id) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Feed</a>

                                <form action="{{ route('autoDialerProviders.destroy', $provider->id) }}" method="POST" class="d-inline" id="delete-form-{{ $provider->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete({{ $provider->id }})">
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
