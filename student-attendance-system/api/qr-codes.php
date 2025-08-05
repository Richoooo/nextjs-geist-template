<?php
/**
 * QR Codes API Endpoint
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
require_once __DIR__ . '/../includes/qr_generator.php';

try {
    $qrGenerator = getQRGenerator();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Get input data
    $input = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
    }
    
    switch ($action) {
        case 'generate':
            handleGenerate($qrGenerator, $input);
            break;
            
        case 'validate':
            handleValidate($qrGenerator);
            break;
            
        case 'active':
            handleGetActive($qrGenerator);
            break;
            
        case 'deactivate':
            handleDeactivate($qrGenerator, $input);
            break;
            
        case 'cleanup':
            handleCleanup($qrGenerator);
            break;
            
        case 'stats':
            handleStats($qrGenerator);
            break;
            
        case 'bulk_generate':
            handleBulkGenerate($qrGenerator, $input);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log("QR Codes API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGenerate($qrGenerator, $input) {
    if (!$input) {
        throw new Exception('Input data required');
    }
    
    $classId = (int)($input['class_id'] ?? 0);
    $teacherId = (int)($input['teacher_id'] ?? 0);
    
    if (!$classId || !$teacherId) {
        throw new Exception('Class ID and Teacher ID are required');
    }
    
    // Verify teacher has access to this class
    $db = getDB();
    $class = $db->fetch(
        "SELECT * FROM classes WHERE id = ? AND teacher_id = ? AND is_active = 1",
        [$classId, $teacherId]
    );
    
    if (!$class) {
        throw new Exception('Class not found or access denied');
    }
    
    $result = $qrGenerator->generateQRCode($classId, $teacherId);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleValidate($qrGenerator) {
    $token = $_GET['token'] ?? '';
    
    if (!$token) {
        throw new Exception('QR token is required');
    }
    
    $result = $qrGenerator->validateQRCode($token);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleGetActive($qrGenerator) {
    $teacherId = (int)($_GET['teacher_id'] ?? 0);
    
    $result = $qrGenerator->getActiveQRCodes($teacherId ?: null);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleDeactivate($qrGenerator, $input) {
    if (!$input) {
        throw new Exception('Input data required');
    }
    
    $qrId = (int)($input['qr_id'] ?? 0);
    $teacherId = (int)($input['teacher_id'] ?? 0);
    
    if (!$qrId) {
        throw new Exception('QR ID is required');
    }
    
    $result = $qrGenerator->deactivateQRCode($qrId, $teacherId ?: null);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleCleanup($qrGenerator) {
    // Only allow admin users to perform cleanup
    requireRole('admin');
    
    $result = $qrGenerator->cleanupExpiredQRCodes();
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleStats($qrGenerator) {
    $teacherId = (int)($_GET['teacher_id'] ?? 0);
    
    $result = $qrGenerator->getQRCodeStats($teacherId ?: null);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}

function handleBulkGenerate($qrGenerator, $input) {
    if (!$input) {
        throw new Exception('Input data required');
    }
    
    $classes = $input['classes'] ?? [];
    $teacherId = (int)($input['teacher_id'] ?? 0);
    
    if (empty($classes) || !$teacherId) {
        throw new Exception('Classes array and Teacher ID are required');
    }
    
    // Verify teacher has access to all classes
    $db = getDB();
    $placeholders = str_repeat('?,', count($classes) - 1) . '?';
    $params = array_merge($classes, [$teacherId]);
    
    $accessibleClasses = $db->fetchAll(
        "SELECT id FROM classes WHERE id IN ($placeholders) AND teacher_id = ? AND is_active = 1",
        $params
    );
    
    if (count($accessibleClasses) !== count($classes)) {
        throw new Exception('Access denied to one or more classes');
    }
    
    $result = $qrGenerator->generateBulkQRCodes($classes, $teacherId);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
}
?>
