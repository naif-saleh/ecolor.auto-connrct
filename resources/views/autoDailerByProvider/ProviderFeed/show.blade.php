@extends('layout.master')

@section('content')
    <div class="container mt-5">


        <h3>Feed File: {{ $feedFile->file_name }}</h3>
        <h5>From: {{ \Carbon\Carbon::parse($feedFile->from)->addHours(3)->format('H:i:s') }} | To: {{ \Carbon\Carbon::parse($feedFile->to)->addHours(3)->format('H:i:s') }} | Date: {{ $feedFile->date }}</h5>

        <div class="mb-3">
            <h4>Mobile Numbers</h4>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Mobile</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($feeds as $feed)
                        <tr>
                            <td>{{ $feed->mobile }}</td>
                            <td>{{ $feed->state }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>


        <a href="{{ route('autoDialerProviders.show', $feedFile->id) }}" class="btn btn-secondary">Back to
            Provider</a>
    </div>
@endsection
