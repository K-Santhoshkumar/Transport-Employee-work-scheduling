<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

// Get filter values
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$shift_type = $_GET['shift_type'] ?? '';
$duty_type = $_GET['duty_type'] ?? '';
$bus_number = $_GET['bus_number'] ?? '';
$route = $_GET['route'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query with filters
$query = "
    SELECT s.*, e.name as employee_name
    FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    WHERE e.email = (SELECT email FROM users WHERE id = ?)
";
$params = [$_SESSION['user_id']];

if ($start_date) {
    $query .= " AND s.schedule_date >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND s.schedule_date <= ?";
    $params[] = $end_date;
}

if ($shift_type) {
    if ($shift_type === 'morning') {
        $query .= " AND (s.shift_time LIKE '%AM%' OR s.shift_time LIKE '6:00%' OR s.shift_time LIKE '7:00%' OR s.shift_time LIKE '8:00%')";
    } elseif ($shift_type === 'afternoon') {
        $query .= " AND (s.shift_time LIKE '2:00%' OR s.shift_time LIKE '3:00%' OR s.shift_time LIKE '4:00%')";
    } elseif ($shift_type === 'night') {
        $query .= " AND (s.shift_time LIKE '%PM%' OR s.shift_time LIKE '10:00%' OR s.shift_time LIKE '11:00%')";
    }
}

if ($duty_type) {
    $query .= " AND s.duty_type = ?";
    $params[] = $duty_type;
}

if ($bus_number) {
    $query .= " AND s.bus_number = ?";
    $params[] = $bus_number;
}

if ($route) {
    $query .= " AND s.route = ?";
    $params[] = $route;
}

// Get total count for pagination
$count_query = str_replace("SELECT s.*, e.name as employee_name", "SELECT COUNT(*)", $query);
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_schedules = $stmt->fetchColumn();
$total_pages = ceil($total_schedules / $per_page);

// Get schedules for current page
$query .= " ORDER BY s.schedule_date DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

// Get unique values for filter dropdowns
$stmt = $pdo->prepare("
    SELECT DISTINCT duty_type FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    WHERE e.email = (SELECT email FROM users WHERE id = ?)
");
$stmt->execute([$_SESSION['user_id']]);
$duty_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("
    SELECT DISTINCT bus_number FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    WHERE e.email = (SELECT email FROM users WHERE id = ?) AND bus_number IS NOT NULL AND bus_number != ''
");
$stmt->execute([$_SESSION['user_id']]);
$bus_numbers = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("
    SELECT DISTINCT route FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    WHERE e.email = (SELECT email FROM users WHERE id = ?) AND route IS NOT NULL AND route != ''
");
$stmt->execute([$_SESSION['user_id']]);
$routes = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedules - Transport Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
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
                    <a href="schedules.php" class="nav-link" style="background: var(--primary-color);">
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
            <h1 class="dashboard-title">My Schedules</h1>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">All Schedules</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shift Time</th>
                        <th>Duty Type</th>
                        <th>Bus Number</th>
                        <th>Route</th>
                        <th>Notes</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                                <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                No schedules found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($schedule['schedule_date'])); ?></td>
                                <td><?php echo htmlspecialchars($schedule['shift_time']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['duty_type']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['bus_number'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($schedule['route'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($schedule['notes'] ?: 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($schedule['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>