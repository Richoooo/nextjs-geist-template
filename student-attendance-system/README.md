# Student Attendance System with QR Code

A comprehensive PHP-based student attendance system using QR codes with real-time WebSocket notifications and email fallback.

## Features

- 📱 **QR Code Based Attendance**: Students scan QR codes to mark attendance
- ⚡ **Real-time Notifications**: WebSocket-based live updates for teachers
- 📧 **Email Notifications**: Fallback email notifications using PHPMailer
- 👨‍🎓 **Student Dashboard**: View attendance history and scan QR codes
- 👨‍🏫 **Teacher Dashboard**: Generate QR codes and monitor attendance
- 📊 **Admin Panel**: Comprehensive reporting and user management
- 📈 **Analytics**: Detailed attendance statistics and reports
- 🔐 **Secure Authentication**: Role-based access control
- 📱 **Mobile Responsive**: Works perfectly on all devices

## Technology Stack

- **Backend**: PHP 8.x
- **Database**: MySQL 8.x
- **Frontend**: Bootstrap 5 + JavaScript
- **QR Code**: Endroid QR Code library
- **Real-time**: WebSocket (Ratchet PHP)
- **Email**: PHPMailer
- **Web Server**: Apache/Nginx

## Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Composer (for dependency management)
- Apache/Nginx web server
- SSL certificate (recommended for production)

## Installation

### 1. Clone or Download

```bash
# If using git
git clone <repository-url>
cd student-attendance-system

# Or download and extract the ZIP file
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Database Setup

1. Create a MySQL database:
```sql
CREATE DATABASE student_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the database schema:
```bash
mysql -u your_username -p student_attendance < database/schema.sql
```

3. Import sample data (optional):
```bash
mysql -u your_username -p student_attendance < database/sample-data.sql
```

### 4. Configuration

1. **Database Configuration**:
   Edit `config/database.php` and update the database credentials:
   ```php
   private $host = 'localhost';
   private $db_name = 'student_attendance';
   private $username = 'your_username';
   private $password = 'your_password';
   ```

2. **Email Configuration**:
   Edit `config/email.php` and update SMTP settings:
   ```php
   private $smtp_host = 'smtp.gmail.com';
   private $smtp_port = 587;
   private $smtp_username = 'your-email@gmail.com';
   private $smtp_password = 'your-app-password';
   private $from_email = 'your-email@gmail.com';
   ```

3. **WebSocket Configuration**:
   The WebSocket server runs on port 8080 by default. You can change this in `config/websocket.php`.

### 5. Web Server Setup

#### Apache
Create a virtual host or place the project in your web root:
```apache
<VirtualHost *:80>
    ServerName attendance.local
    DocumentRoot /path/to/student-attendance-system
    
    <Directory /path/to/student-attendance-system>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name attendance.local;
    root /path/to/student-attendance-system;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Start WebSocket Server

```bash
cd websocket
php server.php
```

The WebSocket server should start and listen on port 8080.

### 7. Set Permissions

```bash
# Make sure the web server can write to assets/images for QR codes
chmod 755 assets/images
chown www-data:www-data assets/images  # For Apache
# or
chown nginx:nginx assets/images        # For Nginx
```

## Usage

### Default Login Credentials

After importing sample data, you can use these credentials:

**Admin:**
- Email: `admin@school.com`
- Password: `password`

**Teacher:**
- Email: `john@school.com`
- Password: `password`

**Student:**
- NIS: `2024001`
- Password: `password`

### For Students

1. Login with your NIS and password
2. Go to "Mark Attendance" section
3. Click "Start Scanner" to activate camera
4. Scan the QR code displayed by your teacher
5. Attendance will be marked automatically

### For Teachers

1. Login with your email and password
2. Select a class from the dropdown
3. Click "Generate QR Code"
4. Display the QR code to students
5. Monitor real-time attendance as students scan

### For Admins

1. Access admin dashboard for comprehensive reports
2. Manage users, classes, and system settings
3. Export attendance data in various formats
4. View detailed analytics and statistics

## API Endpoints

### Authentication
- `POST /api/login.php` - User login
- `GET /api/logout.php` - User logout

### QR Codes
- `POST /api/qr-codes.php?action=generate` - Generate QR code
- `GET /api/qr-codes.php?action=active` - Get active QR codes
- `POST /api/qr-codes.php?action=deactivate` - Deactivate QR code

### Attendance
- `POST /api/scan.php` - Mark attendance via QR scan
- `GET /api/attendance.php?action=student_stats` - Get student statistics
- `GET /api/attendance.php?action=class_stats` - Get class statistics
- `GET /api/attendance.php?action=export` - Export attendance data

## WebSocket Events

### Client to Server
- `auth` - Authenticate user
- `attendance_scan` - Mark attendance
- `get_attendance` - Get attendance data
- `ping` - Heartbeat

### Server to Client
- `auth_success` - Authentication successful
- `attendance_success` - Attendance marked
- `new_attendance` - New attendance notification
- `error` - Error message

## File Structure

```
student-attendance-system/
├── api/                    # API endpoints
├── assets/                 # CSS, JS, images
├── config/                 # Configuration files
├── database/              # Database schema and sample data
├── includes/              # PHP classes and functions
├── pages/                 # Web pages
├── websocket/             # WebSocket server
├── vendor/                # Composer dependencies
├── composer.json          # PHP dependencies
└── README.md             # This file
```

## Troubleshooting

### Common Issues

1. **Database Connection Error**:
   - Check database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Verify database exists and user has proper permissions

2. **QR Code Generation Fails**:
   - Check if `assets/images` directory is writable
   - Ensure Endroid QR Code library is installed via Composer

3. **WebSocket Connection Failed**:
   - Verify WebSocket server is running (`php websocket/server.php`)
   - Check if port 8080 is available and not blocked by firewall
   - For production, use SSL/WSS connections

4. **Email Notifications Not Working**:
   - Verify SMTP settings in `config/email.php`
   - Check if "Less secure app access" is enabled for Gmail
   - Use App Passwords for Gmail with 2FA enabled

5. **Permission Denied Errors**:
   - Ensure proper file permissions for web server
   - Check PHP error logs for detailed error messages

### Debug Mode

Enable debug mode by adding this to your PHP files:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Security Considerations

1. **Change Default Passwords**: Update all default passwords immediately
2. **Use HTTPS**: Enable SSL/TLS for production deployment
3. **Database Security**: Use strong database passwords and limit user privileges
4. **File Permissions**: Set appropriate file and directory permissions
5. **Input Validation**: All user inputs are validated and sanitized
6. **Session Security**: Secure session configuration with proper timeouts

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the [MIT License](LICENSE).

## Support

For support and questions:
- Check the troubleshooting section above
- Review the code comments for implementation details
- Create an issue in the repository

## Changelog

### Version 1.0.0
- Initial release
- QR code based attendance system
- WebSocket real-time notifications
- Email notification fallback
- Student and teacher dashboards
- Admin panel with reporting
- Mobile responsive design
- Comprehensive API endpoints

---

**Note**: This system is designed for educational institutions. Customize the code according to your specific requirements and security policies.
