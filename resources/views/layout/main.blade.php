<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title')</title>
    {{-- Tap Icon --}}
    <link rel="icon"
        href="https://ejaada.sa/wp-content/uploads/thegem-logos/logo_dedfcfaee88a3f71b4ad05fab3d352a4_1x.png"
        type="image/png">
        {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- plugins:css -->
    <link rel="stylesheet" href="{{ url('assets/vendors/feather/feather.css') }}">
    <link rel="stylesheet" href="{{ url('assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ url('assets/vendors/ti-icons/css/themify-icons.css') }} ">
    <link rel="stylesheet" href="{{ url('assets/vendors/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ url('assets/vendors/typicons/typicons.css') }}">
    <link rel="stylesheet" href="{{ url('assets/vendors/simple-line-icons/css/simple-line-icons.css') }}">
    <link rel="stylesheet" href="{{ url('assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ url('assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css') }}">
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <link rel="stylesheet" href="{{ url('assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ url('assets/js/select.dataTables.min.css') }}">
    <!-- End plugin css for this page -->
    <!-- Sweet Alert -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.6/dist/sweetalert2.min.css">
    <!-- inject:css -->
    <link rel="stylesheet" href="{{ url('assets/css/style.css') }}">
    <!-- endinject -->


</head>

<body class="with-welcome-text">
    <div class="container-scroller">

        <!-- partial:partials/_navbar.html -->
        <nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-between">
                <div class="me-3">
                    <button class="navbar-toggler navbar-toggler align-self-center" type="button"
                        data-bs-toggle="minimize">
                        <span class="icon-menu"></span>
                    </button>
                </div>

            </div>
            <div class="navbar-menu-wrapper d-flex align-items-top justify-content-between">
                <ul class="navbar-nav">
                    <div>

                        <a class="navbar-brand brand-logo-mini" href="{{ route('index.page') }}">
                            <img src="https://ejaada.sa/wp-content/uploads/thegem-logos/logo_dedfcfaee88a3f71b4ad05fab3d352a4_1x.png"
                                width="30" alt="ejaada-logo">
                        </a>
                    </div>
                    <li class="nav-item fw-semibold d-none d-lg-block ms-0">
                        @php
                            $currentHour = now()->format('H');
                        @endphp

                        <h1 class="welcome-text">
                            @if ($currentHour < 12)
                                Good Morning, <span class="text-black fw-bold">{{ Auth::user()->name }}</span>
                            @else
                                Good Evening, <span class="text-black fw-bold">{{ Auth::user()->name }}</span>
                            @endif
                        </h1>

                        {{-- <h3 class="welcome-sub-text">Your performance summary this week </h3> --}}
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto"> <!-- Right-aligned login/logout links -->
                    @if (Auth::check())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </a>
                        </li>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST"
                            style="display: none;">
                            @csrf
                        </form>

                    @endif
                </ul>

            </div>
        </nav>
        <!-- partial -->
        <div class="container-fluid page-body-wrapper">
            <!-- partial:partials/_sidebar.html -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">
                    @if (Auth::check() && (Auth::user()->isSuperUser() || Auth::user()->isAdmin()))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('index.page') }}">
                                <i class="mdi mdi-grid-large menu-icon"></i>
                                <span class="menu-title">Dashboard</span>
                            </a>
                        </li>

                        <li class="nav-item nav-category">Systems Call</li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false"
                                aria-controls="ui-basic">
                                <i class="menu-icon mdi mdi-floor-plan"></i>
                                <span class="menu-title">Systems</span>
                                <i class="menu-arrow"></i>
                            </a>
                            <div class="collapse" id="ui-basic">
                                <ul class="nav flex-column sub-menu">
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('providers.index') }}">Auto
                                            Dialer</a></li>
                                    <li class="nav-item"> <a class="nav-link" href="{{ route('users.index') }}">Auto
                                            Distributor</a></li>

                                </ul>
                            </div>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#form-elements" aria-expanded="false"
                                aria-controls="form-elements">
                                <i class="menu-icon mdi mdi-phone"></i>

                                <span class="menu-title">Call Reports</span>
                                <i class="menu-arrow"></i>
                            </a>
                            <div class="collapse" id="form-elements">
                                <ul class="nav flex-column sub-menu">
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('auto_dailer.report') }}">Dialer Calls</a></li>
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('auto_distributer.report') }}">Distributor Calls</a></li>
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('evaluation') }}">Evaluation</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#charts" aria-expanded="false"
                                aria-controls="charts">
                                <i class="menu-icon mdi mdi-message-text"></i>
                                <span class="menu-title">System Reports</span>


                                <i class="menu-arrow"></i>
                            </a>
                            <div class="collapse" id="charts">
                                <ul class="nav flex-column sub-menu">
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('system.activity.report') }}">System Log Report</a></li>
                                    <li class="nav-item"> <a class="nav-link"
                                            href="{{ route('users.activity.report') }}">User Log Report</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('users.system.index') }}" >
                              <i class="menu-icon mdi mdi-account-circle-outline"></i>
                              <span class="menu-title">User Pages</span>

                            </a>

                          </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#form-elements"
                                aria-expanded="false" aria-controls="form-elements">
                                <i class="menu-icon mdi mdi-phone"></i>

                                <span class="menu-title">Call Reports</span>
                                <i class="menu-arrow"></i>
                            </a>
                            <div class="collapse" id="form-elements">
                                <ul class="nav flex-column sub-menu">
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('auto_dailer.report') }}">Dialer Calls</a></li>
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('auto_distributer.report') }}">Distributor Calls</a></li>
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('evaluation') }}">Evaluation</a></li>
                                </ul>
                            </div>
                        </li>
                    @endif
                </ul>

            </nav>
            <!-- partial -->
            @yield('content')
            <!-- main-panel ends -->
        </div>
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- plugins:js -->
    <script src="{{ url('assets/vendors/js/vendor.bundle.base.js') }}"></script>
    <script src="{{ url('assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <script src="{{ url('assets/vendors/chart.js/chart.umd.js') }}"></script>
    <script src="{{ url('assets/vendors/progressbar.js/progressbar.min.js') }}"></script>
    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="{{ url('assets/js/off-canvas.js') }}"></script>
    <script src="{{ url('assets/js/template.js') }}"></script>
    <script src="{{ url('assets/js/settings.js') }}"></script>
    <script src="{{ url('assets/js/hoverable-collapse.js') }}"></script>
    <script src="{{ url('assets/js/todolist.js') }} "></script>
    <!-- endinject -->
    <!-- Custom js for this page-->
    <script src="{{ url('assets/js/jquery.cookie.js') }} " type="text/javascript"></script>
    <script src="{{ url('assets/js/dashboard.js') }} "></script>
    <!-- <script src="assets/js/Chart.roundedBarCharts.js"></script> -->
    <!-- End custom js for this page-->

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.6/dist/sweetalert2.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <!-- Bootstrap & Dropzone JS (Include before closing body tag) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>

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

</body>

</html>
