@extends('layout.main')

@section('content')
    <div class="container mt-5">
        <h1 class="text-center text-primary mb-4">User: {{ $agent->name }}</h1>

        <div class="mb-3">
            <h3>Uploaded Feed Files</h3>
            <table class="table table-bordered">
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
                    @foreach ($agent->feedFiles as $feedFile)
                        <tr>
                            <td>{{ $feedFile->file_name }}</td>
                            <td>{{ \Carbon\Carbon::parse($feedFile->from)->format('H:i:s') }}</td>
                            <td>{{ \Carbon\Carbon::parse($feedFile->to)->format('H:i:s') }}</td>
                            <td>{{ $feedFile->date }}</td>
                            <td>{{ $feedFile->on ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <!-- Button to view data inside the feed file -->
                                <a href="{{ route('auto_distributer_extensions.viewFeedData', ['extensionId' => $agent->id, 'feedFileId' => $feedFile->id]) }}"
                                    class="btn btn-info btn-sm">View Data</a>
                            </td>
                        </tr>

                </tbody>
                @endforeach
            </table>
        </div>
        @if ($agent->feedFiles->isEmpty())
            <div class="alert alert-warning text-center mt-4">
                No feed files found for this provider.
            </div>
        @endif
    </div>
@endsection
