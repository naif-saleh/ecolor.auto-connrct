@extends('layout.master')

@section('content')
<div class="container">
    <h1>Feed Details</h1>

    <p><strong>Mobile:</strong> {{ $feed->mobile }}</p>
    <p><strong>Extension:</strong> {{ $feed->extension }}</p>
    <p><strong>From:</strong> {{ $feed->from }}</p>
    <p><strong>To:</strong> {{ $feed->to }}</p>
    <p><strong>Date:</strong> {{ $feed->date }}</p>
    <p><strong>Status:</strong> {{ $feed->on ? 'On' : 'Off' }}</p>

    <a href="{{ route('autoDialerProviders.show', $feed->provider_id) }}">Back to Provider</a>
</div>
@endsection
