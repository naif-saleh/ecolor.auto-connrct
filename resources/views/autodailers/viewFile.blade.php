@extends('layout.master')
@section('title', 'View File')
@section('content')
    <div class="container mt-5">
        <h1 class="mb-4">Viewing File: {{ $file->file_name }}</h1>

        <div class="mb-3">
            <a href="{{ route('autodailers.providers') }}" class="btn btn-primary">Back to Providers</a>
        </div>

        <h4>File Data</h4>
        @if (count($data) > 0)
            <table class="table table-striped table-hover table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>Mobile</th>
                        <th>Provider</th>
                        <th>Extension</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $row)
                        @if (count($row) >= 3) <!-- Skip rows with insufficient data -->
                            <tr>
                                <td>{{ $row[0] }}</td>
                                <td>{{ $row[1] }}</td>
                                <td>{{ $row[2] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No data found in this file.</p>
        @endif
    </div>
@endsection
