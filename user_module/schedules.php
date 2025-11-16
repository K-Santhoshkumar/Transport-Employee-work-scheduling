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
            <h1 class="dashboard-title">Search Schedules</h1>
            <p style="color: var(--text-secondary);">Find and filter your work schedules</p>
        </div>

        <div class="search-container">
            <h2 class="search-title">
                <i class="fas fa-filter"></i>
                Advanced Search Filters
            </h2>

            <div class="quick-filters">
                <button class="quick-filter-btn" onclick="setQuickFilter('today')">
                    <i class="fas fa-calendar-day"></i>
                    Today
                </button>
                <button class="quick-filter-btn" onclick="setQuickFilter('week')">
                    <i class="fas fa-calendar-week"></i>
                    This Week
                </button>
                <button class="quick-filter-btn" onclick="setQuickFilter('month')">
                    <i class="fas fa-calendar-alt"></i>
                    This Month
                </button>
                <button class="quick-filter-btn" onclick="setQuickFilter('30days')">
                    <i class="fas fa-history"></i>
                    Last 30 Days
                </button>
            </div>

            <form method="GET" id="searchForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="start_date">
                            <i class="fas fa-calendar"></i>
                            Start Date
                        </label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="end_date">
                            <i class="fas fa-calendar-check"></i>
                            End Date
                        </label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="shift_type">
                            <i class="fas fa-clock"></i>
                            Shift Type
                        </label>
                        <select id="shift_type" name="shift_type">
                            <option value="">All Shifts</option>
                            <option value="morning" <?php echo $shift_type === 'morning' ? 'selected' : ''; ?>>
                                Morning Shift (6:00 AM - 2:00 PM)
                            </option>
                            <option value="afternoon" <?php echo $shift_type === 'afternoon' ? 'selected' : ''; ?>>
                                Afternoon Shift (2:00 PM - 10:00 PM)
                            </option>
                            <option value="night" <?php echo $shift_type === 'night' ? 'selected' : ''; ?>>
                                Night Shift (10:00 PM - 6:00 AM)
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="duty_type">
                            <i class="fas fa-briefcase"></i>
                            Duty Type
                        </label>
                        <select id="duty_type" name="duty_type">
                            <option value="">All Duty Types</option>
                            <?php foreach ($duty_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $duty_type === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="bus_number">
                            <i class="fas fa-bus"></i>
                            Bus Number
                        </label>
                        <select id="bus_number" name="bus_number">
                            <option value="">All Buses</option>
                            <?php foreach ($bus_numbers as $bus): ?>
                                <option value="<?php echo htmlspecialchars($bus); ?>" <?php echo $bus_number === $bus ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bus); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="route">
                            <i class="fas fa-route"></i>
                            Route
                        </label>
                        <select id="route" name="route">
                            <option value="">All Routes</option>
                            <?php foreach ($routes as $route): ?>
                                <option value="<?php echo htmlspecialchars($route); ?>" <?php echo $route === $route ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($route); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="search-actions">
                    <button type="submit" class="btn btn-primary btn-search">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <button type="button" class="btn btn-secondary btn-reset" onclick="resetFilters()">
                        <i class="fas fa-redo"></i>
                        Reset
                    </button>

                    <div class="export-buttons">
                        <button type="button" class="export-btn" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i>
                            Export Excel
                        </button>
                        <button type="button" class="export-btn" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i>
                            Export PDF
                        </button>
                        <button type="button" class="export-btn" onclick="window.print()">
                            <i class="fas fa-print"></i>
                            Print
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($start_date || $end_date || $shift_type || $duty_type || $bus_number || $route): ?>
            <div class="results-info">
                <div class="results-count">
                    <strong><?php echo $total_schedules; ?></strong> schedules found
                    <?php if ($total_schedules > $per_page): ?>
                        (showing page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                    <?php endif; ?>
                </div>
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
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
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function setQuickFilter(type) {
            const today = new Date();
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');

            // Remove active class from all buttons
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Add active class to clicked button
            event.target.closest('.quick-filter-btn').classList.add('active');

            switch(type) {
                case 'today':
                    startDate.value = today.toISOString().split('T')[0];
                    endDate.value = today.toISOString().split('T')[0];
                    break;
                case 'week':
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - today.getDay());
                    const weekEnd = new Date(weekStart);
                    weekEnd.setDate(weekStart.getDate() + 6);
                    startDate.value = weekStart.toISOString().split('T')[0];
                    endDate.value = weekEnd.toISOString().split('T')[0];
                    break;
                case 'month':
                    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                    const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    startDate.value = monthStart.toISOString().split('T')[0];
                    endDate.value = monthEnd.toISOString().split('T')[0];
                    break;
                case '30days':
                    const thirtyDaysAgo = new Date(today);
                    thirtyDaysAgo.setDate(today.getDate() - 30);
                    startDate.value = thirtyDaysAgo.toISOString().split('T')[0];
                    endDate.value = today.toISOString().split('T')[0];
                    break;
            }
        }

        function resetFilters() {
            document.getElementById('searchForm').reset();
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            window.location.href = 'schedules.php';
        }

        function exportToExcel() {
            // In a real application, this would generate and download an Excel file
            // For now, we'll show a message and could implement server-side export
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.location.href = 'schedules.php?' + params.toString();
        }

        function exportToPDF() {
            // In a real application, this would generate and download a PDF file
            // For now, we'll show a message and could implement server-side export
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.location.href = 'schedules.php?' + params.toString();
        }

        // Handle export requests
        <?php
        if (isset($_GET['export'])) {
            if ($_GET['export'] === 'excel') {
                echo "alert('Excel export would be generated here. Feature requires server-side implementation.');";
            } elseif ($_GET['export'] === 'pdf') {
                echo "alert('PDF export would be generated here. Feature requires server-side implementation.');";
            }
        }
        ?>

        // Auto-submit form on filter change for better UX
        document.querySelectorAll('.filter-group select').forEach(select => {
            select.addEventListener('change', function() {
                // Only auto-submit if we already have a search or if this is the first filter being set
                const hasActiveFilters = document.querySelector('.quick-filter-btn.active') ||
                                      document.querySelector('#start_date').value ||
                                      document.querySelector('#end_date').value;
                if (hasActiveFilters) {
                    document.getElementById('searchForm').submit();
                }
            });
        });
    </script>
</body>
</html>