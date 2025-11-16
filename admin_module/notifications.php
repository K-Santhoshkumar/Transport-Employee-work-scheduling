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
            case 'create_notification':
                $title = trim($_POST['title'] ?? '');
                $message_text = trim($_POST['message'] ?? '');
                $type = $_POST['type'] ?? 'System';
                $priority = $_POST['priority'] ?? 'Normal';
                $target_audience = $_POST['target_audience'] ?? 'All';
                $target_users = $_POST['target_users'] ?? '';
                $delivery_methods = $_POST['delivery_methods'] ?? ['inapp'];
                $scheduled_at = $_POST['scheduled_at'] ?? null;
                $expires_at = $_POST['expires_at'] ?? null;

                if (empty($title) || empty($message_text)) {
                    $error = 'Title and message are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (title, message, type, priority, target_audience,
                                                     target_users, delivery_methods, scheduled_at, expires_at,
                                                     status, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)
                        ");
                        $stmt->execute([$title, $message_text, $type, $priority, $target_audience,
                                       $target_users, json_encode($delivery_methods), $scheduled_at,
                                       $expires_at, $_SESSION['admin_id']]);

                        // Schedule notification if scheduled_at is set and in the past
                        if ($scheduled_at && $scheduled_at <= date('Y-m-d H:i:s')) {
                            // Send notification immediately
                            $notification_id = $pdo->lastInsertId();
                            sendNotification($notification_id);
                        }

                        $message = 'Notification created successfully!';
                    } catch (PDOException $e) {
                        $error = 'Failed to create notification: ' . $e->getMessage();
                    }
                }
                break;

            case 'send_notification':
                $notification_id = intval($_POST['notification_id'] ?? 0);
                if ($notification_id > 0) {
                    try {
                        sendNotification($notification_id);
                        $message = 'Notification sent successfully!';
                    } catch (PDOException $e) {
                        $error = 'Failed to send notification: ' . $e->getMessage();
                    }
                }
                break;

            case 'delete_notification':
                $notification_id = intval($_POST['notification_id'] ?? 0);
                if ($notification_id > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                        $stmt->execute([$notification_id]);
                        $message = 'Notification deleted successfully!';
                    } catch (PDOException $e) {
                        $error = 'Failed to delete notification: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get data for display
$notifications = [];

try {
    // Get all notifications
    $stmt = $pdo->query("
        SELECT n.*, a.name as created_by_name,
               (SELECT COUNT(*) FROM notification_reads nr WHERE nr.notification_id = n.id) as read_count,
               (SELECT COUNT(*) FROM users) as total_users
        FROM notifications n
        LEFT JOIN admin a ON n.created_by = a.id
        ORDER BY n.created_at DESC
    ");
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Helper function to send notification
function sendNotification($notification_id) {
    global $pdo;

    // Get notification details
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch();

    if (!$notification) {
        throw new Exception('Notification not found');
    }

    // Update status to sent
    $stmt = $pdo->prepare("UPDATE notifications SET status = 'Sent' WHERE id = ?");
    $stmt->execute([$notification_id]);

    // Get target users
    $target_user_ids = [];
    if ($notification['target_audience'] === 'All') {
        $stmt = $pdo->query("SELECT id FROM users");
        $target_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($notification['target_audience'] === 'Specific') {
        $target_user_ids = json_decode($notification['target_users'], true) ?: [];
    }

    // Create notification read records
    $delivery_methods = json_decode($notification['delivery_methods'], true) ?: ['inapp'];
    foreach ($target_user_ids as $user_id) {
        foreach ($delivery_methods as $method) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO notification_reads (notification_id, user_id, read_method)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$notification_id, $user_id, $method]);

            // If email delivery is selected, send email (placeholder)
            if ($method === 'email') {
                // In a real application, implement email sending here
                // sendNotificationEmail($user_id, $notification);
            }
        }
    }

    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .notifications-container {
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
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

        .notification-row {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }

        .notification-row:hover {
            background: var(--background);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .notification-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .notification-date {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .notification-message {
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .notification-stats {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .read-rate {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .read-rate strong {
            color: var(--primary-color);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-draft {
            background: rgba(100, 116, 139, 0.1);
            color: var(--secondary-color);
        }

        .badge-scheduled {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
        }

        .badge-sent {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }

        .badge-expired {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
        }

        .priority-high {
            color: var(--danger-color);
            font-weight: 600;
        }

        .priority-urgent {
            color: #dc2626;
            font-weight: 700;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
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

            .notification-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .notification-stats {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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
                    <a href="notifications.php" class="nav-link" style="background: var(--primary-color);">
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

    <main class="container notifications-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Notification Center</h1>
            <p style="color: var(--text-secondary);">Manage communications and announcements</p>
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
                <div class="stat-number">
                    <?php
                    $total_notifications = count($notifications);
                    echo $total_notifications;
                    ?>
                </div>
                <div class="stat-label">Total Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $sent_notifications = array_filter($notifications, fn($n) => $n['status'] === 'Sent');
                    echo count($sent_notifications);
                    ?>
                </div>
                <div class="stat-label">Sent Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $scheduled_notifications = array_filter($notifications, fn($n) => $n['status'] === 'Scheduled');
                    echo count($scheduled_notifications);
                    ?>
                </div>
                <div class="stat-label">Scheduled</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $total_reads = array_sum(array_column($notifications, 'read_count'));
                    $total_users = max(1, array_sum(array_column($notifications, 'total_users')));
                    $avg_read_rate = $total_reads > 0 ? round(($total_reads / $total_users) * 100, 1) : 0;
                    echo $avg_read_rate . '%';
                    ?>
                </div>
                <div class="stat-label">Average Read Rate</div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button active" onclick="showTab('create')">
                <i class="fas fa-plus"></i> Create Notification
            </button>
            <button class="tab-button" onclick="showTab('manage')">
                <i class="fas fa-cog"></i> Manage Notifications
            </button>
        </div>

        <!-- Create Notification Tab -->
        <div id="create-tab" class="tab-content active">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-plus"></i>
                    Create New Notification
                </h2>
            </div>

            <div class="form-card">
                <form method="POST">
                    <input type="hidden" name="action" value="create_notification">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">
                                <i class="fas fa-heading"></i>
                                Title *
                            </label>
                            <input type="text" id="title" name="title" required
                                   placeholder="Enter notification title">
                        </div>
                        <div class="form-group">
                            <label for="type">
                                <i class="fas fa-tag"></i>
                                Type
                            </label>
                            <select id="type" name="type">
                                <option value="System">System</option>
                                <option value="Schedule">Schedule</option>
                                <option value="Policy">Policy</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Holiday">Holiday</option>
                                <option value="Training">Training</option>
                                <option value="Performance">Performance</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority">
                                <i class="fas fa-exclamation-triangle"></i>
                                Priority
                            </label>
                            <select id="priority" name="priority">
                                <option value="Low">Low</option>
                                <option value="Normal" selected>Normal</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="target_audience">
                                <i class="fas fa-users"></i>
                                Target Audience
                            </label>
                            <select id="target_audience" name="target_audience" onchange="updateTargetUsers()">
                                <option value="All">All Users</option>
                                <option value="Admins">Admins Only</option>
                                <option value="Users">Regular Users Only</option>
                                <option value="Specific">Specific Users</option>
                            </select>
                        </div>
                        <div class="form-group" id="target_users_group" style="display: none;">
                            <label for="target_users">
                                <i class="fas fa-user-check"></i>
                                Specific Users (comma-separated IDs)
                            </label>
                            <input type="text" id="target_users" name="target_users"
                                   placeholder="e.g., 1,5,12,15">
                        </div>
                        <div class="form-group">
                            <label for="scheduled_at">
                                <i class="fas fa-clock"></i>
                                Schedule For (optional)
                            </label>
                            <input type="datetime-local" id="scheduled_at" name="scheduled_at">
                        </div>
                        <div class="form-group">
                            <label for="expires_at">
                                <i class="fas fa-calendar-times"></i>
                                Expires At (optional)
                            </label>
                            <input type="datetime-local" id="expires_at" name="expires_at">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>
                                <i class="fas fa-paper-plane"></i>
                                Delivery Methods
                            </label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="delivery_inapp" name="delivery_methods[]" value="inapp" checked>
                                    <label for="delivery_inapp">In-App</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="delivery_email" name="delivery_methods[]" value="email">
                                    <label for="delivery_email">Email</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="delivery_sms" name="delivery_methods[]" value="sms">
                                    <label for="delivery_sms">SMS (if configured)</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="message">
                                <i class="fas fa-message"></i>
                                Message *
                            </label>
                            <textarea id="message" name="message" required rows="6"
                                    placeholder="Enter your notification message here..."></textarea>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            Create Notification
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-redo"></i>
                            Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manage Notifications Tab -->
        <div id="manage-tab" class="tab-content">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-cog"></i>
                    Manage Notifications
                </h2>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Read Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                                    No notifications found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <tr class="notification-row">
                                    <td>
                                        <div class="notification-title">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </div>
                                        <div class="notification-date">
                                            By <?php echo htmlspecialchars($notification['created_by_name'] ?? 'Admin'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: rgba(37, 99, 235, 0.1); color: var(--primary-color);">
                                            <?php echo htmlspecialchars($notification['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-<?php echo strtolower($notification['priority']); ?>">
                                            <?php echo htmlspecialchars($notification['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($notification['status']); ?>">
                                            <?php echo htmlspecialchars($notification['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="read-rate">
                                            <strong><?php echo $notification['read_count']; ?></strong> /
                                            <?php echo $notification['total_users']; ?>
                                            (<?php
                                            $rate = $notification['total_users'] > 0 ?
                                                round(($notification['read_count'] / $notification['total_users']) * 100, 1) : 0;
                                            echo $rate . '%';
                                            ?>)
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($notification['status'] === 'Draft'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="send_notification">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        <i class="fas fa-paper-plane"></i> Send
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                                <input type="hidden" name="action" value="delete_notification">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7">
                                        <div class="notification-message">
                                            <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                        </div>
                                        <?php if ($notification['target_audience'] === 'Specific'): ?>
                                            <div class="notification-meta">
                                                <small>
                                                    <i class="fas fa-users"></i>
                                                    Target: Specific users (<?php echo htmlspecialchars($notification['target_users']); ?>)
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($notification['scheduled_at']): ?>
                                            <div class="notification-meta">
                                                <small>
                                                    <i class="fas fa-clock"></i>
                                                    Scheduled: <?php echo date('M d, Y H:i', strtotime($notification['scheduled_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($notification['expires_at']): ?>
                                            <div class="notification-meta">
                                                <small>
                                                    <i class="fas fa-calendar-times"></i>
                                                    Expires: <?php echo date('M d, Y H:i', strtotime($notification['expires_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
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

        function updateTargetUsers() {
            const targetAudience = document.getElementById('target_audience').value;
            const targetUsersGroup = document.getElementById('target_users_group');

            if (targetAudience === 'Specific') {
                targetUsersGroup.style.display = 'block';
            } else {
                targetUsersGroup.style.display = 'none';
                document.getElementById('target_users').value = '';
            }
        }

        // Set minimum dates for datetime inputs
        document.addEventListener('DOMContentLoaded', function() {
            const scheduledAt = document.getElementById('scheduled_at');
            const expiresAt = document.getElementById('expires_at');
            const now = new Date();

            if (scheduledAt) {
                scheduledAt.min = now.toISOString().slice(0, 16);
            }

            if (expiresAt) {
                expiresAt.min = now.toISOString().slice(0, 16);
            }
        });
    </script>
</body>
</html>