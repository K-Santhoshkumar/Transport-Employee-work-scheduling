<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

// Enhanced statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM employees");
$total_employees = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM schedules");
$total_schedules = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM contacts");
$total_contacts = $stmt->fetchColumn();

// Live stats
$stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'");
$active_employees = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE schedule_date = CURDATE()");
$today_schedules = $stmt->execute();
$today_schedules = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT bus_number) FROM schedules WHERE schedule_date = CURDATE() AND bus_number IS NOT NULL");
$active_buses = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE status = 'Sent' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$pending_notifications = $stmt->execute();
$pending_notifications = $stmt->fetchColumn();

// System health checks
$system_health = [
    'database' => true, // Will be set to false if connection fails
    'backup' => true, // Placeholder
    'security' => true, // Placeholder
    'performance' => true // Placeholder
];

// Get recent activity
$recent_activity = [];

// Recent schedule changes
$stmt = $pdo->query("SELECT 'schedule' as type, created_at, CONCAT('New schedule created for ', employee_id) as details FROM schedules ORDER BY created_at DESC LIMIT 3");
$schedule_activity = $stmt->fetchAll();

// Recent user registrations
$stmt = $pdo->query("SELECT 'user' as type, created_at, CONCAT('New user registered: ', email) as details FROM users ORDER BY created_at DESC LIMIT 2");
$user_activity = $stmt->fetchAll();

$recent_activity = array_merge($schedule_activity, $user_activity);
usort($recent_activity, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$recent_activity = array_slice($recent_activity, 0, 5);

// Get recent employees
$stmt = $pdo->query("SELECT * FROM employees ORDER BY created_at DESC LIMIT 5");
$recent_employees = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Transport Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .enhanced-dashboard {
            display: grid;
            gap: 2rem;
        }

        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .live-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .live-stat-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
            transition: var(--transition);
        }

        .live-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .live-stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .live-stat-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .live-stat-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .activity-feed {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .activity-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-title i {
            color: var(--primary-color);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-left: 3px solid var(--border);
            margin-bottom: 0.5rem;
            transition: var(--transition);
        }

        .activity-item:hover {
            background: var(--background);
            border-left-color: var(--primary-color);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .activity-icon.schedule {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }

        .activity-icon.user {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }

        .activity-content {
            flex: 1;
        }

        .activity-details {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .quick-actions {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .quick-actions-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-actions-title i {
            color: var(--primary-color);
        }

        .quick-action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--background);
            border: 2px solid var(--border);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
            transition: var(--transition);
        }

        .quick-action-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .quick-action-btn i {
            font-size: 1.25rem;
        }

        .system-health {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .health-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .health-title i {
            color: var(--primary-color);
        }

        .health-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .health-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .health-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .health-status.online {
            background: var(--success-color);
        }

        .health-status.offline {
            background: var(--danger-color);
        }

        .health-label {
            font-weight: 500;
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }

            .quick-action-buttons {
                grid-template-columns: 1fr;
            }

            .health-items {
                grid-template-columns: 1fr;
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
                    <a href="reports.php" class="nav-link">
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
                    <a href="buses.php" class="nav-link">
                        <i class="fas fa-bus"></i>
                        Buses & Routes
                    </a>
                    <a href="notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        Notifications
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

    <main class="container dashboard">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Admin Dashboard</h1>
            <p style="color: var(--text-secondary);">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_employees; ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_schedules; ?></div>
                <div class="stat-label">Total Schedules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_contacts; ?></div>
                <div class="stat-label">Contact Messages</div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Recent Employees</h2>
                <a href="employees.php" class="btn btn-primary">Manage All</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_employees)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-secondary);">No employees found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                <td><?php echo htmlspecialchars($employee['designation']); ?></td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td>
                                    <span style="color: <?php echo $employee['status'] === 'Active' ? 'var(--success-color)' : 'var(--danger-color)'; ?>;">
                                        <?php echo htmlspecialchars($employee['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>