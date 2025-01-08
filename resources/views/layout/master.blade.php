<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Laravel Application')</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    {{-- Sweet Alert --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>


    @yield('style')
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        .report-container {
            margin-top: 20px;
        }

        .card-header {
            background-color: #007bff;
            color: #fff;
        }

        .stats-container {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="/manager/dashboard">
                    <img src="https://ejaada.sa/wp-content/uploads/thegem-logos/logo_dedfcfaee88a3f71b4ad05fab3d352a4_1x.png"
                        width="40" alt="">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                @if (Auth::check() && (Auth::user()->isSuperUser() || Auth::user()->isAdmin()))
                    <div class="collapse navbar-collapse" id="navbarNav">

                        <ul class="navbar-nav me-auto mb-2 mb-lg-0"> <!-- Left-aligned links -->

                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('manager.dashboard') }}">Manager</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('autodailers.files.index') }}">Auto Dailer</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{route('distributor.files.index')}}">Auto
                                    Distributer</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('users.index') }}">Users</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="activityDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    Reports
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="activityDropdown">

                                    <li>
                                        <a class="dropdown-item" href="{{ route('auto_dailer.report') }}">Auto
                                            Dailer</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('auto_distributer.report') }}">Auto
                                            Distributer</a>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="activityDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    Activity Logs
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="activityDropdown">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('system.activity.report') }}">System Logs
                                            Activity</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('users.activity.report') }}">User Logs
                                            Activity</a>
                                    </li>
                                </ul>
                            </li>
                        </ul>

                            @elseif (Auth::check() && (Auth::user()->isManagerUser()))
                            <ul class="navbar-nav me-auto mb-2 mb-lg-0"> <!-- Left-aligned links -->

                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('manager.dashboard') }}">Manager Statistics</a>
                                </li>

                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="activityDropdown" role="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        Reports
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="activityDropdown">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('users.activity.report') }}">User
                                                Activity</a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('auto_dailer.report') }}">Auto
                                                Dailer</a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('auto_distributer.report') }}">Auto
                                                Distributer</a>
                                        </li>
                                    </ul>
                                </li>


                @endif

                {{-- @if (Auth::check() && Auth::user()->isSuperUser())
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('users.index') }}">Users</a>
                    </li>
                    </ul>
                @endif --}}

                <ul class="navbar-nav ms-auto"> <!-- Right-aligned login/logout links -->
                    @if (Auth::check())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right"></i>
                                Login</a>
                        </li>
                    @endif
                </ul>
            </div>

    </div>
    </div>
    </nav>
    <div class="container py-5">

        @yield('content')
    </div>


    @yield('scripts')
    @stack('scripts')

    {{-- Java Script --}}
    {{-- <script>
        // Enable AJAX for the switch toggle
        document.getElementById('allowSwitch{{ $file->id }}').addEventListener('change', function() {
            let fileId = this.getAttribute('data-file-id');
            let checked = this.checked ? 1 : 0; // Determine the state of the switch

            // AJAX request to update the 'allow' field
            fetch("{{ route('autodailers.files.allow', '') }}/" + fileId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content')
                    },
                    body: JSON.stringify({
                        allow: checked
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Optionally update the label text after the switch is toggled
                        document.querySelector(`#allowSwitch${fileId} + label`).innerText = checked ?
                            'Allowed' : 'Disallowed';
                    } else {
                        alert('Error updating the file status.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });
    </script> --}}
    <script>
        // Delete Alert...................................................................................................
        function confirmDelete(extensionId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You won\'t be able to revert this!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Trigger form submission if confirmed
                    document.getElementById('delete-form-' + extensionId).submit();
                }
            });
        }




        // Download
        // Alert.....................................................................................................

        document.getElementById('download-csv-button').addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default action to manage it manually

            const url = this.href;

            Swal.fire({
                title: 'Preparing your file...',
                text: 'Please wait while we generate your CSV.',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Simulate file download process
            fetch(url)
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    } else {
                        throw new Error('Failed to download file');
                    }
                })
                .then(blob => {
                    const link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);
                    link.download = 'auto_distributor_report.csv';
                    link.click();

                    Swal.fire({
                        title: 'Download Ready!',
                        text: 'Your CSV file has been successfully downloaded.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while preparing your file. Please try again later.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            const tableRows = document.querySelectorAll('.user-row');

            // Function to filter rows
            function filterRows() {
                const searchValue = searchInput.value.toLowerCase();

                tableRows.forEach(row => {
                    const name = row.querySelector('.name').textContent.toLowerCase();
                    const lastName = row.querySelector('.lastName').textContent.toLowerCase();

                    if (name.includes(searchValue) || lastName.includes(searchValue)) {
                        row.style.display = ''; // Show row
                    } else {
                        row.style.display = 'none'; // Hide row
                    }
                });
            }

            // Add event listener to the search input for dynamic filtering
            searchInput.addEventListener('input', filterRows);
        });
    </script>



    {{-- Delete All Alert --}}
    <script>
        document.getElementById('delete-all-users-button').addEventListener('click', function(e) {
            e.preventDefault(); // Prevent the button's default action

            Swal.fire({
                title: 'Are you sure?',
                text: "This will delete all users permanently!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete all!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    document.getElementById('delete-all-users-form').submit();
                }
            });
        });
    </script>

    <!-- Bootstrap JS (optional, for interactivity) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>

    {{-- Sweet Alert --}}
    @if (session('success'))
        <script>
            window.onload = function() {
                Swal.fire({
                    title: 'Success!',
                    text: "{{ session('success') }}",
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
            };
        </script>
    @endif

    @if (session('wrong'))
        <script>
            window.onload = function() {
                Swal.fire({
                    title: 'Wrong!',
                    text: "{{ session('wrong') }}",
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            };
        </script>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>

</html>
