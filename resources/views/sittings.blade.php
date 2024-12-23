@extends('layout.master')

@section('title', 'CFD Settings')
@section('header', 'Manage CFD Settings')

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        @if (session('success'))
        <script>
            Swal.fire({
                title: 'Error!',
                text: "{{ session('success') }}",
                icon: 'error',
                confirmButtonText: 'OK'
            });
        </script>
    @endif
        <form method="POST" action="{{ route('settings.save') }}" class="needs-validation" novalidate>
            @csrf

            <!-- Auto Distributor -->
            <div class="form-check mb-3">
                <!-- Hidden input ensures `0` is sent if unchecked -->
                <input type="hidden" name="allow_calling" value="0">
                <input
                    type="checkbox"
                    class="form-check-input"
                    id="allow_calling"
                    name="allow_calling"
                    value="1"
                    {{ $settings->allow_calling ? 'checked' : '' }}>
                <label class="form-check-label" for="allow_calling">Auto Distributor</label>
            </div>

            <div class="form-check mb-3">
                <!-- Hidden input ensures `0` is sent if unchecked -->
                <input type="hidden" name="allow_auto_calling" value="0">
                <input
                    type="checkbox"
                    class="form-check-input"
                    id="allow_auto_calling"
                    name="allow_auto_calling"
                    value="1"
                    {{ $settings->allow_auto_calling ? 'checked' : '' }}>
                <label class="form-check-label" for="allow_auto_calling">AutoDialer IVR</label>
            </div>

            <!-- Start Time -->
            <div class="mb-3">
                <label for="cfd_start_time" class="form-label">Start Time (Hour)</label>
                <p class="text-muted small">Now the hour is {{ $currentHour }}</p>
                <select name="cfd_start_time" id="cfd_start_time" class="form-select" required>
                    @foreach($hours as $hour)
                        <option value="{{ $hour }}" {{ $settings->cfd_start_time == $hour ? 'selected' : '' }}>
                            {{ $hour }}
                        </option>
                    @endforeach
                </select>
                <div class="invalid-feedback">Please select a valid start time.</div>
            </div>

            <!-- End Time -->
            <div class="mb-3">
                <label for="cfd_end_time" class="form-label">End Time (Hour)</label>
                <select name="cfd_end_time" id="cfd_end_time" class="form-select" required>
                    @foreach($hours as $hour)
                        <option value="{{ $hour }}" {{ $settings->cfd_end_time == $hour ? 'selected' : '' }}>
                            {{ $hour }}
                        </option>
                    @endforeach
                </select>
                <div class="invalid-feedback">Please select a valid end time.</div>
            </div>

            <!-- Skip Friday -->
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="cfd_allow_friday" name="cfd_allow_friday" value="1" {{ $settings->cfd_allow_friday ? 'checked' : '' }}>
                <label class="form-check-label" for="cfd_allow_friday">Skip Friday</label>
            </div>

            <!-- Skip Saturday -->
            <div class="form-check mb-4">
                <input type="checkbox" class="form-check-input" id="cfd_allow_saturday" name="cfd_allow_saturday" value="1" {{ $settings->cfd_allow_saturday ? 'checked' : '' }}>
                <label class="form-check-label" for="cfd_allow_saturday">Skip Saturday</label>
            </div>
            <div class="form-check mb-4">
                <!-- Save Settings Button -->
                <button type="submit" class="btn btn-primary mb-2">Save Settings</button>

                <!-- Save Settings (Warning) Button -->
                <a href="{{route('settings.getCfdApi')}}" type="submit" class="btn btn-warning mb-2">View Json Response</a>
            </div>



        </form>
    </div>
</div>
@endsection
