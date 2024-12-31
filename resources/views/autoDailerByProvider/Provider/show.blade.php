@extends('layout.master')

@section('content')
    <div class="container mt-5">
        <h1 class="text-center text-primary mb-4">Provider: {{ $provider->name }}</h1>

        <h3 class="text-center mb-4">Feed Files</h3>

        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($provider->feedFiles as $feedFile)
                        <tr>
                            <td>{{ $feedFile->file_name }}</td>
                            <td>{{ \Carbon\Carbon::parse($feedFile->from)->addHours(3)->format('H:i:s') }}</td>
                            <td>{{ \Carbon\Carbon::parse($feedFile->from)->addHours(3)->format('H:i:s') }}</td>
                            <td>{{ $feedFile->date }}</td>
                            <td>{{ $feedFile->on ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <a href="{{ route('autoDialerProviders.show', $feedFile->id) }}" class="btn btn-primary btn-sm">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>


        @if ($provider->feedFiles->isEmpty())
            <div class="alert alert-warning text-center mt-4">
                No feed files found for this provider.
            </div>
        @endif
    </div>
@endsection
