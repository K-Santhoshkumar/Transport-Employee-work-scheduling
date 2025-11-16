<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

// Get date range for reports
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Get KPI data
$kpis = [];

// Total employees
$stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'");
$kpis['total_employees'] = $stmt->fetchColumn();

// Active schedules today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE schedule_date = CURDATE()");
$kpis['active_schedules_today'] = $stmt->fetchColumn();

// Bus utilization
$stmt = $pdo->query("SELECT COUNT(DISTINCT bus_number) FROM schedules WHERE schedule_date = CURDATE() AND bus_number IS NOT NULL");
$active_buses_today = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(DISTINCT bus_number) FROM employees WHERE bus_number IS NOT NULL AND status = 'Active'");
$total_buses = $stmt->fetchColumn();
$kpis['bus_utilization'] = $total_buses > 0 ? round(($active_buses_today / $total_buses) * 100, 1) : 0;

// Route coverage
$stmt = $pdo->query("SELECT COUNT(DISTINCT route) FROM schedules WHERE schedule_date = CURDATE() AND route IS NOT NULL");
$active_routes_today = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(DISTINCT route) FROM employees WHERE route IS NOT NULL AND status = 'Active'");
$total_routes = $stmt->fetchColumn();
$kpis['route_coverage'] = $total_routes > 0 ? round(($active_routes_today / $total_routes) * 100, 1) : 0;

// On-time performance (placeholder - would need actual time tracking)
$kpis['on_time_performance'] = 95.8; // Example data

