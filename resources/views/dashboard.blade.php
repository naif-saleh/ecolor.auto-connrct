@extends('layout.master')

@section('content')
{{-- <div class="container">
    <div class="row">
        <div class="col-md-12">
            <h2 class="text-center">Auto Calls Dashboard</h2>
            <div class="card">
                <div class="card-body">
                    <canvas id="autoCallsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div> --}}
@endsection

{{-- @push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    var ctx = document.getElementById('autoCallsChart').getContext('2d');
    var autoCallsChart = new Chart(ctx, {
        type: 'bar', // You can change this to 'line', 'pie', etc.
        data: {
            labels: ['AutoDistributorCalls', 'AutoDialerCalls'], // Categories for the bars
            datasets: [{
                label: 'Number of Calls ' ,
                data: [{{ $autoDistributorCalls }}, {{ $autoDialerCalls }}], // The data passed from the controller
                backgroundColor: ['rgba(75, 192, 192, 0.2)', 'rgba(255, 99, 132, 0.2)'],
                borderColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 99, 132, 1)'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>
@endpush --}}
