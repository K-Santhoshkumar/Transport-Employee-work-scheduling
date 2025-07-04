<?php
// Start session
session_start();

// Include database connection
include '../admin_module/database_connect.php';

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error_message = "Please fill in all fields.";
    } else {
        // Prepare SQL query to prevent SQL injection
        $sql = "SELECT id, email, password FROM USER WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                // Verify password
                if (password_verify($password, $row['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_email'] = $row['email'];
                    
                    // Redirect to dashboard
                    header("Location: user_welcome.php");
                    exit();
                } else {
                    $error_message = "Invalid email or password.";
                }
            } else {
                $error_message = "Invalid email or password.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Database error. Please try again.";
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - Transport Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --accent-color: #06b6d4;
            --success-color: #059669;
            --danger-color: #dc2626;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-light: #94a3b8;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.6;
        }

        .login-container {
            background: var(--surface);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
            margin: 2rem;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: var(--text-primary);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .login-header h1 i {
            color: var(--primary-color);
            font-size: 2.5rem;
        }

        .login-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            padding-left: 3rem;
            border: 2px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--surface);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            transform: translateY(-1px);
        }

        .form-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.125rem;
            margin-top: 0.75rem;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 1.5rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .login-links {
            text-align: center;
        }

        .login-links p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .login-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            margin: 0 1rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .login-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .user-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-message {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            padding: 1rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--danger-color);
            margin-bottom: 1rem;
            font-weight: 500;
            text-align: center;
        }

        .back-link {
            position: absolute;
            top: 1rem;
            left: 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }

            .login-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
        
        <div class="login-header">
            <div class="user-badge">
                <i class="fas fa-user"></i>
                Employee Access
            </div>
            <h1>
                <i class="fas fa-bus"></i>
                User Portal
            </h1>
            <p>Access your transport management dashboard</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="email">Email Address</label>
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="Enter your email address" required />
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Enter your password" required />
            </div>

            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                Sign In
            </button>

            <div class="login-links">
                <p>Don't have an account?</p>
                <a href="user_signup.php">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </a>
            </div>
        </form>
    </div>
</body>
</html>