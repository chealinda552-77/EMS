<<<<<<< HEAD
# EMS
=======
# Employee Fingerprint Management System

PHP + MySQL employee management system with fingerprint-based clock-in and clock-out flow.

## Stack

- PHP (XAMPP)
- MySQL
- HTML/CSS/JavaScript
- Bootstrap 5

## Features

- Admin login
- Employee CRUD (with fingerprint ID mapping)
- Clock-in / clock-out attendance records
- Fingerprint scan modes:
  - Manual (testing/demo)
  - API mode (hardware scanner service integration)
  - WebAuthn mode (experimental browser biometric verification + mapped fingerprint ID)
  - Thumb mode (manual thumb scan/capture with thumb method logging)
- Attendance reports and CSV export
- Leave management (request, approve, reject)
- Shift start setting + late detection
- Dashboard summary metrics

## Setup (XAMPP)

1. Copy project folder to:
   - `C:\xampp\htdocs\Team_Project`
2. Start Apache and MySQL in XAMPP Control Panel.
3. Open phpMyAdmin and run:
   - `database/schema.sql`
4. Open browser:
   - `http://localhost/Team_Project/index.php`
5. Default login:
   - Username: `admin`
   - Password: `admin123`

## Scanner API Integration

1. Go to **Settings** page.
2. Set scanner mode to `API`.
3. Set endpoint (example):
   - `http://127.0.0.1:5000/scan`
4. Your scanner service should return JSON:
   - `{"fingerprint_id":"FP-1001"}`

## File Map

- `index.php`: app entry + routing
- `includes/`: auth, helper, layout templates
- `pages/`: dashboard, employees, attendance, reports, leaves, settings
- `api/`: clock API, scanner proxy API, CSV export
- `database/schema.sql`: DB schema + seed data
>>>>>>> a7e4f28 (Initial commit)
