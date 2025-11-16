-- Database Schema Updates for Transport Employee Work Scheduling System
-- These tables enhance the existing system with buses, routes, and notifications functionality

-- Create buses table for fleet management
CREATE TABLE IF NOT EXISTS buses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_number VARCHAR(20) UNIQUE NOT NULL,
    bus_type ENUM('Regular', 'Express', 'Luxury') DEFAULT 'Regular',
    capacity_seats INT NOT NULL,
    capacity_standing INT DEFAULT 0,
    make VARCHAR(50),
    model VARCHAR(50),
    year_manufactured INT,
    purchase_date DATE,
    insurance_expiry DATE,
    registration_expiry DATE,
    status ENUM('Active', 'Maintenance', 'Retired') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create routes table for route management
CREATE TABLE IF NOT EXISTS routes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    route_number VARCHAR(10) UNIQUE NOT NULL,
    route_name VARCHAR(100) NOT NULL,
    start_point VARCHAR(100) NOT NULL,
    end_point VARCHAR(100) NOT NULL,
    intermediate_stops TEXT,
    distance_km DECIMAL(8,2),
    estimated_duration_minutes INT,
    peak_hours_start TIME,
    peak_hours_end TIME,
    base_fare DECIMAL(6,2),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create bus_route_assignments table for managing bus-route relationships
CREATE TABLE IF NOT EXISTS bus_route_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bus_id INT NOT NULL,
    route_id INT NOT NULL,
    effective_date DATE NOT NULL,
    end_date DATE,
    assignment_type ENUM('Permanent', 'Temporary', 'Emergency') DEFAULT 'Permanent',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (route_id) REFERENCES routes(id)
);

-- Create notifications table for communication management
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('Schedule', 'System', 'Policy', 'Emergency', 'Holiday', 'Training', 'Performance') DEFAULT 'System',
    priority ENUM('Low', 'Normal', 'High', 'Urgent') DEFAULT 'Normal',
    target_audience ENUM('All', 'Admins', 'Users', 'Specific') DEFAULT 'All',
    target_users TEXT, -- JSON array of user IDs for 'Specific' targeting
    delivery_methods JSON, -- ['inapp', 'email', 'sms']
    scheduled_at DATETIME,
    expires_at DATETIME,
    status ENUM('Draft', 'Scheduled', 'Sent', 'Expired') DEFAULT 'Draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin(id)
);

-- Create notification_reads table for tracking notification delivery
CREATE TABLE IF NOT EXISTS notification_reads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_method ENUM('inapp', 'email', 'sms') DEFAULT 'inapp',
    FOREIGN KEY (notification_id) REFERENCES notifications(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_notification_user (notification_id, user_id)
);

-- Enhance existing schedules table with bus and route references
ALTER TABLE schedules
ADD COLUMN IF NOT EXISTS bus_id INT,
ADD COLUMN IF NOT EXISTS route_id INT,
ADD COLUMN IF NOT EXISTS created_by INT,
ADD FOREIGN KEY (bus_id) REFERENCES buses(id),
ADD FOREIGN KEY (route_id) REFERENCES routes(id),
ADD FOREIGN KEY (created_by) REFERENCES admin(id);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_schedules_bus_id ON schedules(bus_id);
CREATE INDEX IF NOT EXISTS idx_schedules_route_id ON schedules(route_id);
CREATE INDEX IF NOT EXISTS idx_schedules_created_by ON schedules(created_by);
CREATE INDEX IF NOT EXISTS idx_notifications_status ON notifications(status);
CREATE INDEX IF NOT EXISTS idx_notifications_target_audience ON notifications(target_audience);
CREATE INDEX IF NOT EXISTS idx_notification_reads_user_id ON notification_reads(user_id);
CREATE INDEX IF NOT EXISTS idx_notification_reads_notification_id ON notification_reads(notification_id);
CREATE INDEX IF NOT EXISTS idx_buses_status ON buses(status);
CREATE INDEX IF NOT EXISTS idx_routes_status ON routes(status);
CREATE INDEX IF NOT EXISTS idx_bus_route_assignments_effective_date ON bus_route_assignments(effective_date);

-- Insert sample data for buses (if table is empty)
INSERT IGNORE INTO buses (registration_number, bus_type, capacity_seats, capacity_standing, make, model, year_manufactured, status) VALUES
('BUS001', 'Regular', 45, 20, 'Mercedes', 'Citaro', 2020, 'Active'),
('BUS002', 'Express', 35, 10, 'Volvo', 'B7R', 2019, 'Active'),
('BUS003', 'Luxury', 30, 0, 'Scania', 'Touring', 2021, 'Active'),
('BUS004', 'Regular', 50, 25, 'MAN', 'Lion\'s City', 2018, 'Active'),
('BUS005', 'Express', 40, 15, 'VDL', 'Citea', 2020, 'Maintenance');

-- Insert sample data for routes (if table is empty)
INSERT IGNORE INTO routes (route_number, route_name, start_point, end_point, intermediate_stops, distance_km, estimated_duration_minutes, peak_hours_start, peak_hours_end, base_fare) VALUES
('R001', 'Downtown Express', 'Central Station', 'Airport', 'City Hall, Shopping District, Business Park', 25.5, 45, '07:00:00', '09:00:00', 5.50),
('R002', 'University Route', 'North Terminal', 'University Campus', 'Library, Student Center, Sports Complex', 15.2, 30, '08:00:00', '10:00:00', 3.25),
('R003', 'Industrial Zone', 'East Station', 'Industrial Park', 'Factory A, Factory B, Warehouse District', 18.7, 35, '06:30:00', '08:30:00', 4.75),
('R004', 'Shopping Circuit', 'Mall Entrance', 'Residential Area', 'Supermarket, School, Medical Center', 12.3, 25, '09:00:00', '11:00:00', 2.50),
('R005', 'Airport Shuttle', 'Airport Terminal 1', 'Airport Terminal 2', 'Parking Lot A, Parking Lot B, Car Rental', 8.1, 15, '05:00:00', '23:00:00', 2.00);