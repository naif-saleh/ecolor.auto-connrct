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
    <link rel="stylesheet" href="{{ asset('assets/vendors/feather/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/ti-icons/css/themify-icons.css') }} ">
    <link rel="stylesheet" href="{{ asset('assets/vendors/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/typicons/typicons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/simple-line-icons/css/simple-line-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css') }}">
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/js/select.dataTables.min.css') }}">
    <!-- End plugin css for this page -->
    <!-- Sweet Alert -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.6/dist/sweetalert2.min.css">
    <!-- inject:css -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
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
                                width="40" alt="ejaada-logo">
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
                        {{-- <li class="nav-item dropdown">
                            <a class="nav-link count-indicator" id="notificationDropdown" href="#"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="icon-bell"></i>
                                <span class="count"></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list pb-0 show"
                                aria-labelledby="notificationDropdown">
                                <a class="dropdown-item py-3 border-bottom">
                                    <p class="mb-0 fw-medium float-start">You have 4 new notifications </p>
                                    <span class="badge badge-pill badge-primary float-end">View all</span>
                                </a>
                                <a class="dropdown-item preview-item py-3">
                                    <div class="preview-thumbnail">
                                        <i class="mdi mdi-alert m-auto text-primary"></i>
                                    </div>
                                    <div class="preview-item-content">
                                        <h6 class="preview-subject fw-normal text-dark mb-1">Application Error</h6>
                                        <p class="fw-light small-text mb-0"> Just now </p>
                                    </div>
                                </a>
                                <a class="dropdown-item preview-item py-3">
                                    <div class="preview-thumbnail">
                                        <i class="mdi mdi-lock-outline m-auto text-primary"></i>
                                    </div>
                                    <div class="preview-item-content">
                                        <h6 class="preview-subject fw-normal text-dark mb-1">Settings</h6>
                                        <p class="fw-light small-text mb-0"> Private message </p>
                                    </div>
                                </a>
                                <a class="dropdown-item preview-item py-3">
                                    <div class="preview-thumbnail">
                                        <i class="mdi mdi-airballoon m-auto text-primary"></i>
                                    </div>
                                    <div class="preview-item-content">
                                        <h6 class="preview-subject fw-normal text-dark mb-1">New user registration</h6>
                                        <p class="fw-light small-text mb-0"> 2 days ago </p>
                                    </div>
                                </a>
                            </div>
                        </li> --}}


                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </a>
                        </li>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
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
                            <a class="nav-link" data-bs-toggle="collapse" href="#form-elements"
                                aria-expanded="false" aria-controls="form-elements">
                                <i class="menu-icon mdi mdi-phone"></i>

                                <span class="menu-title">Call Reports</span>
                                <i class="menu-arrow"></i>
                            </a>
                            <div class="collapse" id="form-elements">
                                <ul class="nav flex-column sub-menu">
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('auto_dailer.report') }}">Auto Dailer Report</a></li>
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('auto_distributer.report') }}">Auto Distributor Report</a>
                                    </li>
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('evaluation') }}">Evaluation Report</a></li>
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
                            <a class="nav-link" data-bs-toggle="collapse" href="#settings" aria-expanded="false"
                                aria-controls="settings">
                                <i class="mdi mdi-cog-outline"></i>

                                <span class="menu-title">Settings</span>
                                <i class="menu-arrow"></i>
                            </a>
                            <div class="collapse" id="settings">
                                <ul class="nav flex-column sub-menu">
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('settings.index') }}">System Time Calls</a></li>
                                    <li class="nav-item"><a class="nav-link"
                                            href="{{ route('settings.indexCountNumbers') }}">Count Calls</a></li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('users.system.index') }}">

                                            <span class="menu-title">Manage Users</span>

                                        </a>

                                    </li>

                                </ul>
                            </div>
                        </li>
                    @elseif (Auth::check() && Auth::user()->isManagerUser())
                        <nav class="sidebar sidebar-offcanvas" id="sidebar">
                            <ul class="nav">
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('index.page') }}">
                                        <i class="mdi mdi-grid-large menu-icon"></i>
                                        <span class="menu-title">Dashboard</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="collapse" href="#form-elements"
                                        aria-expanded="false" aria-controls="form-elements">
                                        <i class="menu-icon mdi mdi-phone"></i>

                                        <span class="menu-title">Dialer Reports</span>
                                        <i class="menu-arrow"></i>
                                    </a>
                                    <div class="collapse" id="form-elements">
                                        <ul class="nav flex-column sub-menu">
                                            <li class="nav-item"><a class="nav-link"
                                                    href="{{ route('manager.dailer.report.providers') }}">Dialer
                                                    Provider</a></li>
                                            <li class="nav-item"><a class="nav-link"
                                                    href="{{ route('manager.dailer.report.compaign') }}">Dialer
                                                    Compaign</a></li>
                                        </ul>
                                    </div>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="collapse" href="#form-elements"
                                        aria-expanded="false" aria-controls="form-elements">
                                        <i class="menu-icon mdi mdi-phone"></i>

                                        <span class="menu-title">Distributor Reports</span>
                                        <i class="menu-arrow"></i>
                                    </a>
                                    <div class="collapse" id="form-elements">
                                        <ul class="nav flex-column sub-menu">
                                            <li class="nav-item"><a class="nav-link"
                                                    href="{{ route('manager.autodistributor.report.providers') }}">Distributor
                                                    Provider</a></li>
                                            <li class="nav-item"><a class="nav-link"
                                                    href="{{ route('manager.autodistributor.report.compaign') }}">Distributor
                                                    Compaign</a></li>

                                        </ul>
                                    </div>
                                </li>
                            </ul>

                        </nav>
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
    <script src="{{ asset('assets/vendors/js/vendor.bundle.base.js') }}"></script>
    <script src="{{ asset('assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <script src="{{ asset('assets/vendors/chart.js/chart.umd.js') }}"></script>
    <script src="{{ asset('assets/vendors/progressbar.js/progressbar.min.js') }}"></script>
    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="{{ asset('assets/js/off-canvas.js') }}"></script>
    <script src="{{ asset('assets/js/template.js') }}"></script>
    <script src="{{ asset('assets/js/settings.js') }}"></script>
    <script src="{{ asset('assets/js/hoverable-collapse.js') }}"></script>
    <script src="{{ asset('assets/js/todolist.js') }} "></script>
    <!-- endinject -->
    <!-- Custom js for this page-->
    <script src="{{ asset('assets/js/jquery.cookie.js') }} " type="text/javascript"></script>
    <script src="{{ asset('assets/js/dashboard.js') }} "></script>
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


    {{-- <script>
        let notify = document.getElementById("notificationDropdown");
        let
        notify.addEventListener("click", function() {
            alert("Notification clicked!");
        });
    </script> --}}


</body>

</html>
