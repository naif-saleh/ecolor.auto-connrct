@extends('layout.master')

@section('content')
<div class="container">
    <h1 class="my-4">{{ $provider->name }} Feeds</h1>

    <h2 class="h5">Existing Feeds:</h2>
    <ul class="list-group mb-4">
        @foreach($feeds as $feed)
            <li class="list-group-item">

                   Mobile: {{ $feed->mobile }} Extension: {{ $feed->extension }} State: {{ $feed->state }}

            </li>
        @endforeach
    </ul>


</div>
@endsection
