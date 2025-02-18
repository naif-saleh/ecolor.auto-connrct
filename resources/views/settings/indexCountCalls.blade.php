<!-- resources/views/settings/index.blade.php -->
@extends('layout.main')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mt-2">
                <div class="card-header">Number of Calls</div>

                <div class="card-body">
                    @if (session('success_count'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success_count') }}
                        </div>
                    @endif

                    @if (!$number_calls )
                        <div class="alert alert-warning" role="alert">
                            <strong>Initial Setup Required:</strong> Please set the allowed calls count for the system.
                        </div>
                    @endif

                    <form method="POST" action="{{route('settings.update.callsNumber')}}">
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
                                    {{ !$number_calls ? 'Save Initial Settings' : 'Update Settings' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
