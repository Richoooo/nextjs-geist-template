<?php
/**
 * WebSocket Configuration
 * Student Attendance System
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketConfig implements MessageComponentInterface {
    protected $clients;
    protected $db;
    protected $userConnections;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->db = getDB();
        echo "WebSocket server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
        
        // Send welcome message
        $conn->send(json_encode([
            'type' => 'system',
            'message' => 'Connected to attendance system',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Invalid message format');
                return;
            }

            switch ($data['type']) {
                case 'auth':
                    $this->handleAuth($from, $data);
                    break;
                    
                case 'attendance_scan':
                    $this->handleAttendanceScan($from, $data);
                    break;
                    
                case 'get_attendance':
                    $this->handleGetAttendance($from, $data);
                    break;
                    
                case 'ping':
                    $this->handlePing($from);
                    break;
                    
                default:
                    $this->sendError($from, 'Unknown message type');
            }
            
        } catch (Exception $e) {
            error_log("WebSocket message error: " . $e->getMessage());
            $this->sendError($from, 'Server error occurred');
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from user connections
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleAuth($conn, $data) {
        if (!isset($data['user_id']) || !isset($data['user_type'])) {
            $this->sendError($conn, 'Missing authentication data');
            return;
        }

        $userId = $data['user_id'];
        $userType = $data['user_type'];
        
        // Verify user exists
        if ($userType === 'student') {
            $user = $this->db->fetch("SELECT id, name FROM students WHERE id = ? AND is_active = 1", [$userId]);
        } else {
            $user = $this->db->fetch("SELECT id, name FROM teachers WHERE id = ? AND is_active = 1", [$userId]);
        }
        
        if (!$user) {
            $this->sendError($conn, 'Invalid user');
            return;
        }
        
        // Store connection
        $this->userConnections[$userId . '_' . $userType] = $conn;
        
        $conn->send(json_encode([
            'type' => 'auth_success',
            'message' => 'Authentication successful',
            'user' => $user,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        echo "User authenticated: {$user['name']} ({$userType})\n";
    }

    private function handleAttendanceScan($conn, $data) {
        if (!isset($data['qr_token']) || !isset($data['student_id'])) {
            $this->sendError($conn, 'Missing scan data');
            return;
        }

        $qrToken = $data['qr_token'];
        $studentId = $data['student_id'];
        
        // Verify QR code
        $qrCode = $this->db->fetch(
            "SELECT qc.*, c.class_name 
             FROM qr_codes qc 
             JOIN classes c ON qc.class_id = c.id 
             WHERE qc.qr_token = ? AND qc.is_active = 1 AND qc.expires_at > NOW()",
            [$qrToken]
        );
        
        if (!$qrCode) {
            $this->sendError($conn, 'Invalid or expired QR code');
            return;
        }
        
        // Check if already marked today
        $existing = $this->db->fetch(
            "SELECT id FROM attendance 
             WHERE student_id = ? AND class_id = ? AND date = CURDATE()",
            [$studentId, $qrCode['class_id']]
        );
        
        if ($existing) {
            $this->sendError($conn, 'Attendance already marked for today');
            return;
        }
        
        // Get student info
        $student = $this->db->fetch("SELECT * FROM students WHERE id = ?", [$studentId]);
        if (!$student) {
            $this->sendError($conn, 'Student not found');
            return;
        }
        
        // Determine status (present or late)
        $currentTime = date('H:i:s');
        $lateThreshold = $this->db->getSetting('late_threshold_minutes', 10);
        $classStartTime = '08:00:00'; // You can make this configurable per class
        
        $status = 'present';
        if (strtotime($currentTime) > strtotime($classStartTime) + ($lateThreshold * 60)) {
            $status = 'late';
        }
        
        // Mark attendance
        $this->db->execute(
            "INSERT INTO attendance (student_id, class_id, date, time_in, status, qr_code) 
             VALUES (?, ?, CURDATE(), ?, ?, ?)",
            [$studentId, $qrCode['class_id'], $currentTime, $status, $qrToken]
        );
        
        // Send success response
        $response = [
            'type' => 'attendance_success',
            'message' => 'Attendance marked successfully',
            'data' => [
                'student_name' => $student['name'],
                'class_name' => $qrCode['class_name'],
                'status' => $status,
                'time' => $currentTime,
                'date' => date('Y-m-d')
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $conn->send(json_encode($response));
        
        // Broadcast to teacher if connected
        $this->broadcastToTeachers([
            'type' => 'new_attendance',
            'data' => $response['data'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Log notification
        $this->db->execute(
            "INSERT INTO notifications (student_id, message, type, status) 
             VALUES (?, ?, 'websocket', 'sent')",
            [$studentId, "Attendance marked: {$status} for {$qrCode['class_name']}"]
        );
        
        echo "Attendance marked: {$student['name']} - {$status}\n";
    }

    private function handleGetAttendance($conn, $data) {
        if (!isset($data['student_id'])) {
            $this->sendError($conn, 'Missing student ID');
            return;
        }

        $studentId = $data['student_id'];
        $limit = isset($data['limit']) ? (int)$data['limit'] : 10;
        
        $attendance = $this->db->fetchAll(
            "SELECT a.*, c.class_name 
             FROM attendance a 
             JOIN classes c ON a.class_id = c.id 
             WHERE a.student_id = ? 
             ORDER BY a.date DESC, a.time_in DESC 
             LIMIT ?",
            [$studentId, $limit]
        );
        
        $conn->send(json_encode([
            'type' => 'attendance_data',
            'data' => $attendance,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    private function handlePing($conn) {
        $conn->send(json_encode([
            'type' => 'pong',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    private function sendError($conn, $message) {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    private function broadcastToTeachers($message) {
        foreach ($this->userConnections as $key => $conn) {
            if (strpos($key, '_teacher') !== false) {
                $conn->send(json_encode($message));
            }
        }
    }

    public function broadcastToAll($message) {
        foreach ($this->clients as $client) {
            $client->send(json_encode($message));
        }
    }

    public function sendToUser($userId, $userType, $message) {
        $key = $userId . '_' . $userType;
        if (isset($this->userConnections[$key])) {
            $this->userConnections[$key]->send(json_encode($message));
            return true;
        }
        return false;
    }
}

// WebSocket server configuration
function getWebSocketConfig() {
    return [
        'host' => '0.0.0.0',
        'port' => 8080,
        'component' => new WebSocketConfig()
    ];
}
?>