// Get attendance trends for last 30 days
$attendance_trends = [];
$stmt = $pdo->prepare("
    SELECT
        DATE(schedule_date) as date,
        COUNT(*) as schedules,
        COUNT(DISTINCT employee_id) as unique_employees
    FROM schedules
    WHERE schedule_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
    GROUP BY DATE(schedule_date)
    ORDER BY date
");
$stmt->execute();
$attendance_data = $stmt->fetchAll();

foreach ($attendance_data as $row) {
    $attendance_trends[] = [
        'date' => date('M d', strtotime($row['date'])),
        'schedules' => (int)$row['schedules'],
        'employees' => (int)$row['unique_employees']
    ];
}

// Get bus utilization by route
$bus_utilization = [];
$stmt = $pdo->prepare("
    SELECT
        route,
        COUNT(DISTINCT bus_number) as buses_used,
        COUNT(*) as total_schedules
    FROM schedules
    WHERE schedule_date BETWEEN ? AND ?
    AND route IS NOT NULL
    AND bus_number IS NOT NULL
    GROUP BY route
    ORDER BY total_schedules DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$bus_route_data = $stmt->fetchAll();

foreach ($bus_route_data as $row) {
    $bus_utilization[] = [
        'route' => htmlspecialchars($row['route']),
        'buses' => (int)$row['buses_used'],
        'schedules' => (int)$row['total_schedules']
    ];
}

// Get shift distribution
$shift_distribution = [];
$stmt = $pdo->prepare("
    SELECT
        shift_time,
        COUNT(*) as count
    FROM schedules
    WHERE schedule_date BETWEEN ? AND ?
    GROUP BY shift_time
    ORDER BY count DESC
");
$stmt->execute([$start_date, $end_date]);
$shift_data = $stmt->fetchAll();

foreach ($shift_data as $row) {
    $shift_distribution[] = [
        'shift' => htmlspecialchars($row['shift_time']),
        'count' => (int)$row['count']
    ];
}

// Get employee workload distribution
$workload_data = [];
$stmt = $pdo->prepare("
    SELECT
        e.name,
        COUNT(s.id) as schedule_count
    FROM employees e
    LEFT JOIN schedules s ON e.id = s.employee_id
        AND s.schedule_date BETWEEN ? AND ?
    WHERE e.status = 'Active'
    GROUP BY e.id, e.name
    ORDER BY schedule_count DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$workload_result = $stmt->fetchAll();

foreach ($workload_result as $row) {
    $workload_data[] = [
        'name' => htmlspecialchars($row['name']),
        'schedules' => (int)$row['schedule_count']
    ];
}

// Get performance metrics
$performance_metrics = [
    'avg_schedules_per_day' => 0,
    'peak_scheduling_day' => '',
    'underutilized_buses' => 0,
    'employee_satisfaction' => 0 // Placeholder
];

// Average schedules per day
$stmt = $pdo->prepare("
    SELECT AVG(schedule_count) as avg_count
    FROM (
        SELECT COUNT(*) as schedule_count
        FROM schedules
        WHERE schedule_date BETWEEN ? AND ?
        GROUP BY DATE(schedule_date)
    ) as daily_counts
");
$stmt->execute([$start_date, $end_date]);
$avg_schedules = $stmt->fetchColumn();
$performance_metrics['avg_schedules_per_day'] = round($avg_schedules, 1);

// Peak scheduling day
$stmt = $pdo->prepare("
    SELECT DATE(schedule_date) as peak_date, COUNT(*) as schedule_count
    FROM schedules
    WHERE schedule_date BETWEEN ? AND ?
    GROUP BY DATE(schedule_date)
    ORDER BY schedule_count DESC
    LIMIT 1
");
$stmt->execute([$start_date, $end_date]);
$peak_data = $stmt->fetch();
if ($peak_data) {
    $performance_metrics['peak_scheduling_day'] = date('M d, Y', strtotime($peak_data['peak_date'])) . ' (' . $peak_data['schedule_count'] . ' schedules)';
}

// Underutilized buses (less than 3 schedules in the period)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT bus_number) as underutilized_count
    FROM (
        SELECT bus_number, COUNT(*) as schedule_count
        FROM schedules
        WHERE schedule_date BETWEEN ? AND ?
        AND bus_number IS NOT NULL
        GROUP BY bus_number
        HAVING schedule_count < 3
    ) as underutilized
");
$stmt->execute([$start_date, $end_date]);
$performance_metrics['underutilized_buses'] = (int)$stmt->fetchColumn();

// Employee satisfaction (placeholder)
$performance_metrics['employee_satisfaction'] = 4.2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reports-container {
            padding: 2rem 0;
        }

        .date-filter {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .date-filter h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .date-filter h3 i {
            color: var(--primary-color);
        }

        .date-inputs {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .date-input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .date-input-group label {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .date-input-group input {
            padding: 0.5rem;
            border: 2px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }

        .date-input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
        }

        .kpi-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .kpi-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .kpi-change {
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .kpi-change.positive {
            color: var(--success-color);
        }

        .kpi-change.negative {
            color: var(--danger-color);
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-title i {
            color: var(--primary-color);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .metric-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .metric-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .metric-title i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .metric-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .export-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }

            .date-inputs {
                flex-direction: column;
                align-items: stretch;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="#" class="logo">
                    <i class="fas fa-bus"></i>
                    Transport Manager - Admin
                </a>
                <div class="nav-links">
                    <a href="dashboard.php" class="nav-link admin">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="reports.php" class="nav-link" style="background: var(--primary-color);">
                        <i class="fas fa-chart-line"></i>
                        Reports
                    </a>
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Users
                    </a>
                    <a href="employees.php" class="nav-link">
                        <i class="fas fa-user-tie"></i>
                        Employees
                    </a>
                    <a href="schedules.php" class="nav-link">
                        <i class="fas fa-calendar"></i>
                        Schedules
                    </a>
                    <a href="logout.php" class="nav-link" style="background: var(--danger-color);">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container reports-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Reports & Analytics</h1>
            <p style="color: var(--text-secondary);">Comprehensive insights and performance metrics</p>
        </div>

        <div class="date-filter">
            <h3><i class="fas fa-calendar-alt"></i> Date Range Filter</h3>
            <form method="GET" class="date-inputs">
                <div class="date-input-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="date-input-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync"></i>
                    Update Reports
                </button>
            </form>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $kpis['total_employees']; ?></div>
                <div class="kpi-label">Total Employees</div>
                <div class="kpi-change positive">
                    <i class="fas fa-arrow-up"></i> 5% from last month
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $kpis['active_schedules_today']; ?></div>
                <div class="kpi-label">Active Schedules Today</div>
                <div class="kpi-change positive">
                    <i class="fas fa-arrow-up"></i> 12% from yesterday
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $kpis['bus_utilization']; ?>%</div>
                <div class="kpi-label">Bus Utilization Rate</div>
                <div class="kpi-change negative">
                    <i class="fas fa-arrow-down"></i> 3% from last week
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $kpis['route_coverage']; ?>%</div>
                <div class="kpi-label">Route Coverage</div>
                <div class="kpi-change positive">
                    <i class="fas fa-arrow-up"></i> 2% from last week
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $kpis['on_time_performance']; ?>%</div>
                <div class="kpi-label">On-Time Performance</div>
                <div class="kpi-change positive">
                    <i class="fas fa-arrow-up"></i> 1.5% from last month
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line"></i>
                    Employee Attendance Trends (Last 30 Days)
                </h3>
                <div class="chart-container">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Bus Utilization by Route
                </h3>
                <div class="chart-container">
                    <canvas id="busUtilizationChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-bar"></i>
                    Shift Distribution
                </h3>
                <div class="chart-container">
                    <canvas id="shiftDistributionChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-users"></i>
                    Employee Workload Distribution
                </h3>
                <div class="chart-container">
                    <canvas id="workloadChart"></canvas>
                </div>
            </div>
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-title">
                    <i class="fas fa-calendar-check"></i>
                    Average Schedules per Day
                </div>
                <div class="metric-value"><?php echo $performance_metrics['avg_schedules_per_day']; ?></div>
                <div class="metric-description">
                    Based on the selected date range
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-title">
                    <i class="fas fa-chart-line"></i>
                    Peak Scheduling Day
                </div>
                <div class="metric-value" style="font-size: 1.1rem;">
                    <?php echo $performance_metrics['peak_scheduling_day']; ?>
                </div>
                <div class="metric-description">
                    Highest number of schedules in the period
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-title">
                    <i class="fas fa-bus"></i>
                    Underutilized Buses
                </div>
                <div class="metric-value"><?php echo $performance_metrics['underutilized_buses']; ?></div>
                <div class="metric-description">
                    Buses with less than 3 schedules in the period
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-title">
                    <i class="fas fa-smile"></i>
                    Employee Satisfaction
                </div>
                <div class="metric-value"><?php echo $performance_metrics['employee_satisfaction']; ?>/5.0</div>
                <div class="metric-description">
                    Average satisfaction rating
                </div>
            </div>
        </div>

        <div class="export-actions">
            <button class="btn btn-success" onclick="generateReport('daily')">
                <i class="fas fa-file-alt"></i>
                Generate Daily Report
            </button>
            <button class="btn btn-success" onclick="generateReport('weekly')">
                <i class="fas fa-file-alt"></i>
                Generate Weekly Report
            </button>
            <button class="btn btn-success" onclick="generateReport('monthly')">
                <i class="fas fa-file-alt"></i>
                Generate Monthly Report
            </button>
            <button class="btn btn-primary" onclick="exportData('excel')">
                <i class="fas fa-file-excel"></i>
                Export to Excel
            </button>
            <button class="btn btn-danger" onclick="exportData('pdf')">
                <i class="fas fa-file-pdf"></i>
                Export to PDF
            </button>
        </div>
    </main>

    <script>
        // Attendance Trends Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($attendance_trends, 'date')); ?>,
                datasets: [{
                    label: 'Total Schedules',
                    data: <?php echo json_encode(array_column($attendance_trends, 'schedules')); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Unique Employees',
                    data: <?php echo json_encode(array_column($attendance_trends, 'employees')); ?>,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Bus Utilization Chart
        const busCtx = document.getElementById('busUtilizationChart').getContext('2d');
        new Chart(busCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($bus_utilization, 'route')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($bus_utilization, 'schedules')); ?>,
                    backgroundColor: [
                        '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed',
                        '#0891b2', '#84cc16', '#f59e0b', '#ef4444', '#8b5cf6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Shift Distribution Chart
        const shiftCtx = document.getElementById('shiftDistributionChart').getContext('2d');
        new Chart(shiftCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($shift_distribution, 'shift')); ?>,
                datasets: [{
                    label: 'Number of Schedules',
                    data: <?php echo json_encode(array_column($shift_distribution, 'count')); ?>,
                    backgroundColor: '#2563eb'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Employee Workload Chart
        const workloadCtx = document.getElementById('workloadChart').getContext('2d');
        new Chart(workloadCtx, {
            type: 'horizontalBar',
            data: {
                labels: <?php echo json_encode(array_column($workload_data, 'name')); ?>,
                datasets: [{
                    label: 'Number of Schedules',
                    data: <?php echo json_encode(array_column($workload_data, 'schedules')); ?>,
                    backgroundColor: '#059669'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });

        function generateReport(type) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            // In a real application, this would generate and download a report
            alert(`Generating ${type} report for ${startDate} to ${endDate}\n\nThis would create a comprehensive report with all metrics and charts.`);
        }

        function exportData(format) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            // In a real application, this would export data in the specified format
            alert(`Exporting data as ${format.toUpperCase()} for ${startDate} to ${endDate}\n\nThis would download all report data in ${format.toUpperCase()} format.`);
        }
    </script>
</body>
</html>