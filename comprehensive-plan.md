# Student Attendance System with QR Code - Comprehensive Plan

## Project Overview
Creating a PHP-based student attendance system using QR codes with WebSocket real-time notifications and email fallback notifications.

## Technology Stack
- **Backend**: PHP 8.x
- **Database**: MySQL with phpMyAdmin
- **Frontend**: Bootstrap 5 + JavaScript
- **QR Code**: PHP QR Code library (endroid/qr-code)
- **Real-time**: WebSocket (Ratchet PHP)
- **Email**: PHPMailer
- **Web Server**: Apache/Nginx

## Project Structure
```
student-attendance-system/
├── config/
│   ├── database.php          # MySQL connection
│   ├── websocket.php         # WebSocket server config
│   └── email.php             # PHPMailer config
├── includes/
│   ├── auth.php              # Authentication functions
│   ├── qr_generator.php      # QR code generation
│   ├── attendance.php        # Attendance processing
│   ├── websocket_server.php  # WebSocket server
│   └── email_notifications.php # Email notifications
├── api/
│   ├── scan.php              # QR scan endpoint
│   ├── attendance.php        # Attendance API
│   ├── websocket.php         # WebSocket API
│   └── notifications.php     # Notification API
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css
│   │   └── custom.css
│   ├── js/
│   │   ├── qr-scanner.js
│   │   ├── websocket-client.js
│   │   └── attendance.js
│   └── images/
├── pages/
│   ├── index.php            # Login page
│   ├── dashboard.php        # Admin dashboard
│   ├── student-dashboard.php # Student view
│   ├── scan-qr.php          # QR scanning page
│   ├── attendance-report.php # Reports
│   └── teacher-dashboard.php # Teacher view
├── database/
│   ├── schema.sql           # MySQL database schema
│   └── sample-data.sql      # Sample data
├── websocket/
│   └── server.php           # WebSocket server
├── composer.json            # PHP dependencies
└── vendor/                  # Composer dependencies
```

## Database Design (MySQL)

### Tables Structure
```sql
-- Students table
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    class VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teachers table
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('teacher', 'admin') DEFAULT 'teacher',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Classes table
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    teacher_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- Attendance table
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('present', 'late', 'absent') DEFAULT 'present',
    qr_code VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    UNIQUE KEY unique_attendance (student_id, class_id, date)
);

-- QR codes table
CREATE TABLE qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    qr_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    teacher_id INT,
    message TEXT NOT NULL,
    type ENUM('websocket', 'email') DEFAULT 'websocket',
    status ENUM('sent', 'pending', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);
```

## Core Features Implementation

### 1. Authentication System
- **Student Login**: NIS + password
- **Teacher/Admin Login**: Email + password
- **Session Management**: PHP sessions with security tokens
- **Password Hashing**: bcrypt algorithm

### 2. QR Code System
- **Generation**: PHP QR Code library (endroid/qr-code)
- **Scanning**: JavaScript QR scanner (html5-qrcode)
- **Security**: Unique tokens with 15-minute expiration
- **Format**: `student_id|class_id|timestamp|hash`
- **Validation**: Server-side token verification

### 3. Real-time Notifications (WebSocket)
- **Server**: Ratchet PHP WebSocket library
- **Client**: JavaScript WebSocket API
- **Message Types**:
  - Attendance confirmation
  - Late arrival alerts
  - Daily attendance reminders
  - System notifications
- **Connection Management**: Auto-reconnection on disconnect

### 4. Email Notifications (Fallback)
- **Library**: PHPMailer with SMTP
- **Features**:
  - HTML email templates
  - Queue system for bulk emails
  - Error handling and retries
  - Attendance summaries

### 5. User Interface Design
- **Framework**: Bootstrap 5 for responsive design
- **Mobile-First**: Optimized for mobile QR scanning
- **Pages**:
  - Login page with role selection
  - Student dashboard with attendance history
  - Teacher dashboard with QR generation
  - Admin panel for user management
  - Attendance reports and analytics

## Security Measures
- **Input Validation**: All user inputs sanitized
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Output escaping
- **CSRF Protection**: Token-based validation
- **Rate Limiting**: QR scan attempts limited
- **Session Security**: Secure session configuration
- **Password Policy**: Strong password requirements

## API Endpoints
```
POST /api/login.php          # User authentication
POST /api/scan-qr.php        # QR code scanning
GET  /api/attendance.php     # Get attendance data
POST /api/attendance.php     # Mark attendance
GET  /api/generate-qr.php    # Generate new QR code
POST /api/notifications.php  # Send notifications
GET  /api/reports.php        # Attendance reports
```

## Required PHP Libraries (Composer)
```json
{
    "require": {
        "endroid/qr-code": "^4.0",
        "phpmailer/phpmailer": "^6.0",
        "ratchet/pawl": "^0.4",
        "ratchet/ratchet": "^0.4"
    }
}
```

## Implementation Steps

### Phase 1: Database Setup
1. Create MySQL database
2. Execute schema.sql
3. Insert sample data
4. Configure database connection

### Phase 2: Core Backend
1. Authentication system
2. QR code generation/validation
3. Attendance processing
4. Basic API endpoints

### Phase 3: Frontend Development
1. Login page
2. Student dashboard
3. Teacher dashboard
4. QR scanning interface

### Phase 4: Real-time Features
1. WebSocket server setup
2. Client-side WebSocket integration
3. Real-time notifications
4. Email fallback system

### Phase 5: Testing & Deployment
1. Unit testing
2. Integration testing
3. Security testing
4. Performance optimization
5. Production deployment

## File Dependencies
- **config/database.php** → All database operations
- **includes/auth.php** → All pages requiring authentication
- **includes/qr_generator.php** → QR generation and scanning
- **assets/js/websocket-client.js** → Real-time features
- **composer.json** → External library management

## Development Environment Requirements
- PHP 8.0+
- MySQL 8.0+
- Apache/Nginx web server
- Composer for dependency management
- phpMyAdmin for database management
- SSL certificate for production (WebSocket security)

## Deployment Considerations
- **Server Requirements**: PHP 8.0+, MySQL, WebSocket support
- **Security**: HTTPS required for WebSocket connections
- **Performance**: Database indexing, query optimization
- **Backup**: Regular database backups
- **Monitoring**: Error logging, performance monitoring

This comprehensive plan provides a complete roadmap for developing the student attendance system with QR codes and real-time notifications using PHP and MySQL.
