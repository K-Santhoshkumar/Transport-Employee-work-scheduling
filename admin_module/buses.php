<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_bus':
                $registration_number = trim($_POST['registration_number'] ?? '');
                $bus_type = $_POST['bus_type'] ?? 'Regular';
                $capacity_seats = intval($_POST['capacity_seats'] ?? 0);
                $capacity_standing = intval($_POST['capacity_standing'] ?? 0);
                $make = trim($_POST['make'] ?? '');
                $model = trim($_POST['model'] ?? '');
                $year_manufactured = intval($_POST['year_manufactured'] ?? 0);
                $purchase_date = $_POST['purchase_date'] ?? null;
                $insurance_expiry = $_POST['insurance_expiry'] ?? null;
                $registration_expiry = $_POST['registration_expiry'] ?? null;

                if (empty($registration_number) || $capacity_seats <= 0) {
                    $error = 'Registration number and seat capacity are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO buses (registration_number, bus_type, capacity_seats, capacity_standing,
                                              make, model, year_manufactured, purchase_date, insurance_expiry,
                                              registration_expiry)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$registration_number, $bus_type, $capacity_seats, $capacity_standing,
                                       $make, $model, $year_manufactured, $purchase_date, $insurance_expiry,
                                       $registration_expiry]);
                        $message = 'Bus added successfully!';
                    } catch (PDOException $e) {
                        $error = 'Failed to add bus. Registration number might already exist.';
                    }
                }
                break;

            case 'add_route':
                $route_number = trim($_POST['route_number'] ?? '');
                $route_name = trim($_POST['route_name'] ?? '');
                $start_point = trim($_POST['start_point'] ?? '');
                $end_point = trim($_POST['end_point'] ?? '');
                $intermediate_stops = trim($_POST['intermediate_stops'] ?? '');
                $distance_km = floatval($_POST['distance_km'] ?? 0);
                $estimated_duration = intval($_POST['estimated_duration'] ?? 0);
                $peak_hours_start = $_POST['peak_hours_start'] ?? null;
                $peak_hours_end = $_POST['peak_hours_end'] ?? null;
                $base_fare = floatval($_POST['base_fare'] ?? 0);

                if (empty($route_number) || empty($route_name) || empty($start_point) || empty($end_point)) {
                    $error = 'Route number, name, start point, and end point are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO routes (route_number, route_name, start_point, end_point, intermediate_stops,
                                             distance_km, estimated_duration_minutes, peak_hours_start, peak_hours_end, base_fare)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$route_number, $route_name, $start_point, $end_point, $intermediate_stops,
                                       $distance_km, $estimated_duration, $peak_hours_start, $peak_hours_end, $base_fare]);
                        $message = 'Route added successfully!';
                    } catch (PDOException $e) {
                        $error = 'Failed to add route. Route number might already exist.';
                    }
                }
                break;

            case 'assign_bus_route':
                $bus_id = intval($_POST['bus_id'] ?? 0);
                $route_id = intval($_POST['route_id'] ?? 0);
                $effective_date = $_POST['effective_date'] ?? '';
                $end_date = $_POST['end_date'] ?? null;
                $assignment_type = $_POST['assignment_type'] ?? 'Permanent';
                $notes = trim($_POST['notes'] ?? '');

                if ($bus_id <= 0 || $route_id <= 0 || empty($effective_date)) {
                    $error = 'Bus, route, and effective date are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO bus_route_assignments (bus_id, route_id, effective_date, end_date, assignment_type, notes)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$bus_id, $route_id, $effective_date, $end_date, $assignment_type, $notes]);
                        $message = 'Bus assigned to route successfully!';
                    } catch (PDOException $e) {
                        $error = 'Failed to assign bus to route.';
                    }
                }
                break;
        }
    }
}

// Get data for display
$buses = $routes = $assignments = [];

