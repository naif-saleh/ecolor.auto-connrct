@extends('layout.autoDistributer')

@section('title', 'Manager | Auto Dailers Reports per Providers')

@section('content')
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <!-- Card Container -->
                <div class="card shadow border-0">
                    <div class="card-header bg-gradient text-white text-center" style="background-color: #1a73e8;">
                        <h3 class="mb-0">Auto Dailer Reports per Providers</h3>
                    </div>
                    <div class="card-body">
                        <!-- Filter Form -->
                        <form method="GET" action="{{ route('manager.autodistributor.report.providers') }}" class="mb-4">
                            <div class="row justify-content-center">
                                <!-- From Date -->
                                <div class="col-md-3 mb-3">
                                    <label for="from_date" class="form-label">From Date</label>
                                    <input type="date" name="from_date" value="{{ $from_date }}"
                                        class="form-control rounded-pill border-primary" />
                                </div>

                                <!-- To Date -->
                                <div class="col-md-3 mb-3">
                                    <label for="to_date" class="form-label">To Date</label>
                                    <input type="date" name="to_date" value="{{ $to_date }}"
                                        class="form-control rounded-pill border-primary" />
                                </div>

                                <!-- Provider -->
                                <div class="col-md-3 mb-3">
                                    <label for="provider" class="form-label">Provider</label>
                                    <input type="text" name="provider" value="{{ $provider }}"
                                        class="form-control rounded-pill border-primary" placeholder="Provider" />
                                </div>

                                <!-- Submit Button -->
                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Filter</button>
                                </div>
                            </div>
                        </form>

                        <!-- Activity Table -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th scope="col">Extension</th>
                                        <th scope="col">Provider</th>
                                        <th scope="col">Answered</th>
                                        <th scope="col">Unanswered</th>
                                        <th scope="col">Unanswered - Emplooyee</th>
                                        <th scope="col">Total Calls</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData as $data)
                                        <tr>
                                            <td>{{ $data->extension }}</td>
                                            <td>{{ $data->provider }}</td>
                                            <td>{{ $data->answered }}</td>
                                            <td>{{ $data->unanswered }}</td>
                                            <td>{{ $data->unasweredEmplooyee }}</td>
                                            <td>{{ $data->total_calls }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-muted text-center">
                        Total Records: {{ $reportData->total() }}
                    </div>
                </div>
            </div>

            <!-- Pagination Links -->
            <div class="d-flex justify-content-center mt-4">
                {{ $reportData->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <style>
        .card {
            border-radius: 15px;
        }

        .card-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        }

        .table-hover tbody tr:hover {
            background-color: #f1f1f1;
        }

        .btn-primary {
            background-color: #1a73e8;
            border-color: #1a73e8;
        }

        .btn-primary:hover {
            background-color: #155bb5;
            border-color: #155bb5;
        }

        .form-label {
            font-weight: 600;
            font-size: 1.1rem;
            color: #4B4F54;
        }

        .form-control {
            transition: all 0.3s ease-in-out;
            border-radius: 50px;
            box-shadow: none;
        }

        .form-control:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 8px rgba(26, 115, 232, 0.5);
        }

        .btn-primary {
            background-color: #1a73e8;
            border-color: #1a73e8;
            border-radius: 50px;
            padding: 12px 20px;
        }

        .btn-primary:hover {
            background-color: #155bb5;
            border-color: #155bb5;
        }

        .col-md-3,
        .col-md-2 {
            padding-left: 15px;
            padding-right: 15px;
        }

        /* For small screens, stack inputs vertically */
        @media (max-width: 768px) {

            .col-md-3,
            .col-md-2 {
                margin-bottom: 15px;
            }
        }
    </style>
@endsection
