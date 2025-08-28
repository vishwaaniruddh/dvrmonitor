<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Alerts CRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background-color: var(--secondary-color);
            overflow-x: hidden;
        }
        
        #sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%, #224abe 100%);
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .stat-card {
            border-left: 0.25rem solid;
        }
        
        .stat-card.total {
            border-left-color: var(--primary-color);
        }
        
        .stat-card.critical {
            border-left-color: var(--danger-color);
        }
        
        .stat-card.today {
            border-left-color: var(--success-color);
        }
        
        .stat-card.resolved {
            border-left-color: var(--info-color);
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>
        
        <div id="page-content-wrapper" style="width: 85%;">
            <?php include 'header.php'; ?>
            
            <div class="container-fluid px-4">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                        <i class="bi bi-download text-white-50"></i> Generate Report
                    </a>
                </div>
                
                <div class="row">
                    <!-- Stat Cards -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card total h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Alerts</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">1,722</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-exclamation-triangle-fill text-primary fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card today h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Today's Alerts</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">24</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-calendar-day-fill text-success fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card critical h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Critical Alerts</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">36</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-exclamation-octagon-fill text-danger fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card resolved h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Resolved Alerts</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">1,200</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-check-circle-fill text-info fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Alert Type Distribution -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Alert Type Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="alertTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Priority Alerts -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Priority Alerts</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Count</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="table-danger">
                                                <td>Fire</td>
                                                <td>7</td>
                                                <td><span class="badge bg-danger">Critical</span></td>
                                            </tr>
                                            <tr class="table-danger">
                                                <td>Theft Attempt</td>
                                                <td>21</td>
                                                <td><span class="badge bg-danger">Critical</span></td>
                                            </tr>
                                            <tr class="table-warning">
                                                <td>Person Fall</td>
                                                <td>8</td>
                                                <td><span class="badge bg-warning">Warning</span></td>
                                            </tr>
                                            <tr class="table-warning">
                                                <td>Crowding</td>
                                                <td>174</td>
                                                <td><span class="badge bg-warning">Warning</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Alert Trends -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Alert Trends</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-bar">
                                    <canvas id="alertTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resolution Status -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Resolution Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="resolutionChart"></canvas>
                                </div>
                                <div class="mt-4 text-center small">
                                    <span class="mr-2">
                                        <i class="fas fa-circle text-success"></i> Resolved
                                    </span>
                                    <span class="mr-2">
                                        <i class="fas fa-circle text-primary"></i> In Progress
                                    </span>
                                    <span class="mr-2">
                                        <i class="fas fa-circle text-info"></i> New
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Alert Type Chart (Pie)
        const alertTypeCtx = document.getElementById('alertTypeChart').getContext('2d');
        const alertTypeChart = new Chart(alertTypeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Person', 'Person-Gather', 'Motion', 'Crowding', 'Loitering', 'Multi', 'Theftattempt', 'facemask', 'Person-fall', 'Fire', 'helmet'],
                datasets: [{
                    data: [682, 460, 274, 174, 110, 32, 21, 13, 8, 7, 1],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
                        '#858796', '#5a5c69', '#3a3b45', '#2c3e50', '#e83e8c', '#6f42c1'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617',
                        '#656776', '#424347', '#28292e', '#1a252f', '#c2185b', '#563d7c'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyFontColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    },
                    legend: {
                        display: true,
                        position: 'right',
                    },
                },
                cutout: '70%',
            },
        });

        // Alert Trends Chart (Bar)
        const alertTrendsCtx = document.getElementById('alertTrendsChart').getContext('2d');
        const alertTrendsChart = new Chart(alertTrendsCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: "Alerts",
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9',
                    borderColor: '#4e73df',
                    data: [120, 190, 150, 220, 170, 160, 200, 240, 190, 210, 250, 300],
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 6
                        },
                    },
                    y: {
                        ticks: {
                            min: 0,
                            max: 400,
                            maxTicksLimit: 5,
                            padding: 10,
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    },
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        titleMarginBottom: 10,
                        titleColor: '#6e707e',
                        backgroundColor: "rgb(255,255,255)",
                        bodyFontColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    },
                }
            }
        });

        // Resolution Status Chart (Pie)
        const resolutionCtx = document.getElementById('resolutionChart').getContext('2d');
        const resolutionChart = new Chart(resolutionCtx, {
            type: 'pie',
            data: {
                labels: ["Resolved", "In Progress", "New"],
                datasets: [{
                    data: [1200, 400, 122],
                    backgroundColor: ['#1cc88a', '#4e73df', '#36b9cc'],
                    hoverBackgroundColor: ['#17a673', '#2e59d9', '#2c9faf'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyFontColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    },
                    legend: {
                        display: false
                    },
                },
                cutout: '70%',
            },
        });
    </script>
</body>
</html>