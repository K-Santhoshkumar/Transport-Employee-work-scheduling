<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Transport Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/animations.css">
    <link rel="stylesheet" href="../assets/responsive.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--success-color));
            color: white;
            padding: 4rem 0;
            text-align: center;
            margin-bottom: 3rem;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
        }

        .about-section {
            background: var(--surface);
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .section-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
            line-height: 1.8;
            color: var(--text-secondary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
            padding: 0 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            background: rgba(37, 99, 235, 0.1);
            border-radius: var(--border-radius);
            border: 1px solid var(--primary-color);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .timeline {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .timeline-item {
            display: flex;
            margin-bottom: 2rem;
            align-items: center;
        }

        .timeline-year {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-right: 2rem;
            min-width: 100px;
        }

        .timeline-content {
            flex: 1;
            padding: 1rem;
            background: var(--background);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
            padding: 0 2rem;
        }

        .value-card {
            text-align: center;
            padding: 2rem;
            background: var(--background);
            border-radius: var(--border-radius);
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .value-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .value-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .value-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .value-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .fleet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
            padding: 0 2rem;
        }

        .fleet-card {
            background: var(--background);
            padding: 2rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border);
        }

        .fleet-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .fleet-icon {
            color: var(--success-color);
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }

            .timeline-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .timeline-year {
                margin-right: 0;
                margin-bottom: 1rem;
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

    <main>
        <section class="hero-section">
            <div class="container">
                <h1 class="hero-title">Transport Employee Work Scheduling System</h1>
                <p class="hero-subtitle">Efficient Workforce Management for Modern Transportation</p>
            </div>
        </section>

        <section class="about-section">
            <div class="container">
                <h2 class="section-title">Our Story</h2>
                <div class="section-content">
                    <p>Founded with a vision to revolutionize transportation workforce management, our system has been at the forefront of innovation in employee scheduling and logistics coordination. We understand the complexities of managing transportation operations and have built comprehensive solutions to address these challenges.</p>
                </div>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">15+</div>
                <div class="stat-label">Years of Service</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">500+</div>
                <div class="stat-label">Employees Managed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">50+</div>
                <div class="stat-label">Routes Covered</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">100%</div>
                <div class="stat-label">Safety Record</div>
            </div>
        </section>

        <section class="about-section">
            <div class="container">
                <h2 class="section-title">Our Journey</h2>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-year">2009</div>
                        <div class="timeline-content">
                            <h3>Company Foundation</h3>
                            <p>Started with a small fleet and a big vision to transform transportation management.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-year">2015</div>
                        <div class="timeline-content">
                            <h3>Digital Transformation</h3>
                            <p>Launched our first digital scheduling system to streamline operations.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-year">2020</div>
                        <div class="timeline-content">
                            <h3>System Expansion</h3>
                            <p>Expanded our services and upgraded to a comprehensive workforce management platform.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-year">2024</div>
                        <div class="timeline-content">
                            <h3>Modern Platform</h3>
                            <p>Deployed the current advanced system with real-time analytics and mobile support.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-section">
            <div class="container">
                <h2 class="section-title">Mission & Vision</h2>
                <div class="section-content">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Our Mission</h3>
                    <p>To provide exceptional transportation workforce management solutions that prioritize employee welfare, operational efficiency, and passenger safety through innovative technology and dedicated service.</p>

                    <h3 style="color: var(--primary-color); margin: 2rem 0 1rem;">Our Vision</h3>
                    <p>To be the leading transportation management platform, setting industry standards for efficiency, reliability, and employee satisfaction while driving sustainable growth in the transportation sector.</p>
                </div>
            </div>
        </section>

        <section class="about-section">
            <div class="container">
                <h2 class="section-title">Core Values</h2>
                <div class="values-grid">
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="value-title">Safety</h3>
                        <p class="value-description">Prioritizing the safety and well-being of our employees and passengers above all else.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="value-title">Reliability</h3>
                        <p class="value-description">Consistent, dependable service that our community can count on every day.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <h3 class="value-title">Efficiency</h3>
                        <p class="value-description">Optimizing operations to deliver maximum value with minimum waste.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="value-title">Integrity</h3>
                        <p class="value-description">Conducting business with honesty, transparency, and ethical principles.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-section">
            <div class="container">
                <h2 class="section-title">Transport Services</h2>
                <div class="section-content">
                    <p>We provide comprehensive transportation services across multiple routes, ensuring safe and efficient travel for all passengers. Our modern fleet is equipped with the latest technology and maintained to the highest standards.</p>
                </div>
                <div class="fleet-grid">
                    <div class="fleet-card">
                        <h3 class="fleet-title">
                            <i class="fas fa-route fleet-icon"></i>
                            Route Coverage
                        </h3>
                        <p>Serving urban and suburban areas with 50+ routes covering major commercial, residential, and educational centers.</p>
                    </div>
                    <div class="fleet-card">
                        <h3 class="fleet-title">
                            <i class="fas fa-bus fleet-icon"></i>
                            Fleet Size
                        </h3>
                        <p>Maintaining a diverse fleet of 100+ vehicles including regular, express, and luxury buses.</p>
                    </div>
                    <div class="fleet-card">
                        <h3 class="fleet-title">
                            <i class="fas fa-clock fleet-icon"></i>
                            Service Hours
                        </h3>
                        <p>Operating from 5:00 AM to 11:00 PM with extended services during peak hours and special events.</p>
                    </div>
                    <div class="fleet-card">
                        <h3 class="fleet-title">
                            <i class="fas fa-tools fleet-icon"></i>
                            Maintenance
                        </h3>
                        <p>Regular maintenance schedules and 24/7 mechanical support to ensure optimal vehicle performance.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-section">
            <div class="container">
                <h2 class="section-title">Leadership Team</h2>
                <div class="section-content">
                    <p>Our management team comprises experienced professionals dedicated to excellence in transportation management. With backgrounds in logistics, operations, human resources, and technology, we bring diverse expertise to deliver superior service.</p>
                    <br>
                    <p><strong>Key Departments:</strong></p>
                    <ul style="list-style: none; padding: 0; margin-top: 1rem;">
                        <li style="margin-bottom: 0.5rem;"><i class="fas fa-users" style="color: var(--primary-color); margin-right: 0.5rem;"></i> Human Resources & Employee Relations</li>
                        <li style="margin-bottom: 0.5rem;"><i class="fas fa-cogs" style="color: var(--primary-color); margin-right: 0.5rem;"></i> Operations & Logistics</li>
                        <li style="margin-bottom: 0.5rem;"><i class="fas fa-chart-line" style="color: var(--primary-color); margin-right: 0.5rem;"></i> Planning & Analytics</li>
                        <li style="margin-bottom: 0.5rem;"><i class="fas fa-tools" style="color: var(--primary-color); margin-right: 0.5rem;"></i> Fleet Maintenance</li>
                        <li style="margin-bottom: 0.5rem;"><i class="fas fa-headset" style="color: var(--primary-color); margin-right: 0.5rem;"></i> Customer Service & Support</li>
                    </ul>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 Transport Employee Work Scheduling System. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>