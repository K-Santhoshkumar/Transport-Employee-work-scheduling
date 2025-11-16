<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

// Get user stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = (SELECT email FROM users WHERE id = ?)");
$stmt->execute([$_SESSION['user_id']]);
$employee_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM schedules s JOIN employees e ON s.employee_id = e.id WHERE e.email = (SELECT email FROM users WHERE id = ?)");
$stmt->execute([$_SESSION['user_id']]);
$schedule_count = $stmt->fetchColumn();

// Get recent schedules
$stmt = $pdo->prepare("
    SELECT s.*, e.name as employee_name 
    FROM schedules s 
    JOIN employees e ON s.employee_id = e.id 
    WHERE e.email = (SELECT email FROM users WHERE id = ?) 
    ORDER BY s.schedule_date DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_schedules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Transport Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/animations.css">
    <link rel="stylesheet" href="../assets/responsive.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="#" class="logo">
                    <i class="fas fa-bus"></i>
                    Transport Manager
                </a>
                <div class="nav-links">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        Profile
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
            <h1 class="dashboard-title">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $employee_count; ?></div>
                <div class="stat-label">Employee Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $schedule_count; ?></div>
                <div class="stat-label">Total Schedules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($recent_schedules); ?></div>
                <div class="stat-label">Recent Schedules</div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Recent Schedules</h2>
                <a href="schedules.php" class="btn btn-primary">View All</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shift Time</th>
                        <th>Duty Type</th>
                        <th>Bus Number</th>
                        <th>Route</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_schedules)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-secondary);">No schedules found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_schedules as $schedule): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($schedule['schedule_date'])); ?></td>
                                <td><?php echo htmlspecialchars($schedule['shift_time']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['duty_type']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['bus_number'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($schedule['route'] ?: 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>