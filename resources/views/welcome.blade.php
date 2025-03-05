@extends('layout.main')
@section('title', 'Ecolor | Dashboard')

@section('content')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-sm-12">
                    <div class="home-tab">

                        <div class="tab-content tab-content-basic">
                            <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="statistics-details d-flex align-items-center justify-content-between">
                                            <div>
                                                <p class="statistics-title">Providers</p>
                                                <h3 class="rate-percentage">{{$providers}}</h3>
                                                <p class="text-danger d-flex"><i
                                                        class="mdi mdi-menu-down"></i><span></span></p>
                                            </div>
                                            <div>
                                                <p class="statistics-title">Agent Working</p>
                                                <h3 class="rate-percentage">{{$agents}}</h3>
                                                <p class="text-success d-flex">
                                            </div>
                                            <div>
                                                <p class="statistics-title">Agent not Working</p>
                                                <h3 class="rate-percentage">{{$agents_not_working}}</h3>
                                                <p class="text-success d-flex">
                                            </div>
                                            <div>
                                                <p class="statistics-title">Dialer Files</p>
                                                <h3 class="rate-percentage">{{$dialFeeds}}</h3>
                                                <p class="text-danger d-flex">
                                            </div>
                                            <div class="d-none d-md-block">
                                                <p class="statistics-title">Distributo Files</p>
                                                <h3 class="rate-percentage">{{$distFeeds}}</h3>
                                                <p class="text-success d-flex">
                                            </div>
                                            {{-- <div class="d-none d-md-block">
                                                <p class="statistics-title">Avg. Time on Site</p>
                                                <h3 class="rate-percentage">2m:35s</h3>
                                                <p class="text-success d-flex">
                                            </div> --}}
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-8 d-flex flex-column">
                                        <div class="row flex-grow">
                                            <div class="col-12 col-lg-4 col-lg-12 grid-margin stretch-card">
                                                <div class="card card-rounded">
                                                    <div class="card-body">
                                                        <div class="d-sm-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h4 class="card-title card-title-dash">
                                                                    Performance Line Chart</h4>
                                                                <h5 class="card-subtitle card-subtitle-dash">
                                                                    Lorem Ipsum is simply dummy text of the
                                                                    printing</h5>
                                                            </div>
                                                            <div id="performanceLine-legend"></div>
                                                        </div>
                                                        <div class="chartjs-wrapper mt-4">
                                                            <canvas id="performanceLine" width=""></canvas>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 d-flex flex-column">
                                        <div class="row flex-grow">
                                            <div class="col-md-6 col-lg-12 grid-margin stretch-card">
                                                <div class="card bg-primary card-rounded">
                                                    <div class="card-body pb-0">
                                                        <h4 class="card-title card-title-dash text-white mb-4">
                                                            Status Summary</h4>
                                                        <div class="row">
                                                            <div class="col-sm-4">
                                                                <p class="status-summary-ight-white mb-1">
                                                                    Closed Value</p>
                                                                <h2 class="text-info">357</h2>
                                                            </div>
                                                            <div class="col-sm-8">
                                                                <div class="status-summary-chart-wrapper pb-4">
                                                                    <canvas id="status-summary"></canvas>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-lg-12 grid-margin stretch-card">
                                                <div class="card card-rounded">
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-lg-6">
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center mb-2 mb-sm-0">
                                                                    <div class="circle-progress-width">
                                                                        <div id="totalVisitors"
                                                                            class="progressbar-js-circle pr-2">
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <p class="text-small mb-2">Total
                                                                            Visitors</p>
                                                                        <h4 class="mb-0 fw-bold">26.80%</h4>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-6">
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center">
                                                                    <div class="circle-progress-width">
                                                                        <div id="visitperday"
                                                                            class="progressbar-js-circle pr-2">
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <p class="text-small mb-2">Visits per
                                                                            day</p>
                                                                        <h4 class="mb-0 fw-bold">9065</h4>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-8 d-flex flex-column">
                                        <div class="row flex-grow">
                                            <div class="col-12 grid-margin stretch-card">
                                                <div class="card card-rounded">
                                                    <div class="card-body">
                                                        <div class="d-sm-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h4 class="card-title card-title-dash">Market
                                                                    Overview</h4>
                                                                <p class="card-subtitle card-subtitle-dash">
                                                                    Lorem ipsum dolor sit amet consectetur
                                                                    adipisicing elit</p>
                                                            </div>
                                                            <div>
                                                                <div class="dropdown">
                                                                    <button
                                                                        class="btn btn-light dropdown-toggle toggle-dark btn-lg mb-0 me-0"
                                                                        type="button" id="dropdownMenuButton2"
                                                                        data-bs-toggle="dropdown" aria-haspopup="true"
                                                                        aria-expanded="false"> This month
                                                                    </button>
                                                                    <div class="dropdown-menu"
                                                                        aria-labelledby="dropdownMenuButton2">
                                                                        <h6 class="dropdown-header">Settings
                                                                        </h6>
                                                                        <a class="dropdown-item" href="#">Action</a>
                                                                        <a class="dropdown-item" href="#">Another
                                                                            action</a>
                                                                        <a class="dropdown-item" href="#">Something
                                                                            else
                                                                            here</a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <a class="dropdown-item" href="#">Separated
                                                                            link</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="d-sm-flex align-items-center mt-1 justify-content-between">
                                                            <div
                                                                class="d-sm-flex align-items-center mt-4 justify-content-between">
                                                                <h2 class="me-2 fw-bold">$36,2531.00</h2>
                                                                <h4 class="me-2">USD</h4>
                                                                <h4 class="text-success">(+1.37%)</h4>
                                                            </div>
                                                            <div class="me-3">
                                                                <div id="marketingOverview-legend"></div>
                                                            </div>
                                                        </div>
                                                        <div class="chartjs-bar-wrapper mt-3">
                                                            <canvas id="marketingOverview"></canvas>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row flex-grow">
                                            <div class="col-12 grid-margin stretch-card">
                                                <div class="card card-rounded">
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-lg-12">
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center">
                                                                    <h4 class="card-title card-title-dash">Todo
                                                                        list</h4>
                                                                    <div class="add-items d-flex mb-0">
                                                                        <!-- <input type="text" class="form-control todo-list-input" placeholder="What do you need to do today?"> -->
                                                                        <button
                                                                            class="add btn btn-icons btn-rounded btn-primary todo-list-add-btn text-white me-0 pl-12p"><i
                                                                                class="mdi mdi-plus"></i></button>
                                                                    </div>
                                                                </div>
                                                                <div class="list-wrapper">
                                                                    <ul class="todo-list todo-list-rounded">
                                                                        <li class="d-block">
                                                                            <div class="form-check w-100">
                                                                                <label class="form-check-label">
                                                                                    <input class="checkbox"
                                                                                        type="checkbox"> Lorem
                                                                                    Ipsum is simply dummy text
                                                                                    of the printing <i
                                                                                        class="input-helper rounded"></i>
                                                                                </label>
                                                                                <div class="d-flex mt-2">
                                                                                    <div class="ps-4 text-small me-3">
                                                                                        24 June 2020</div>
                                                                                    <div
                                                                                        class="badge badge-opacity-warning me-3">
                                                                                        Due tomorrow</div>
                                                                                    <i
                                                                                        class="mdi mdi-flag ms-2 flag-color"></i>
                                                                                </div>
                                                                            </div>
                                                                        </li>
                                                                        <li class="d-block">
                                                                            <div class="form-check w-100">
                                                                                <label class="form-check-label">
                                                                                    <input class="checkbox"
                                                                                        type="checkbox"> Lorem
                                                                                    Ipsum is simply dummy text
                                                                                    of the printing <i
                                                                                        class="input-helper rounded"></i>
                                                                                </label>
                                                                                <div class="d-flex mt-2">
                                                                                    <div class="ps-4 text-small me-3">
                                                                                        23 June 2020</div>
                                                                                    <div
                                                                                        class="badge badge-opacity-success me-3">
                                                                                        Done</div>
                                                                                </div>
                                                                            </div>
                                                                        </li>
                                                                        <li>
                                                                            <div class="form-check w-100">
                                                                                <label class="form-check-label">
                                                                                    <input class="checkbox"
                                                                                        type="checkbox"> Lorem
                                                                                    Ipsum is simply dummy text
                                                                                    of the printing <i
                                                                                        class="input-helper rounded"></i>
                                                                                </label>
                                                                                <div class="d-flex mt-2">
                                                                                    <div class="ps-4 text-small me-3">
                                                                                        24 June 2020</div>
                                                                                    <div
                                                                                        class="badge badge-opacity-success me-3">
                                                                                        Done</div>
                                                                                </div>
                                                                            </div>
                                                                        </li>
                                                                        <li class="border-bottom-0">
                                                                            <div class="form-check w-100">
                                                                                <label class="form-check-label">
                                                                                    <input class="checkbox"
                                                                                        type="checkbox"> Lorem
                                                                                    Ipsum is simply dummy text
                                                                                    of the printing <i
                                                                                        class="input-helper rounded"></i>
                                                                                </label>
                                                                                <div class="d-flex mt-2">
                                                                                    <div class="ps-4 text-small me-3">
                                                                                        24 June 2020</div>
                                                                                    <div
                                                                                        class="badge badge-opacity-danger me-3">
                                                                                        Expired</div>
                                                                                </div>
                                                                            </div>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 d-flex flex-column">
                                        <div class="row flex-grow">
                                            <div class="col-12 grid-margin stretch-card">
                                                <div class="card card-rounded">
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-lg-12">
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center mb-3">
                                                                    <h4 class="card-title card-title-dash">Type
                                                                        By Amount</h4>
                                                                </div>
                                                                <div>
                                                                    <canvas class="my-auto" id="doughnutChart"></canvas>
                                                                </div>
                                                                <div id="doughnutChart-legend" class="mt-5 text-center">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row flex-grow">
                                            <div class="col-12 grid-margin stretch-card">
                                                <div class="card card-rounded">
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-lg-12">
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center mb-3">
                                                                    <h4 class="card-title card-title-dash">Type
                                                                        By Amount</h4>
                                                                </div>
                                                                <div>
                                                                    <canvas class="my-auto" id="doughnutChart"></canvas>
                                                                </div>
                                                                <div id="doughnutChart-legend" class="mt-5 text-center">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        <footer class="footer">
            <div class="d-sm-flex justify-content-center text-center">

                <span class="float-none float-sm-end d-block mt-1 mt-sm-0 text-center">Copyright © 2025. All
                    rights reserved.</span>
            </div>
        </footer>
        <!-- partial -->
    </div>
@endsection
