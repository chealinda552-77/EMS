CREATE DATABASE IF NOT EXISTS employee_fingerprint CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE employee_fingerprint;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(30) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    department_id INT UNSIGNED NULL,
    position VARCHAR(120) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    fingerprint_id VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS attendance_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    clock_in_at DATETIME NOT NULL,
    clock_out_at DATETIME NULL,
    clock_in_method ENUM('manual', 'api', 'webauthn', 'thumb', 'fingerprint', 'card', 'face', 'qr') NOT NULL DEFAULT 'manual',
    clock_out_method ENUM('manual', 'api', 'webauthn', 'thumb', 'fingerprint', 'card', 'face', 'qr') NULL,
    is_late TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attendance_employee (employee_id),
    INDEX idx_attendance_clock_in (clock_in_at),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_employee (employee_id),
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS scanner_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    scanner_mode ENUM('manual', 'api', 'webauthn', 'thumb') NOT NULL DEFAULT 'manual',
    api_endpoint VARCHAR(255) NULL,
    shift_start_time TIME NOT NULL DEFAULT '09:00:00',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE attendance_logs
    MODIFY clock_in_method ENUM('manual', 'api', 'webauthn', 'thumb', 'fingerprint', 'card', 'face', 'qr') NOT NULL DEFAULT 'manual',
    MODIFY clock_out_method ENUM('manual', 'api', 'webauthn', 'thumb', 'fingerprint', 'card', 'face', 'qr') NULL;

ALTER TABLE scanner_settings
    MODIFY scanner_mode ENUM('manual', 'api', 'webauthn', 'thumb') NOT NULL DEFAULT 'manual',
    ADD COLUMN IF NOT EXISTS qr_secret VARCHAR(100) NOT NULL DEFAULT 'TEAM_PROJECT_QR',
    ADD COLUMN IF NOT EXISTS allowed_network_prefix VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS office_latitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS office_longitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS qr_max_distance_meters INT UNSIGNED NOT NULL DEFAULT 20;

ALTER TABLE users
    MODIFY role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'admin',
    ADD COLUMN IF NOT EXISTS employee_id INT UNSIGNED NULL UNIQUE;

INSERT INTO users (username, password_hash, role)
VALUES ('admin', '$2y$10$Df37Y9NayngToGUhTjTcCeUq6sHVJ336YEltVsWqSUkX40coJMyly', 'admin')
ON DUPLICATE KEY UPDATE username = VALUES(username);

INSERT INTO departments (name)
VALUES ('Human Resources'), ('Operations'), ('IT')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO employees (employee_code, first_name, last_name, email, phone, department_id, position, status, fingerprint_id)
VALUES
    ('EMP-1001', 'John', 'Carter', 'john.carter@example.com', '555-1001', 3, 'Developer', 'active', 'FP-1001'),
    ('EMP-1002', 'Mina', 'Lopez', 'mina.lopez@example.com', '555-1002', 2, 'Operations Specialist', 'active', 'FP-1002'),
    ('EMP-1003', 'Anna', 'Reyes', 'anna.reyes@example.com', '555-1003', 1, 'HR Associate', 'active', 'FP-1003')
ON DUPLICATE KEY UPDATE employee_code = VALUES(employee_code);

INSERT INTO scanner_settings (
    id, scanner_mode, api_endpoint, shift_start_time, qr_secret, allowed_network_prefix, office_latitude, office_longitude, qr_max_distance_meters
)
VALUES (1, 'manual', 'http://127.0.0.1:5000/scan', '09:00:00', 'TEAM_PROJECT_QR', '192.168.1.', NULL, NULL, 20)
ON DUPLICATE KEY UPDATE id = VALUES(id);
