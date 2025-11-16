<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = $_POST['subject'] ?? '';
    $message_text = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message_text)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($message_text) < 10) {
        $error = 'Message must be at least 10 characters long.';
    } elseif (strlen($message_text) > 1000) {
        $error = 'Message must not exceed 1000 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO contacts (name, email, subject, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $email, $subject, $message_text]);
            $message = 'Your message has been sent successfully. We will get back to you soon!';

            // Clear form
            $_POST = [];
        } catch (PDOException $e) {
            $error = 'Failed to send message. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Transport Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin: 2rem 0;
        }

        .contact-info {
            background: var(--surface);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .contact-form {
            background: var(--surface);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .info-section {
            margin-bottom: 2.5rem;
        }

        .info-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-icon {
            color: var(--primary-color);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        .info-item i {
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }

        .quick-links {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--background);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-secondary);
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        .quick-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
        }

        .quick-link i {
            color: var(--success-color);
        }

        .quick-link:hover i {
            color: white;
        }

        .department-contacts {
            background: var(--background);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
        }

        .department-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .department-item:last-child {
            border-bottom: none;
        }

        .department-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .department-contact {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .char-counter {
            text-align: right;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }

        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-submit.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid white;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .success-animation {
            animation: slideInDown 0.5s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .contact-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .quick-links {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 2rem;
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

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Contact Us</h1>
            <p class="page-subtitle">We're here to help and answer any questions you might have</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success success-animation">
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

        <div class="contact-container">
            <div class="contact-info">
                <div class="info-section">
                    <h2 class="info-title">
                        <i class="fas fa-map-marker-alt info-icon"></i>
                        Office Address
                    </h2>
                    <div class="info-item">
                        <i class="fas fa-building"></i>
                        <span>Transport Management Center<br>123 Main Street<br>City Center, District 1</span>
                    </div>
                </div>

                <div class="info-section">
                    <h2 class="info-title">
                        <i class="fas fa-phone info-icon"></i>
                        Contact Numbers
                    </h2>
                    <div class="info-item">
                        <i class="fas fa-phone-alt"></i>
                        <span>Main Office: +1 (555) 123-4567</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-headset"></i>
                        <span>Support Hotline: +1 (555) 987-6543</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone-volume"></i>
                        <span>Emergency: +1 (555) 555-0123</span>
                    </div>
                </div>

                <div class="info-section">
                    <h2 class="info-title">
                        <i class="fas fa-envelope info-icon"></i>
                        Email Addresses
                    </h2>
                    <div class="info-item">
                        <i class="fas fa-at"></i>
                        <span>general@transportmanager.com</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-life-ring"></i>
                        <span>support@transportmanager.com</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user-shield"></i>
                        <span>hr@transportmanager.com</span>
                    </div>
                </div>

                <div class="info-section">
                    <h2 class="info-title">
                        <i class="fas fa-clock info-icon"></i>
                        Working Hours
                    </h2>
                    <div class="info-item">
                        <i class="fas fa-calendar-week"></i>
                        <span>Monday - Friday: 8:00 AM - 6:00 PM</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar-day"></i>
                        <span>Saturday: 9:00 AM - 2:00 PM</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar-times"></i>
                        <span>Sunday: Closed</span>
                    </div>
                </div>

                <div class="info-section">
                    <h2 class="info-title">
                        <i class="fas fa-link info-icon"></i>
                        Quick Links
                    </h2>
                    <div class="quick-links">
                        <a href="#" class="quick-link">
                            <i class="fas fa-question-circle"></i>
                            <span>FAQ Section</span>
                        </a>
                        <a href="#" class="quick-link">
                            <i class="fas fa-calendar-edit"></i>
                            <span>Schedule Request</span>
                        </a>
                        <a href="#" class="quick-link">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Emergency Info</span>
                        </a>
                        <a href="#" class="quick-link">
                            <i class="fas fa-users"></i>
                            <span>HR Department</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <h2 class="info-title">
                    <i class="fas fa-paper-plane info-icon"></i>
                    Send us a Message
                </h2>
                <form method="POST" id="contactForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Your Name *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Your Email *</label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <select id="subject" name="subject" required>
                            <option value="">Select a subject</option>
                            <option value="General Inquiry" <?php echo ($_POST['subject'] ?? '') === 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                            <option value="Technical Support" <?php echo ($_POST['subject'] ?? '') === 'Technical Support' ? 'selected' : ''; ?>>Technical Support</option>
                            <option value="Schedule Issue" <?php echo ($_POST['subject'] ?? '') === 'Schedule Issue' ? 'selected' : ''; ?>>Schedule Issue</option>
                            <option value="HR Related" <?php echo ($_POST['subject'] ?? '') === 'HR Related' ? 'selected' : ''; ?>>HR Related</option>
                            <option value="Other" <?php echo ($_POST['subject'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" rows="6" required
                                  placeholder="Please describe your inquiry in detail..."
                                  maxlength="1000"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span> / 1000 characters
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        <span>Send Message</span>
                    </button>
                </form>

                <div class="department-contacts">
                    <h3 class="info-title" style="font-size: 1.1rem;">
                        <i class="fas fa-building info-icon"></i>
                        Department Contacts
                    </h3>
                    <div class="department-item">
                        <span class="department-name">Scheduling Department</span>
                        <span class="department-contact">ext: 101</span>
                    </div>
                    <div class="department-item">
                        <span class="department-name">HR Department</span>
                        <span class="department-contact">ext: 102</span>
                    </div>
                    <div class="department-item">
                        <span class="department-name">Technical Support</span>
                        <span class="department-contact">ext: 103</span>
                    </div>
                    <div class="department-item">
                        <span class="department-name">Emergency Contacts</span>
                        <span class="department-contact">ext: 999</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 Transport Employee Work Scheduling System. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Character counter
        const messageTextarea = document.getElementById('message');
        const charCount = document.getElementById('charCount');

        messageTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });

        // Initialize character count
        charCount.textContent = messageTextarea.value.length;

        // Form submission animation
        const contactForm = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');

        contactForm.addEventListener('submit', function() {
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<span>Sending...</span>';
        });
    </script>
</body>
</html>