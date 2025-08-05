<?php
/**
 * Authentication Functions
 * Student Attendance System
 */

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        $this->startSession();
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login($identifier, $password, $userType = 'student') {
        try {
            if ($userType === 'student') {
                $user = $this->db->fetch(
                    "SELECT * FROM students WHERE (nis = ? OR email = ?) AND is_active = 1",
                    [$identifier, $identifier]
                );
            } else {
                $user = $this->db->fetch(
                    "SELECT * FROM teachers WHERE email = ? AND is_active = 1",
                    [$identifier]
                );
            }
            
            if (!$user || !password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }
            
            // Create session
            $sessionId = $this->generateSessionId();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Store session in database
            $this->db->execute(
                "INSERT INTO user_sessions (id, user_id, user_type, ip_address, user_agent, expires_at) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $sessionId,
                    $user['id'],
                    $userType,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $expiresAt
                ]
            );
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $userType;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['login_time'] = time();
            
            if ($userType === 'teacher') {
                $_SESSION['user_role'] = $user['role'];
            } else {
                $_SESSION['user_class'] = $user['class'];
                $_SESSION['user_nis'] = $user['nis'];
            }
            
            // Update last login
            $table = $userType === 'student' ? 'students' : 'teachers';
            $this->db->execute(
                "UPDATE {$table} SET updated_at = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'type' => $userType,
                    'role' => $user['role'] ?? null,
                    'class' => $user['class'] ?? null,
                    'nis' => $user['nis'] ?? null
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ];
        }
    }
    
    public function logout() {
        if (isset($_SESSION['session_id'])) {
            // Remove session from database
            $this->db->execute(
                "DELETE FROM user_sessions WHERE id = ?",
                [$_SESSION['session_id']]
            );
        }
        
        // Clear session
        session_unset();
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }
        
        // Check if session exists and is valid
        $session = $this->db->fetch(
            "SELECT * FROM user_sessions WHERE id = ? AND expires_at > NOW()",
            [$_SESSION['session_id']]
        );
        
        if (!$session) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $this->db->execute(
            "UPDATE user_sessions SET last_activity = NOW() WHERE id = ?",
            [$_SESSION['session_id']]
        );
        
        return true;
    }
    
    public function requireLogin($userType = null) {
        if (!$this->isLoggedIn()) {
            $this->redirectToLogin();
            exit;
        }
        
        if ($userType && $_SESSION['user_type'] !== $userType) {
            $this->redirectToLogin('Access denied');
            exit;
        }
    }
    
    public function requireRole($role) {
        $this->requireLogin('teacher');
        
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
            $this->redirectToLogin('Insufficient permissions');
            exit;
        }
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $userType = $_SESSION['user_type'];
        $userId = $_SESSION['user_id'];
        
        if ($userType === 'student') {
            return $this->db->fetch(
                "SELECT id, nis, name, email, class FROM students WHERE id = ?",
                [$userId]
            );
        } else {
            return $this->db->fetch(
                "SELECT id, name, email, role FROM teachers WHERE id = ?",
                [$userId]
            );
        }
    }
    
    public function changePassword($currentPassword, $newPassword) {
        if (!$this->isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Not logged in'
            ];
        }
        
        $user = $this->getCurrentUser();
        $userType = $_SESSION['user_type'];
        $table = $userType === 'student' ? 'students' : 'teachers';
        
        // Get current password hash
        $currentUser = $this->db->fetch(
            "SELECT password FROM {$table} WHERE id = ?",
            [$user['id']]
        );
        
        if (!password_verify($currentPassword, $currentUser['password'])) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect'
            ];
        }
        
        // Validate new password
        if (strlen($newPassword) < 6) {
            return [
                'success' => false,
                'message' => 'New password must be at least 6 characters long'
            ];
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->execute(
            "UPDATE {$table} SET password = ?, updated_at = NOW() WHERE id = ?",
            [$hashedPassword, $user['id']]
        );
        
        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
    }
    
    public function register($data, $userType = 'student') {
        try {
            // Validate required fields
            $required = $userType === 'student' 
                ? ['nis', 'name', 'email', 'class', 'password']
                : ['name', 'email', 'password'];
            
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ];
                }
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email format'
                ];
            }
            
            // Check if email already exists
            $table = $userType === 'student' ? 'students' : 'teachers';
            $existing = $this->db->fetch(
                "SELECT id FROM {$table} WHERE email = ?",
                [$data['email']]
            );
            
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'Email already exists'
                ];
            }
            
            // For students, check if NIS already exists
            if ($userType === 'student') {
                $existingNis = $this->db->fetch(
                    "SELECT id FROM students WHERE nis = ?",
                    [$data['nis']]
                );
                
                if ($existingNis) {
                    return [
                        'success' => false,
                        'message' => 'NIS already exists'
                    ];
                }
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            if ($userType === 'student') {
                $this->db->execute(
                    "INSERT INTO students (nis, name, email, class, password) VALUES (?, ?, ?, ?, ?)",
                    [$data['nis'], $data['name'], $data['email'], $data['class'], $hashedPassword]
                );
            } else {
                $role = $data['role'] ?? 'teacher';
                $this->db->execute(
                    "INSERT INTO teachers (name, email, password, role) VALUES (?, ?, ?, ?)",
                    [$data['name'], $data['email'], $hashedPassword, $role]
                );
            }
            
            return [
                'success' => true,
                'message' => 'Registration successful'
            ];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }
    
    private function generateSessionId() {
        return bin2hex(random_bytes(32));
    }
    
    private function redirectToLogin($message = null) {
        $url = '/student-attendance-system/pages/index.php';
        if ($message) {
            $url .= '?error=' . urlencode($message);
        }
        header("Location: {$url}");
    }
    
    public function cleanExpiredSessions() {
        $this->db->execute("DELETE FROM user_sessions WHERE expires_at < NOW()");
    }
    
    public function getActiveSessions($userId = null, $userType = null) {
        $sql = "SELECT * FROM user_sessions WHERE expires_at > NOW()";
        $params = [];
        
        if ($userId && $userType) {
            $sql .= " AND user_id = ? AND user_type = ?";
            $params = [$userId, $userType];
        }
        
        $sql .= " ORDER BY last_activity DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
}

// Global auth instance
function getAuth() {
    static $auth = null;
    if ($auth === null) {
        $auth = new Auth();
    }
    return $auth;
}

// Helper functions
function isLoggedIn() {
    return getAuth()->isLoggedIn();
}

function requireLogin($userType = null) {
    getAuth()->requireLogin($userType);
}

function requireRole($role) {
    getAuth()->requireRole($role);
}

function getCurrentUser() {
    return getAuth()->getCurrentUser();
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['user_name'] ?? null;
}
?>
