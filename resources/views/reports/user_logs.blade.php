@extends('layout.master')
@section('title', 'Logs | User Log')
@section('content')
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <!-- Card Container -->
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">User Activity Report</h3>
                    </div>
                    <div class="card-body">
                        <!-- Search Bar -->
                        <div class="mb-4">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search User Activity...">
                        </div>

                        <!-- Activity Table -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th scope="col">Username</th>
                                        <th scope="col">Operation</th>
                                        <th scope="col">User Role</th>
                                        <th scope="col">User Name</th>
                                        <th scope="col">User Email</th>
                                        <th scope="col">Time</th>
                                        <th scope="col">Date</th>
                                    </tr>
                                </thead>
                                <tbody id="activityTable">
                                    @foreach ($logs as $log)
                                        <tr>
                                            <td>{{ $log->user->name }}</td>
                                            <td>
                                                <span
                                                    class="badge
                                            @if ($log->opreation == 'add') bg-success
                                            @elseif($log->opreation == 'update') bg-warning
                                            @elseif($log->opreation == 'delete') bg-danger
                                            @else bg-secondary @endif">
                                                    {{ ucfirst($log->opreation) }}
                                                </span>
                                            </td>
                                            <td>{{ $log->user_role }}</td>
                                            <td>{{ $log->user_name }}</td>
                                            <td>{{ $log->user_email }}</td>
                                            {{-- <td>{{ $log->operation_time }}</td> --}}
                                            <td>{{ $log->created_at->addHours(3)->format('H:i:s') }}</td>
                                            <td>{{ $log->created_at->addHours(3)->format('Y-m-d') }}</td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-muted text-center">
                        Total Activities: <span id="activityCount">{{ count($logs) }}</span>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-center mt-4">
                {{ $logs->links('pagination::bootstrap-5') }}
            </div>
        </div>
        </div>



    <!-- JavaScript for Table Search -->
    <script>

        document.getElementById("searchInput").addEventListener("keyup", function() {
            let value = this.value.toLowerCase();
            let rows = document.querySelectorAll("#activityTable tr");
            let visibleCount = 0;

            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                if (text.includes(value)) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });

            // Update the total activity count dynamically
            document.getElementById("activityCount").textContent = visibleCount;
        });

        document.getElementById("searchInput").addEventListener("keyup", function() {
            let value = this.value.toLowerCase();
            let rows = document.querySelectorAll("#activityTable tr");

            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? "" : "none";
            });
        });
    </script>
@endsection
