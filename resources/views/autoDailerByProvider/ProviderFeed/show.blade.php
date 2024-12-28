@extends('layout.master')

@section('content')
    <div class="container mt-5">
        <h1 class="text-center text-primary mb-4">Feed File: {{ $feedFile->file_name }}</h1>

        <h3 class="text-center mb-4">Feeds</h3>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Mobile</th>
                        <th>Extension</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Date</th>
                        <th>state</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($feeds as $feed)
                        <tr>
                            <td>{{ $feed->mobile }}</td>
                            <td>{{ $feed->extension }}</td>
                            <td>{{ $feed->from }}</td>
                            <td>{{ $feed->to }}</td>
                            <td>{{ $feed->date }}</td>
                            <td>{{$feed->state}}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($feeds->isEmpty())
            <div class="alert alert-warning text-center mt-4">
                No feeds available for this file.
            </div>
        @endif
    </div>
@endsection
