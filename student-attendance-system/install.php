<?php
/**
 * Installation Script
 * Student Attendance System
 */

// Check if already installed
if (file_exists(__DIR__ . '/.installed')) {
    die('System is already installed. Delete .installed file to reinstall.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            $result = testDatabaseConnection($_POST);
            if ($result['success']) {
                $success = $result['message'];
                $step = 3;
            } else {
                $error = $result['message'];
            }
            break;
            
        case 3:
            $result = setupDatabase($_POST);
            if ($result['success']) {
                $success = $result['message'];
                $step = 4;
            } else {
                $error = $result['message'];
            }
            break;
            
        case 4:
            $result = createAdminUser($_POST);
            if ($result['success']) {
                $success = $result['message'];
                $step = 5;
            } else {
                $error = $result['message'];
            }
            break;
            
        case 5:
            $result = finalizeInstallation();
            if ($result['success']) {
                $success = $result['message'];
                $step = 6;
            } else {
                $error = $result['message'];
            }
            break;
    }
}

function testDatabaseConnection($data) {
    try {
        $host = $data['db_host'] ?? 'localhost';
        $dbname = $data['db_name'] ?? 'student_attendance';
        $username = $data['db_username'] ?? 'root';
        $password = $data['db_password'] ?? '';
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Update database config file
        updateDatabaseConfig($host, $dbname, $username, $password);
        
        return ['success' => true, 'message' => 'Database connection successful!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

function setupDatabase($data) {
    try {
        require_once __DIR__ . '/config/database.php';
        $db = getDB();
        
        // Read and execute schema
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        $statements = explode(';', $schema);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $db->query($statement);
            }
        }
        
        // Import sample data if requested
        if (isset($data['import_sample']) && $data['import_sample'] === '1') {
            $sampleData = file_get_contents(__DIR__ . '/database/sample-data.sql');
            $statements = explode(';', $sampleData);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $db->query($statement);
                }
            }
        }
        
        return ['success' => true, 'message' => 'Database setup completed successfully!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database setup failed: ' . $e->getMessage()];
    }
}

function createAdminUser($data) {
    try {
        require_once __DIR__ . '/config/database.php';
        $db = getDB();
        
        $name = $data['admin_name'] ?? 'Administrator';
        $email = $data['admin_email'] ?? 'admin@school.com';
        $password = password_hash($data['admin_password'] ?? 'admin123', PASSWORD_DEFAULT);
        
        // Check if admin already exists
        $existing = $db->fetch("SELECT id FROM teachers WHERE email = ?", [$email]);
        
        if ($existing) {
            // Update existing admin
            $db->execute(
                "UPDATE teachers SET name = ?, password = ?, role = 'admin' WHERE email = ?",
                [$name, $password, $email]
            );
        } else {
            // Create new admin
            $db->execute(
                "INSERT INTO teachers (name, email, password, role) VALUES (?, ?, ?, 'admin')",
                [$name, $email, $password]
            );
        }
        
        return ['success' => true, 'message' => 'Admin user created successfully!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Admin user creation failed: ' . $e->getMessage()];
    }
}

function finalizeInstallation() {
    try {
        // Create .installed file
        file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        
        // Set proper permissions
        if (is_dir(__DIR__ . '/assets/images')) {
            chmod(__DIR__ . '/assets/images', 0755);
        }
        
        return ['success' => true, 'message' => 'Installation completed successfully!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Installation finalization failed: ' . $e->getMessage()];
    }
}

