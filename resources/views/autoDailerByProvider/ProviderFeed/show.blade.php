@extends('layout.master')
@section('content')
    <div class="container">
        <h2>File: {{ $file->file_name }}</h2>

        @if (!empty($file))
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Mobile Number</th>
                        <th>state</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->mobile ?? 'N/A' }}</td>
                            <td>{{ $row->state ?? 'N/A' }}</td>
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