try {
    // Get all buses
    $stmt = $pdo->query("SELECT * FROM buses ORDER BY registration_number");
    $buses = $stmt->fetchAll();

    // Get all routes
    $stmt = $pdo->query("SELECT * FROM routes ORDER BY route_number");
    $routes = $stmt->fetchAll();

    // Get current assignments
    $stmt = $pdo->query("
        SELECT bra.*, b.registration_number, r.route_name, r.route_number
        FROM bus_route_assignments bra
        JOIN buses b ON bra.bus_id = b.id
        JOIN routes r ON bra.route_id = r.id
        WHERE (bra.end_date IS NULL OR bra.end_date >= CURDATE())
        ORDER BY bra.effective_date DESC
    ");
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buses & Routes Management - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .buses-container {
            padding: 2rem 0;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border);
        }

        .tab-button {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            transition: var(--transition);
        }

        .tab-button:hover {
            color: var(--text-primary);
        }

        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--primary-color);
        }

        .form-card {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group label i {
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--surface);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-active {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }

        .badge-maintenance {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
        }

        .badge-retired {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
        }

        .badge-permanent {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }

        .badge-temporary {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
        }

        @media (max-width: 768px) {
            .tabs {
                overflow-x: auto;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .form-grid {
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
                    <a href="buses.php" class="nav-link" style="background: var(--primary-color);">
                        <i class="fas fa-bus"></i>
                        Buses & Routes
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

    <main class="container buses-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Buses & Routes Management</h1>
            <p style="color: var(--text-secondary);">Manage fleet and route assignments</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($buses); ?></div>
                <div class="stat-label">Total Buses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($routes); ?></div>
                <div class="stat-label">Total Routes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($assignments); ?></div>
                <div class="stat-label">Active Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $active_buses = array_filter($buses, fn($bus) => $bus['status'] === 'Active');
                    echo count($active_buses);
                    ?>
                </div>
                <div class="stat-label">Active Buses</div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button active" onclick="showTab('buses')">
                <i class="fas fa-bus"></i> Bus Fleet
            </button>
            <button class="tab-button" onclick="showTab('routes')">
                <i class="fas fa-route"></i> Routes
            </button>
            <button class="tab-button" onclick="showTab('assignments')">
                <i class="fas fa-link"></i> Assignments
            </button>
        </div>

        <!-- Buses Tab -->
        <div id="buses-tab" class="tab-content active">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-bus"></i>
                    Bus Fleet Management
                </h2>
                <button class="btn btn-primary" onclick="toggleForm('bus-form')">
                    <i class="fas fa-plus"></i>
                    Add New Bus
                </button>
            </div>

            <div id="bus-form" class="form-card" style="display: none;">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-primary);">Add New Bus</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_bus">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="registration_number">
                                <i class="fas fa-id-card"></i>
                                Registration Number *
                            </label>
                            <input type="text" id="registration_number" name="registration_number" required>
                        </div>
                        <div class="form-group">
                            <label for="bus_type">
                                <i class="fas fa-tag"></i>
                                Bus Type
                            </label>
                            <select id="bus_type" name="bus_type">
                                <option value="Regular">Regular</option>
                                <option value="Express">Express</option>
                                <option value="Luxury">Luxury</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="capacity_seats">
                                <i class="fas fa-users"></i>
                                Seat Capacity *
                            </label>
                            <input type="number" id="capacity_seats" name="capacity_seats" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="capacity_standing">
                                <i class="fas fa-users"></i>
                                Standing Capacity
                            </label>
                            <input type="number" id="capacity_standing" name="capacity_standing" min="0">
                        </div>
                        <div class="form-group">
                            <label for="make">
                                <i class="fas fa-industry"></i>
                                Make
                            </label>
                            <input type="text" id="make" name="make">
                        </div>
                        <div class="form-group">
                            <label for="model">
                                <i class="fas fa-cog"></i>
                                Model
                            </label>
                            <input type="text" id="model" name="model">
                        </div>
                        <div class="form-group">
                            <label for="year_manufactured">
                                <i class="fas fa-calendar"></i>
                                Year Manufactured
                            </label>
                            <input type="number" id="year_manufactured" name="year_manufactured"
                                   min="1990" max="<?php echo date('Y'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="purchase_date">
                                <i class="fas fa-shopping-cart"></i>
                                Purchase Date
                            </label>
                            <input type="date" id="purchase_date" name="purchase_date">
                        </div>
                        <div class="form-group">
                            <label for="insurance_expiry">
                                <i class="fas fa-shield-alt"></i>
                                Insurance Expiry
                            </label>
                            <input type="date" id="insurance_expiry" name="insurance_expiry">
                        </div>
                        <div class="form-group">
                            <label for="registration_expiry">
                                <i class="fas fa-id-card"></i>
                                Registration Expiry
                            </label>
                            <input type="date" id="registration_expiry" name="registration_expiry">
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            Add Bus
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm('bus-form')">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Registration</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Make/Model</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Insurance</th>
                            <th>Registration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($buses)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                                    No buses found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($buses as $bus): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($bus['registration_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($bus['bus_type']); ?></td>
                                    <td>
                                        <?php echo $bus['capacity_seats']; ?> seats
                                        <?php if ($bus['capacity_standing'] > 0): ?>
                                            + <?php echo $bus['capacity_standing']; ?> standing
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $make_model = [];
                                        if ($bus['make']) $make_model[] = htmlspecialchars($bus['make']);
                                        if ($bus['model']) $make_model[] = htmlspecialchars($bus['model']);
                                        echo implode(' ', $make_model) ?: 'N/A';
                                        ?>
                                    </td>
                                    <td><?php echo $bus['year_manufactured'] ?: 'N/A'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($bus['status']); ?>">
                                            <?php echo htmlspecialchars($bus['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($bus['insurance_expiry']) {
                                            $expiry = new DateTime($bus['insurance_expiry']);
                                            $today = new DateTime();
                                            $days_until = $today->diff($expiry)->days;
                                            $color = $days_until < 30 ? 'danger' : ($days_until < 90 ? 'warning' : 'success');
                                            echo "<span style='color: var(--{$color}-color);'>" . $expiry->format('M d, Y') . "</span>";
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($bus['registration_expiry']) {
                                            $expiry = new DateTime($bus['registration_expiry']);
                                            $today = new DateTime();
                                            $days_until = $today->diff($expiry)->days;
                                            $color = $days_until < 30 ? 'danger' : ($days_until < 90 ? 'warning' : 'success');
                                            echo "<span style='color: var(--{$color}-color);'>" . $expiry->format('M d, Y') . "</span>";
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Routes Tab -->
        <div id="routes-tab" class="tab-content">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-route"></i>
                    Route Management
                </h2>
                <button class="btn btn-primary" onclick="toggleForm('route-form')">
                    <i class="fas fa-plus"></i>
                    Add New Route
                </button>
            </div>

            <div id="route-form" class="form-card" style="display: none;">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-primary);">Add New Route</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_route">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="route_number">
                                <i class="fas fa-hashtag"></i>
                                Route Number *
                            </label>
                            <input type="text" id="route_number" name="route_number" required>
                        </div>
                        <div class="form-group">
                            <label for="route_name">
                                <i class="fas fa-tag"></i>
                                Route Name *
                            </label>
                            <input type="text" id="route_name" name="route_name" required>
                        </div>
                        <div class="form-group">
                            <label for="start_point">
                                <i class="fas fa-map-marker-alt"></i>
                                Start Point *
                            </label>
                            <input type="text" id="start_point" name="start_point" required>
                        </div>
                        <div class="form-group">
                            <label for="end_point">
                                <i class="fas fa-map-marker-alt"></i>
                                End Point *
                            </label>
                            <input type="text" id="end_point" name="end_point" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="intermediate_stops">
                                <i class="fas fa-map-signs"></i>
                                Intermediate Stops
                            </label>
                            <textarea id="intermediate_stops" name="intermediate_stops" rows="3"
                                    placeholder="Enter intermediate stops separated by commas"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="distance_km">
                                <i class="fas fa-road"></i>
                                Distance (km)
                            </label>
                            <input type="number" id="distance_km" name="distance_km" step="0.1" min="0">
                        </div>
                        <div class="form-group">
                            <label for="estimated_duration">
                                <i class="fas fa-clock"></i>
                                Duration (minutes)
                            </label>
                            <input type="number" id="estimated_duration" name="estimated_duration" min="0">
                        </div>
                        <div class="form-group">
                            <label for="peak_hours_start">
                                <i class="fas fa-sun"></i>
                                Peak Hours Start
                            </label>
                            <input type="time" id="peak_hours_start" name="peak_hours_start">
                        </div>
                        <div class="form-group">
                            <label for="peak_hours_end">
                                <i class="fas fa-sun"></i>
                                Peak Hours End
                            </label>
                            <input type="time" id="peak_hours_end" name="peak_hours_end">
                        </div>
                        <div class="form-group">
                            <label for="base_fare">
                                <i class="fas fa-dollar-sign"></i>
                                Base Fare
                            </label>
                            <input type="number" id="base_fare" name="base_fare" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            Add Route
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm('route-form')">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Route #</th>
                            <th>Name</th>
                            <th>Start Point</th>
                            <th>End Point</th>
                            <th>Distance</th>
                            <th>Duration</th>
                            <th>Base Fare</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($routes)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                                    No routes found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($routes as $route): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($route['route_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($route['route_name']); ?></td>
                                    <td><?php echo htmlspecialchars($route['start_point']); ?></td>
                                    <td><?php echo htmlspecialchars($route['end_point']); ?></td>
                                    <td><?php echo $route['distance_km'] ? number_format($route['distance_km'], 1) . ' km' : 'N/A'; ?></td>
                                    <td><?php echo $route['estimated_duration_minutes'] ? $route['estimated_duration_minutes'] . ' min' : 'N/A'; ?></td>
                                    <td><?php echo $route['base_fare'] ? '$' . number_format($route['base_fare'], 2) : 'N/A'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($route['status']); ?>">
                                            <?php echo htmlspecialchars($route['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Assignments Tab -->
        <div id="assignments-tab" class="tab-content">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-link"></i>
                    Bus-Route Assignments
                </h2>
                <button class="btn btn-primary" onclick="toggleForm('assignment-form')">
                    <i class="fas fa-plus"></i>
                    New Assignment
                </button>
            </div>

            <div id="assignment-form" class="form-card" style="display: none;">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-primary);">Assign Bus to Route</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_bus_route">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="bus_id">
                                <i class="fas fa-bus"></i>
                                Bus *
                            </label>
                            <select id="bus_id" name="bus_id" required>
                                <option value="">Select Bus</option>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?php echo $bus['id']; ?>">
                                        <?php echo htmlspecialchars($bus['registration_number']); ?>
                                        (<?php echo htmlspecialchars($bus['bus_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="route_id">
                                <i class="fas fa-route"></i>
                                Route *
                            </label>
                            <select id="route_id" name="route_id" required>
                                <option value="">Select Route</option>
                                <?php foreach ($routes as $route): ?>
                                    <option value="<?php echo $route['id']; ?>">
                                        <?php echo htmlspecialchars($route['route_number']); ?> -
                                        <?php echo htmlspecialchars($route['route_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="effective_date">
                                <i class="fas fa-calendar"></i>
                                Effective Date *
                            </label>
                            <input type="date" id="effective_date" name="effective_date" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">
                                <i class="fas fa-calendar-check"></i>
                                End Date
                            </label>
                            <input type="date" id="end_date" name="end_date">
                        </div>
                        <div class="form-group">
                            <label for="assignment_type">
                                <i class="fas fa-tag"></i>
                                Assignment Type
                            </label>
                            <select id="assignment_type" name="assignment_type">
                                <option value="Permanent">Permanent</option>
                                <option value="Temporary">Temporary</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="assignment_notes">
                                <i class="fas fa-sticky-note"></i>
                                Notes
                            </label>
                            <textarea id="assignment_notes" name="notes" rows="3"
                                    placeholder="Additional notes about this assignment"></textarea>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            Create Assignment
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm('assignment-form')">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bus</th>
                            <th>Route</th>
                            <th>Assignment Type</th>
                            <th>Effective Date</th>
                            <th>End Date</th>
                            <th>Days Active</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                                    No active assignments found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <?php
                                $effective = new DateTime($assignment['effective_date']);
                                $end = $assignment['end_date'] ? new DateTime($assignment['end_date']) : new DateTime();
                                $days_active = $effective->diff($end)->days;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($assignment['registration_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($assignment['route_number']); ?> -
                                        <?php echo htmlspecialchars($assignment['route_name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($assignment['assignment_type']); ?>">
                                            <?php echo htmlspecialchars($assignment['assignment_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $effective->format('M d, Y'); ?></td>
                                    <td><?php echo $assignment['end_date'] ? (new DateTime($assignment['end_date']))->format('M d, Y') : 'Ongoing'; ?></td>
                                    <td><?php echo $days_active; ?> days</td>
                                    <td><?php echo htmlspecialchars($assignment['notes'] ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to clicked button
            event.target.closest('.tab-button').classList.add('active');
        }

        function toggleForm(formId) {
            const form = document.getElementById(formId);
            if (form.style.display === 'none') {
                form.style.display = 'block';
                // Scroll to form
                form.scrollIntoView({ behavior: 'smooth' });
            } else {
                form.style.display = 'none';
            }
        }

        // Set default effective date to today
        document.addEventListener('DOMContentLoaded', function() {
            const effectiveDate = document.getElementById('effective_date');
            if (effectiveDate) {
                effectiveDate.valueAsDate = new Date();
            }
        });
    </script>
</body>
</html>