function updateDatabaseConfig($host, $dbname, $username, $password) {
    $configFile = __DIR__ . '/config/database.php';
    $config = file_get_contents($configFile);
    
    $config = preg_replace("/private \$host = '[^']*';/", "private \$host = '{$host}';", $config);
    $config = preg_replace("/private \$db_name = '[^']*';/", "private \$db_name = '{$dbname}';", $config);
    $config = preg_replace("/private \$username = '[^']*';/", "private \$username = '{$username}';", $config);
    $config = preg_replace("/private \$password = '[^']*';/", "private \$password = '{$password}';", $config);
    
    file_put_contents($configFile, $config);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Student Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <style>
        .install-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            margin: 0 5px;
            border-radius: 5px;
        }
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        .step.completed {
            background: var(--success-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="card">
            <div class="card-header text-center">
                <h2>📚 Student Attendance System</h2>
                <p>Installation Wizard</p>
            </div>
            
            <div class="card-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step == 1 ? 'active' : 'completed') : ''; ?>">
                        1. Welcome
                    </div>
                    <div class="step <?php echo $step >= 2 ? ($step == 2 ? 'active' : 'completed') : ''; ?>">
                        2. Database
                    </div>
                    <div class="step <?php echo $step >= 3 ? ($step == 3 ? 'active' : 'completed') : ''; ?>">
                        3. Setup
                    </div>
                    <div class="step <?php echo $step >= 4 ? ($step == 4 ? 'active' : 'completed') : ''; ?>">
                        4. Admin
                    </div>
                    <div class="step <?php echo $step >= 5 ? ($step == 5 ? 'active' : 'completed') : ''; ?>">
                        5. Finish
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                    <!-- Welcome Step -->
                    <h4>Welcome to Student Attendance System</h4>
                    <p>This wizard will help you set up your attendance system. Please ensure you have:</p>
                    <ul>
                        <li>✅ PHP 8.0 or higher</li>
                        <li>✅ MySQL 8.0 or higher</li>
                        <li>✅ Composer installed</li>
                        <li>✅ Web server (Apache/Nginx)</li>
                    </ul>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> Make sure you have run <code>composer install</code> before proceeding.
                    </div>
                    
                    <a href="?step=2" class="btn btn-primary">Continue</a>
                    
                <?php elseif ($step == 2): ?>
                    <!-- Database Configuration -->
                    <h4>Database Configuration</h4>
                    <p>Enter your database connection details:</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Database Host</label>
                            <input type="text" class="form-control" name="db_host" value="localhost" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" class="form-control" name="db_name" value="student_attendance" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Database Username</label>
                            <input type="text" class="form-control" name="db_username" value="root" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Database Password</label>
                            <input type="password" class="form-control" name="db_password">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Test Connection</button>
                    </form>
                    
                <?php elseif ($step == 3): ?>
                    <!-- Database Setup -->
                    <h4>Database Setup</h4>
                    <p>Configure your database tables and initial data:</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="import_sample" value="1" id="import_sample" checked>
                                <label class="form-check-label" for="import_sample">
                                    Import sample data (recommended for testing)
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>Warning:</strong> This will create database tables. Make sure you have a backup if the database contains existing data.
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Setup Database</button>
                    </form>
                    
                <?php elseif ($step == 4): ?>
                    <!-- Admin User Creation -->
                    <h4>Create Admin User</h4>
                    <p>Create your administrator account:</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Admin Name</label>
                            <input type="text" class="form-control" name="admin_name" value="Administrator" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Email</label>
                            <input type="email" class="form-control" name="admin_email" value="admin@school.com" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Password</label>
                            <input type="password" class="form-control" name="admin_password" required>
                            <div class="form-text">Use a strong password for security</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Create Admin</button>
                    </form>
                    
                <?php elseif ($step == 5): ?>
                    <!-- Finalization -->
                    <h4>Finalize Installation</h4>
                    <p>Complete the installation process:</p>
                    
                    <div class="alert alert-info">
                        <strong>Next Steps:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Start the WebSocket server: <code>php websocket/server.php</code></li>
                            <li>Configure email settings in <code>config/email.php</code></li>
                            <li>Set up SSL certificate for production</li>
                            <li>Configure your web server virtual host</li>
                        </ul>
                    </div>
                    
                    <form method="POST">
                        <button type="submit" class="btn btn-success">Complete Installation</button>
                    </form>
                    
                <?php elseif ($step == 6): ?>
                    <!-- Installation Complete -->
                    <div class="text-center">
                        <h4 class="text-success">🎉 Installation Complete!</h4>
                        <p>Your Student Attendance System is now ready to use.</p>
                        
                        <div class="alert alert-success">
                            <strong>Installation Summary:</strong>
                            <ul class="mb-0 mt-2 text-start">
                                <li>✅ Database configured and tables created</li>
                                <li>✅ Admin user created</li>
                                <li>✅ System files configured</li>
                                <li>✅ Installation completed</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Default Login Credentials:</strong><br>
                            <strong>Admin:</strong> admin@school.com / [your password]<br>
                            <strong>Teacher:</strong> john@school.com / password<br>
                            <strong>Student:</strong> 2024001 / password
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="pages/index.php" class="btn btn-primary btn-lg">
                                🚀 Go to Login Page
                            </a>
                            <a href="README.md" class="btn btn-outline-info">
                                📖 Read Documentation
                            </a>
                        </div>
                        
                        <div class="mt-4">
                            <small class="text-muted">
                                Remember to delete this install.php file for security reasons.
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
