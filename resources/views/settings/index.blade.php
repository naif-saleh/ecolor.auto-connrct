<!-- resources/views/settings/index.blade.php -->
@extends('layout.main')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Call Time Settings</div>

                <div class="card-body">
                    @if (session('success_time'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success_time') }}
                        </div>
                    @endif

                    @if (!$callTimeStart || !$callTimeEnd)
                        <div class="alert alert-warning" role="alert">
                            <strong>Initial Setup Required:</strong> Please set the allowed calling hours for the system.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.update') }}">
                        @csrf

                        <div class="form-group row mb-3">
                            <label for="call_time_start" class="col-md-4 col-form-label text-md-right">Call Time Start</label>

                            <div class="col-md-6">
                                <input id="call_time_start" type="time" class="form-control @error('call_time_start') is-invalid @enderror"
                                       name="call_time_start" value="{{ $callTimeStart ? substr($callTimeStart, 0, 5) : '' }}" required>

                                @error('call_time_start')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-3">
                            <label for="call_time_end" class="col-md-4 col-form-label text-md-right">Call Time End</label>

                            <div class="col-md-6">
                                <input id="call_time_end" type="time" class="form-control @error('call_time_end') is-invalid @enderror"
                                       name="call_time_end" value="{{ $callTimeEnd ? substr($callTimeEnd, 0, 5) : '' }}" required>

                                @error('call_time_end')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ !$callTimeStart || !$callTimeEnd ? 'Save Initial Settings' : 'Update Settings' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card mt-2">
                <div class="card-header">Number of Calls</div>

                <div class="card-body">
                    @if (session('success_count'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success_count') }}
                        </div>
                    @endif

                    {{-- @if (!$callTimeStart || !$callTimeEnd)
                        <div class="alert alert-warning" role="alert">
                            <strong>Initial Setup Required:</strong> Please set the allowed calling hours for the system.
                        </div>
                    @endif --}}

                    <form method="POST" action="">
                        @csrf

                        <div class="form-group row mb-3">
                            <label for="number_calls" class="col-md-4 col-form-label text-md-right">Put Number of Calls</label>

                            <div class="col-md-6">
                                <input id="number_calls" type="number" class="form-control"
                                       name="number_calls" value="{{ $number_calls ? $number_calls : '' }}" required>

                                @error('number_calls')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>



                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ !$callTimeEnd ? 'Save Initial Settings' : 'Update Settings' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<br>

@endsection
