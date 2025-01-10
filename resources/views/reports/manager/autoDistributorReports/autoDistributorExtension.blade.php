@extends('layout.autoDistributer')

@section('title', 'Manager | Auto Dailers Reports')

@section('content')
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <!-- Card Container -->
                <div class="card shadow border-0">
                    <div class="card-header bg-gradient text-white text-center" style="background-color: #1a73e8; color: #fff;">
                        <h3 class="mb-0">Report Per Extensions</h3>
                    </div>
                    <div class="card-body">
                        <!-- Filter Form -->
                        <form method="GET" action="{{ route('manager.autodistributor.report.extension') }}" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="from_date" class="form-label">From Date</label>
                                    <input type="date" name="from_date" value="{{ $from_date }}" class="form-control rounded-pill border-primary" />
                                </div>
                                <div class="col-md-3">
                                    <label for="to_date" class="form-label">To Date</label>
                                    <input type="date" name="to_date" value="{{ $to_date }}" class="form-control rounded-pill border-primary" />
                                </div>
                                <div class="col-md-3">
                                    <label for="extension" class="form-label">Extension</label>
                                    <input type="text" name="extension" value="{{ $extension }}" class="form-control rounded-pill border-primary" placeholder="Extension" />
                                </div>
                                <div class="col-md-3">
                                    <label for="provider" class="form-label">Provider</label>
                                    <input type="text" name="provider" value="{{ $provider }}" class="form-control rounded-pill border-primary" placeholder="Provider" />
                                </div>
                                <div class="col-md-2 mt-3">
                                    <button type="submit" class="btn btn-primary rounded-pill w-100">Filter</button>
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
                                            <td>{{ $data->unansweredByEmplooyee }}</td>
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

        .form-control {
            transition: all 0.3s ease-in-out;
        }

        .form-control:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 8px rgba(26, 115, 232, 0.5);
        }
    </style>
@endsection
