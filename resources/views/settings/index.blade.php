<!-- resources/views/settings/index.blade.php -->
@extends('layout.main')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Call Time Settings</div>

                <div class="card-body">
                    {{-- Success Message --}}
                    @if (session('success_time'))
                    <div class="alert alert-success text-center" role="alert">
                        {{ session('success_time') }}
                    </div>
                    @endif

                    {{-- Warnings for Missing Initial Settings --}}
                    @if (!$callTimeStart || !$callTimeEnd)
                    <div class="alert alert-warning text-center" role="alert">
                        <strong>Initial Setup Required:</strong> Please set the allowed calling hours for the system.
                    </div>
                    @endif

                    @if (!$number_calls)
                    <div class="alert alert-warning text-center" role="alert">
                        <strong>Initial Setup Required:</strong> Please set the allowed calls count for the system.
                    </div>
                    @endif

                    {{-- Form --}}
                    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- Call Time Start -->
                        <div class="mb-4">
                            <label for="call_time_start" class="form-label fw-bold">Call Time Start</label>
                            <input id="call_time_start" type="time"
                                class="form-control @error('call_time_start') is-invalid @enderror"
                                name="call_time_start" value="{{ $callTimeStart ? substr($callTimeStart, 0, 5) : '' }}"
                                required>
                            @error('call_time_start')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <!-- Call Time End -->
                        <div class="mb-4">
                            <label for="call_time_end" class="form-label fw-bold">Call Time End</label>
                            <input id="call_time_end" type="time"
                                class="form-control @error('call_time_end') is-invalid @enderror" name="call_time_end"
                                value="{{ $callTimeEnd ? substr($callTimeEnd, 0, 5) : '' }}" required>
                            @error('call_time_end')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <!-- Number of Calls -->
                        <div class="mb-4">
                            <label for="number_calls" class="form-label fw-bold">Number of Calls</label>
                            <input id="number_calls" type="number"
                                class="form-control @error('number_calls') is-invalid @enderror" name="number_calls"
                                value="{{ $number_calls ?? '' }}" required>
                            @error('number_calls')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <!-- Logo Upload -->
                        <div class="mb-4">
                            <label for="logo" class="form-label fw-bold">Application Logo</label>
                            <input id="logo" type="file" class="form-control @error('logo') is-invalid @enderror"
                                name="logo" accept="image/*">
                            @error('logo')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror

                            <!-- Display Current Logo -->
                            @if($logo)
                            <div class="mt-3 text-center">
                                <img src="{{ asset('storage/' . $logo) }}" alt="Current Logo"
                                    class="rounded border shadow" width="120">
                            </div>
                            @endif
                        </div>

                        <!-- Submit Button -->
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary px-4">
                                {{ !$callTimeStart || !$callTimeEnd ? 'Save Initial Settings' : 'Update Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
<br>

@endsection