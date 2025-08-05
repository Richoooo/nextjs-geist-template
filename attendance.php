<?php
/**
 * Attendance API Endpoint
 * Student Attendance System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';

try {
    $attendanceManager = getAttendanceManager();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'student_stats':
            handleStudentStats($attendanceManager);
            break;
            
        case 'class_stats':
            handleClassStats($attendanceManager);
            break;
            
        case 'student_attendance':
            handleStudentAttendance($attendanceManager);
            break;
            
        case 'class_attendance':
            handleClassAttendance($attendanceManager);
            break;
            
        case 'recent_attendance':
            handleRecentAttendance($attendanceManager);
            break;
            
        case 'mark_attendance':
            handleMarkAttendance($attendanceManager);
            break;
            
        case 'update_status':
            handleUpdateStatus($attendanceManager);
            break;
            
        case 'export':
            handleExport($attendanceManager);
            break;
            
        case 'report':
            handleReport($attendanceManager);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log("Attendance API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleStudentStats($attendanceManager) {
    $studentId = (int)($_GET['student_id'] ?? 0);
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    if (!$studentId) {
        throw new Exception('Student ID is required');
    }
    
    $result = $attendanceManager->getStudentAttendanceStats($studentId, $startDate, $endDate);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleClassStats($attendanceManager) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $date = $_GET['date'] ?? null;
    
    if (!$classId) {
        throw new Exception('Class ID is required');
    }
    
    $result = $attendanceManager->getClassAttendanceStats($classId, $date);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleStudentAttendance($attendanceManager) {
    $studentId = (int)($_GET['student_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    
    if (!$studentId) {
        throw new Exception('Student ID is required');
    }
    
    $result = $attendanceManager->getStudentAttendance($studentId, $limit, $offset);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleClassAttendance($attendanceManager) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $date = $_GET['date'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    if (!$classId) {
        throw new Exception('Class ID is required');
    }
    
    $result = $attendanceManager->getClassAttendance($classId, $date, $limit, $offset);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleRecentAttendance($attendanceManager) {
    $limit = (int)($_GET['limit'] ?? 20);
    $teacherId = (int)($_GET['teacher_id'] ?? 0);
    
    $db = getDB();
    
    // Get recent attendance based on teacher's classes
    if ($teacherId) {
        $sql = "SELECT a.*, s.name as student_name, s.nis, c.class_name 
                FROM attendance a 
                JOIN students s ON a.student_id = s.id 
                JOIN classes c ON a.class_id = c.id 
                WHERE c.teacher_id = ? 
                ORDER BY a.created_at DESC 
                LIMIT ?";
        $params = [$teacherId, $limit];
    } else {
        $sql = "SELECT a.*, s.name as student_name, s.nis, c.class_name 
                FROM attendance a 
                JOIN students s ON a.student_id = s.id 
                JOIN classes c ON a.class_id = c.id 
                ORDER BY a.created_at DESC 
                LIMIT ?";
        $params = [$limit];
    }
    
    $attendance = $db->fetchAll($sql, $params);
    
    echo json_encode([
        'success' => true,
        'data' => $attendance
    ]);
}

function handleMarkAttendance($attendanceManager) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $studentId = (int)($input['student_id'] ?? 0);
    $qrToken = $input['qr_token'] ?? '';
    
    if (!$studentId || !$qrToken) {
        throw new Exception('Student ID and QR token are required');
    }
    
    $result = $attendanceManager->markAttendance($studentId, $qrToken);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleUpdateStatus($attendanceManager) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $attendanceId = (int)($input['attendance_id'] ?? 0);
    $newStatus = $input['status'] ?? '';
    $notes = $input['notes'] ?? null;
    
    if (!$attendanceId || !$newStatus) {
        throw new Exception('Attendance ID and status are required');
    }
    
    $result = $attendanceManager->updateAttendanceStatus($attendanceId, $newStatus, $notes);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleExport($attendanceManager) {
    $format = $_GET['format'] ?? 'csv';
    $classId = (int)($_GET['class_id'] ?? 0);
    $studentId = (int)($_GET['student_id'] ?? 0);
    $startDate = $_GET['date_from'] ?? null;
    $endDate = $_GET['date_to'] ?? null;
    
    $result = $attendanceManager->generateAttendanceReport($classId ?: null, $studentId ?: null, $startDate, $endDate);
    
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        return;
    }
    
    $report = $result['data']['report'];
    $summary = $result['data']['summary'];
    
    if ($format === 'csv') {
        // Generate CSV
        $filename = 'attendance_report_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Student Name',
            'NIS',
            'Email',
            'Class',
            'Date',
            'Time In',
            'Time Out',
            'Status',
            'Teacher'
        ]);
        
        // CSV data
        foreach ($report as $record) {
            fputcsv($output, [
                $record['student_name'],
                $record['nis'],
                $record['student_email'],
                $record['class_name'],
                $record['date'],
                $record['time_in'] ?? '',
                $record['time_out'] ?? '',
                ucfirst($record['status']),
                $record['teacher_name']
            ]);
        }
        
        fclose($output);
    } else {
        // Return JSON
        echo json_encode($result);
    }
}

function handleReport($attendanceManager) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $studentId = (int)($_GET['student_id'] ?? 0);
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $groupBy = $_GET['group_by'] ?? 'date';
    
    $result = $attendanceManager->generateAttendanceReport(
        $classId ?: null, 
        $studentId ?: null, 
        $startDate, 
        $endDate
    );
    
    if ($result['success'] && $groupBy !== 'none') {
        // Group data by specified field
        $groupedData = [];
        foreach ($result['data']['report'] as $record) {
            $key = $record[$groupBy] ?? 'unknown';
            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [];
            }
            $groupedData[$key][] = $record;
        }
        
        $result['data']['grouped'] = $groupedData;
    }
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}
?>
