@extends('layout.master')
@section('content')
    <div class="container">
        <h2>File: {{ $file->file_name }}</h2>

        @if (!empty($data))
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Mobile Number</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row[0] ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No data found in this file.</p>
        @endif

        <a href="{{ route('provider.files.index', $file->provider_id) }}" class="btn btn-primary">Back</a>
    </div>
@endsection
