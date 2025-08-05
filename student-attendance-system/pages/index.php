<?php
/**
 * Login Page
 * Student Attendance System
 */

require_once __DIR__ . '/../includes/auth.php';

$auth = getAuth();
$error = '';
$success = '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $userType = $_SESSION['user_type'];
    $redirectUrl = $userType === 'student' ? 'student-dashboard.php' : 'dashboard.php';
    header("Location: {$redirectUrl}");
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = $_POST['user_type'] ?? 'student';
    
    if (empty($identifier) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = $auth->login($identifier, $password, $userType);
        
        if ($result['success']) {
            $redirectUrl = $userType === 'student' ? 'student-dashboard.php' : 'dashboard.php';
            header("Location: {$redirectUrl}");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Get error from URL parameter
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Get success message from URL parameter
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Attendance System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../assets/css/custom.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>📚 Student Attendance</h1>
                <p>QR Code Based Attendance System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_type" class="form-label">Login As</label>
                    <select class="form-control" id="user_type" name="user_type" required>
                        <option value="student" <?php echo (($_POST['user_type'] ?? 'student') === 'student') ? 'selected' : ''; ?>>
                            👨‍🎓 Student
                        </option>
                        <option value="teacher" <?php echo (($_POST['user_type'] ?? '') === 'teacher') ? 'selected' : ''; ?>>
                            👨‍🏫 Teacher/Admin
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="identifier" class="form-label">
                        <span id="identifier-label">NIS or Email</span>
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="identifier" 
                        name="identifier" 
                        placeholder="Enter your NIS or email"
                        value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                        required
                    >
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    🔐 Login
                </button>
            </form>
            
            <div class="mt-4 text-center">
                <small class="text-muted">
                    <strong>Demo Credentials:</strong><br>
                    Student: NIS: 2024001, Password: password<br>
                    Teacher: Email: john@school.com, Password: password<br>
                    Admin: Email: admin@school.com, Password: password
                </small>
            </div>
            
            <div class="mt-3 text-center">
                <small class="text-muted">
                    Need help? Contact your system administrator
                </small>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Update identifier label based on user type
        document.getElementById('user_type').addEventListener('change', function() {
            const identifierLabel = document.getElementById('identifier-label');
            const identifierInput = document.getElementById('identifier');
            
            if (this.value === 'student') {
                identifierLabel.textContent = 'NIS or Email';
                identifierInput.placeholder = 'Enter your NIS or email';
            } else {
                identifierLabel.textContent = 'Email';
                identifierInput.placeholder = 'Enter your email';
            }
        });
        
        // Auto-focus on identifier field
        document.getElementById('identifier').focus();
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const identifier = document.getElementById('identifier').value.trim();
            const password = document.getElementById('password').value;
            
            if (!identifier || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '⏳ Logging in...';
            submitBtn.disabled = true;
        });
        
        // Add some animation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.login-card').classList.add('fade-in');
        });
    </script>
</body>
</html>
