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
    <title>Search Schedules - Transport Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .search-container {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .search-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-title i {
            color: var(--primary-color);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group label i {
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--surface);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .quick-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .quick-filter-btn {
            padding: 0.5rem 1rem;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-filter-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .quick-filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .search-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-search {
            background: var(--primary-color);
            color: white;
        }

        .btn-search:hover {
            background: var(--primary-dark);
        }

        .btn-reset {
            background: var(--secondary-color);
            color: white;
        }

        .btn-reset:hover {
            background: #475569;
        }

        .export-buttons {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        .export-btn {
            padding: 0.5rem 1rem;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .export-btn:hover {
            background: #047857;
        }

        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--background);
            border-radius: var(--border-radius);
        }

        .results-count {
            font-weight: 500;
            color: var(--text-primary);
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .page-link:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .highlight {
            background: rgba(37, 99, 235, 0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .search-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .export-buttons {
                margin-left: 0;
                margin-top: 1rem;
            }

            .results-info {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
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
                    Transport Manager
                </a>
                <div class="nav-links">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="about.php" class="nav-link">
                        <i class="fas fa-info-circle"></i>
                        About
                    </a>
                    <a href="contact.php" class="nav-link">
                        <i class="fas fa-envelope"></i>
                        Contact
                    </a>
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                    <a href="schedules.php" class="nav-link" style="background: var(--primary-color);">
                        <i class="fas fa-search"></i>
                        Search
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