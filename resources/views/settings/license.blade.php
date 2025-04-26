<!-- resources/views/settings/index.blade.php -->
@extends('layout.main')
<!-- Toastr CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mt-4">
                <div class="card-header">License Settings</div>

                <div class="card-body">
                    {{-- Success Message --}}
                    @if (session('success_licesns'))
                    <div class="alert alert-success text-center" role="alert">
                        {{ session('success_licesns') }}
                    </div>
                    @endif

                    {{-- Warnings for Missing License Key --}}
                    @if (!$licenseKey)
                    <div class="alert alert-warning text-center" role="alert">
                        <strong>Initial Setup Required:</strong> Please set the allowed License Key.
                    </div>
                    @endif


                    {{-- Form --}}
                    <form method="POST" action="{{ route('PostLicen') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- License Key -->
                        <div class="mb-4">
                            <label for="license_key" class="form-label fw-bold">License Key</label>
                            <input id="license_key" type="text"
                                class="form-control @error('license_key') is-invalid @enderror" name="license_key"
                                value="{{ $licenseKey ?? '' }}" required autocomplete="license_key" autofocus>
                            @error('license_key')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>




                        <!-- Logo Upload -->


                        <!-- Submit Button -->
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary px-4">
                                {{ !$licenseKey ? 'Save License Key' : 'Update License Key' }}
                            </button>
                        </div>
                    </form>

                    @if(isset($licenseInfo))
                    <div class="mt-5">
                        <h4 class="mb-4 text-primary fw-bold">
                            <i class="fas fa-id-card-alt me-2"></i> License Overview
                        </h4>

                        <div class="row g-4">
                            {{-- GENERAL LICENSE INFO --}}
                            <div class="col-12">
                                <div class="card shadow-sm border-0 h-100">
                                    <div class="card-header bg-secondary text-white fw-bold">
                                        <i class="fas fa-info-circle me-2"></i> General License Info
                                    </div>
                                    <div class="card-body bg-light">
                                        <div class="row gy-3">
                                            <div class="col-md-6">
                                                <p class="mb-1 text-muted">Company</p>
                                                <span class="fw-semibold text-dark">{{ $licenseInfo['company_name'] }}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1 text-muted">License Key</p>
                                                <span class="fw-semibold text-dark">{{ $licenseInfo['license_key'] }}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1 text-muted">Status</p>
                                                <span class="fw-semibold {{ $isExpaired ? 'text-danger' : (!$isActive ? 'text-warning' : 'text-success') }}">
                                                    <i class="fa {{ $isExpaired ? 'fa-x' : (!$isActive ? 'fa-solid fa-triangle-exclamation' : 'fa-check') }} me-1"></i> {{ $isExpaired ? 'Expaired' : (!$isActive ? 'Inactive' : 'Active') }}
                                                </span>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1 text-muted">Duration</p>
                                                <div class="d-flex flex-col gap-2">
                                                    <span class="fw-semibold text-dark"><b>start: </b>{{ $licenseInfo['start_date'] }}</span>
                                                    <span class="fw-semibold text-dark"><b>end: </b>{{ $licenseInfo['end_date'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- DIALER INFO --}}
                            <div class="col-md-6">
                                <div class="card shadow-sm border-0 h-100">
                                    <div class="card-header bg-primary text-white fw-bold">
                                        <i class="fas fa-phone-volume me-2"></i> Dialer Module
                                    </div>
                                    <div class="card-body bg-light">
                                        <div class="row gy-3">
                                            <div class="col-12">
                                                <p class="mb-1 text-sm">Max Calls Allowed</p>
                                                <span class="badge rounded-pill {{ $isExpaired ? 'bg-danger' : (!$isActive ? 'bg-warning' : ($maxDialCalls > 0 ? 'bg-success' : ($maxDialCalls == 0 ? 'bg-danger' : 'bg-warning'))) }}">{{ $isExpaired ? 'License Expired' : (!$isActive ? 'Inactive License' : ($enableDial == 0 ? 'enabled' : ($maxDialCalls > 0 ? $maxDialCalls : ($maxDialCalls == 0 ? 'limit reached' : 'Inactive')))) }}</span>
                                            </div>
                                            <div class="col-12">
                                                <p class="mb-1 text-sm">Max Providers Allowed</p>
                                                <span class="badge rounded-pill {{ $isExpaired ? 'bg-danger' : (!$isActive ? 'bg-warning' : ($maxProviders > 0 ? 'bg-success' : ($maxProviders == 0 ? 'bg-danger' : 'bg-warning'))) }}">{{ $isExpaired ? 'License Expired' : (!$isActive ? 'Inactive License' : ($enableDial == 0 ? 'enabled' : ($maxProviders > 0 ? $maxProviders : ($maxProviders == 0 ? 'limit reached' : 'Inactive')))) }}</span>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- DISTRIBUTOR INFO --}}
                            <div class="col-md-6">
                                <div class="card shadow-sm border-0 h-100">
                                    <div class="card-header bg-success text-white fw-bold">
                                        <i class="fas fa-project-diagram me-2"></i> Distributor Module
                                    </div>
                                    <div class="card-body bg-light">
                                        <div class="row gy-3">
                                            <div class="col-12">
                                                <p class="mb-1 text-muted">Max Calls Allowed</p>
                                                <span class="badge rounded-pill {{ $isExpaired ? 'bg-danger' : (!$isActive ? 'bg-warning' : ($maxDistCalls > 0 ? 'bg-success' : ($maxDistCalls == 0 ? 'bg-danger' : 'bg-warning'))) }}">{{ $isExpaired ? 'License Expired' : (!$isActive ? 'Inactive License' : ($enableDist == 0 ? 'enabled' : ($maxDistCalls > 0 ? $maxDistCalls : ($maxDistCalls == 0 ? 'limit reached' : 'Inactive')))) }}</span>

                                             </div>
                                             <div class="col-12">
                                                <p class="mb-1 text-muted">Max Agents Allowed</p>
                                                <span class="badge rounded-pill {{ $isExpaired ? 'bg-danger' : (!$isActive ? 'bg-warning' : ($maxAgents > 0 ? 'bg-success' : ($maxAgents == 0 ? 'bg-danger' : 'bg-warning'))) }}">{{ $isExpaired ? 'License Expired' : (!$isActive ? 'Inactive License' : ($enableDist == 0 ? 'enabled' : ($maxAgents > 0 ? $maxAgents : ($maxAgents == 0 ? 'limit reached' : 'Inactive')))) }}</span>

                                             </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Upgrade Suggestion --}}
                        @if(isset($totalAgents) && $totalAgents > $maxAgents)
                            <div class="mt-4 text-end">
                                <a href="#" class="btn btn-outline-success">
                                    <i class="fa fa-arrow-up me-1"></i> Upgrade License
                                </a>
                            </div>
                        @endif
                    </div>

                @endif

                </div>

            </div>
        </div>
    </div>
</div>
<br>
<!-- jQuery (required for Toastr) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Toastr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
           // Configure Toastr global options
           toastr.options = {
        closeButton: true,
        newestOnTop: true,
        progressBar: true,
        positionClass: "toast-top-right",
        preventDuplicates: false,
        onclick: null,
        showDuration: "300",
        hideDuration: "1000",
        timeOut: "5000",
        extendedTimeOut: "1000",
        showEasing: "swing",
        hideEasing: "linear",
        showMethod: "fadeIn",
        hideMethod: "fadeOut"
    };

    // Show toasts based on URL parameters or session messages
    document.addEventListener("DOMContentLoaded", function() {
        // Check for flash messages
        @if(session('success'))
            toastr.success("{{ session('success') }}");
        @endif

        @if(session('error'))
            toastr.error("{{ session('error') }}");
        @endif

        @if(session('warning'))
            toastr.warning("{{ session('warning') }}");
        @endif

        @if(session('info'))
            toastr.info("{{ session('info') }}");
        @endif
    });
</script>

@endsection
