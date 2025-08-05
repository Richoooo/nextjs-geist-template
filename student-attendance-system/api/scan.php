<?php
/**
 * QR Code Scan API Endpoint
 * Student Attendance System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_once __DIR__ . '/../config/email.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    if (empty($input['qr_token']) || empty($input['student_id'])) {
        throw new Exception('Missing required fields: qr_token and student_id');
    }
    
    $qrToken = $input['qr_token'];
    $studentId = (int)$input['student_id'];
    
    // Mark attendance
    $attendanceManager = getAttendanceManager();
    $result = $attendanceManager->markAttendance($studentId, $qrToken);
    
    if ($result['success']) {
        // Send email notification if enabled
        try {
            $emailConfig = getEmailConfig();
            $db = getDB();
            
            // Get student info
            $student = $db->fetch("SELECT * FROM students WHERE id = ?", [$studentId]);
            
            if ($student) {
                $emailConfig->sendAttendanceNotification(
                    $student['email'],
                    $student['name'],
                    $result['data']['class_name'],
                    $result['data']['status'],
                    $result['data']['time_in']
                );
            }
        } catch (Exception $e) {
            // Log email error but don't fail the attendance marking
            error_log("Email notification failed: " . $e->getMessage());
        }
        
        // Log successful attendance
        error_log("Attendance marked successfully: Student ID {$studentId}, Status: {$result['data']['status']}");
        
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("QR Scan API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
