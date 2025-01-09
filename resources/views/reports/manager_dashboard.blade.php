@extends('layout.master')
@section('title', 'Manager | Statistics Dashboard')
@section('content')
@section('style')
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            padding: 30px;
        }

        h1,
        h2 {
            font-weight: 500;
            color: #4A90E2;
            text-align: center;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            justify-items: center;
            margin-top: 40px;
        }

        .chart-box {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
            max-width: 500px;
        }

        .chart-box h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        canvas {
            margin: auto;
            width: 100%;
        }

        .card-body {
            padding: 30px;
        }

        .card {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-bottom: 40px;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            justify-items: center;
            margin-top: 40px;
        }

        .chart-box {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
            max-width: 500px;
        }
    </style>
@endsection

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h2>Call Count Overview</h2>
            <div class="card">
                <div class="card-body">
                    <canvas id="autoCallsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<h1>Call Statistics</h1>
<div class="charts-container">
    <div class="chart-box">
        <h2>Auto Distributor Calls</h2>
        <canvas id="autoDistributorChart"></canvas>
    </div>
    <div class="chart-box">
        <h2>Auto Dialer Calls</h2>
        <canvas id="autoDailerChart"></canvas>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Data from the controller
    const autoDailerAnswered = @json($autoDailerAnswered);
    const autoDailerUnanswered = @json($autoDailerUnanswered);
    const autoDistributorAnswered = @json($autoDistributorAnswered);
    const autoDistributorUnanswered = @json($autoDistributorUnanswered);

    // Auto Dailer Chart
    new Chart(document.getElementById('autoDailerChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['Answered', 'Unanswered'],
            datasets: [{
                data: [autoDailerAnswered, autoDailerUnanswered],
                backgroundColor: ['#36A2EB', '#FF6384'],
                hoverBackgroundColor: ['#5AC8FA', '#FF8E9B'],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            size: 14,
                            family: 'Roboto',
                            weight: '500'
                        },
                        color: '#333'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let total = autoDailerAnswered + autoDailerUnanswered;
                            let percentage = (context.raw / total * 100).toFixed(2);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                },
                datalabels: {
                    formatter: function(value, context) {
                        let total = autoDailerAnswered + autoDailerUnanswered;
                        let percentage = (value / total * 100).toFixed(2);
                        return `${value} (${percentage}%)`;
                    },
                    color: '#fff',
                    font: {
                        weight: 'bold',
                        size: 16
                    },
                    anchor: 'center',
                    align: 'center'
                }
            }
        }
    });

    // Auto Distributor Chart
    new Chart(document.getElementById('autoDistributorChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['Answered', 'Unanswered'],
            datasets: [{
                data: [autoDistributorAnswered, autoDistributorUnanswered],
                backgroundColor: ['#36A2EB', '#FF6384'],
                hoverBackgroundColor: ['#5AC8FA', '#FF8E9B'],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            size: 14,
                            family: 'Roboto',
                            weight: '500'
                        },
                        color: '#333'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let total = autoDistributorAnswered + autoDistributorUnanswered;
                            let percentage = (context.raw / total * 100).toFixed(2);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                },
                datalabels: {
                    formatter: function(value, context) {
                        let total = autoDistributorAnswered + autoDistributorUnanswered;
                        let percentage = (value / total * 100).toFixed(2);
                        return `${value} (${percentage}%)`;
                    },
                    color: '#fff',
                    font: {
                        weight: 'bold',
                        size: 16
                    },
                    anchor: 'center',
                    align: 'center'
                }
            }
        }
    });

    // Count Dashboard Chart
    var ctx = document.getElementById('autoCallsChart').getContext('2d');
    var autoCallsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['AutoDistributor Calls', 'AutoDialer Calls'],
            datasets: [{
                label: 'Number of Calls',
                data: [{{ $autoDistributorCalls }}, {{ $autoDialerCalls }}],
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
@endpush
