<?php
/**
 * QR Code Generator and Validator
 * Student Attendance System
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Logo\LogoInterface;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

class QRGenerator {
    private $db;
    private $expiryMinutes;
    
    public function __construct() {
        $this->db = getDB();
        $this->expiryMinutes = (int)$this->db->getSetting('qr_expiry_minutes', 15);
    }
    
    public function generateQRCode($classId, $teacherId) {
        try {
            // Deactivate previous QR codes for this class
            $this->db->execute(
                "UPDATE qr_codes SET is_active = 0 WHERE class_id = ?",
                [$classId]
            );
            
            // Generate unique token
            $token = $this->generateToken($classId, $teacherId);
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->expiryMinutes} minutes"));
            
            // Store QR code in database
            $this->db->execute(
                "INSERT INTO qr_codes (class_id, qr_token, expires_at, created_by) VALUES (?, ?, ?, ?)",
                [$classId, $token, $expiresAt, $teacherId]
            );
            
            $qrId = $this->db->lastInsertId();
            
            // Get class information
            $class = $this->db->fetch(
                "SELECT c.*, t.name as teacher_name 
                 FROM classes c 
                 JOIN teachers t ON c.teacher_id = t.id 
                 WHERE c.id = ?",
                [$classId]
            );
            
            // Create QR code data
            $qrData = json_encode([
                'token' => $token,
                'class_id' => $classId,
                'class_name' => $class['class_name'],
                'teacher' => $class['teacher_name'],
                'expires_at' => $expiresAt,
                'timestamp' => time()
            ]);
            
            // Generate QR code image
            $result = Builder::create()
                ->writer(new PngWriter())
                ->writerOptions([])
                ->data($qrData)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(300)
                ->margin(10)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();
            
            // Save QR code image
            $filename = "qr_class_{$classId}_{$qrId}.png";
            $filepath = __DIR__ . "/../assets/images/{$filename}";
            $result->saveToFile($filepath);
            
            return [
                'success' => true,
                'data' => [
                    'qr_id' => $qrId,
                    'token' => $token,
                    'class_id' => $classId,
                    'class_name' => $class['class_name'],
                    'teacher_name' => $class['teacher_name'],
                    'expires_at' => $expiresAt,
                    'image_path' => "/student-attendance-system/assets/images/{$filename}",
                    'qr_data' => $qrData,
                    'expiry_minutes' => $this->expiryMinutes
                ]
            ];
            
        } catch (Exception $e) {
            error_log("QR generation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate QR code'
            ];
        }
    }
    
    public function validateQRCode($token) {
        try {
            $qrCode = $this->db->fetch(
                "SELECT qc.*, c.class_name, t.name as teacher_name 
                 FROM qr_codes qc 
                 JOIN classes c ON qc.class_id = c.id 
                 JOIN teachers t ON c.teacher_id = t.id 
                 WHERE qc.qr_token = ? AND qc.is_active = 1",
                [$token]
            );
            
            if (!$qrCode) {
                return [
                    'success' => false,
                    'message' => 'Invalid QR code'
                ];
            }
            
            // Check if expired
            if (strtotime($qrCode['expires_at']) < time()) {
                // Deactivate expired QR code
                $this->db->execute(
                    "UPDATE qr_codes SET is_active = 0 WHERE id = ?",
                    [$qrCode['id']]
                );
                
                return [
                    'success' => false,
                    'message' => 'QR code has expired'
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'qr_id' => $qrCode['id'],
                    'class_id' => $qrCode['class_id'],
                    'class_name' => $qrCode['class_name'],
                    'teacher_name' => $qrCode['teacher_name'],
                    'expires_at' => $qrCode['expires_at'],
                    'remaining_minutes' => round((strtotime($qrCode['expires_at']) - time()) / 60)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("QR validation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'QR code validation failed'
            ];
        }
    }
    
    public function getActiveQRCodes($teacherId = null) {
        try {
            $sql = "SELECT qc.*, c.class_name, t.name as teacher_name 
                    FROM qr_codes qc 
                    JOIN classes c ON qc.class_id = c.id 
                    JOIN teachers t ON c.teacher_id = t.id 
                    WHERE qc.is_active = 1 AND qc.expires_at > NOW()";
            
            $params = [];
            
            if ($teacherId) {
                $sql .= " AND qc.created_by = ?";
                $params[] = $teacherId;
            }
            
            $sql .= " ORDER BY qc.created_at DESC";
            
            $qrCodes = $this->db->fetchAll($sql, $params);
            
            // Add remaining time for each QR code
            foreach ($qrCodes as &$qr) {
                $qr['remaining_minutes'] = round((strtotime($qr['expires_at']) - time()) / 60);
                $qr['image_path'] = "/student-attendance-system/assets/images/qr_class_{$qr['class_id']}_{$qr['id']}.png";
            }
            
            return [
                'success' => true,
                'data' => $qrCodes
            ];
            
        } catch (Exception $e) {
            error_log("Get QR codes error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve QR codes'
            ];
        }
    }
    
    public function deactivateQRCode($qrId, $teacherId = null) {
        try {
            $sql = "UPDATE qr_codes SET is_active = 0 WHERE id = ?";
            $params = [$qrId];
            
            if ($teacherId) {
                $sql .= " AND created_by = ?";
                $params[] = $teacherId;
            }
            
            $affected = $this->db->execute($sql, $params);
            
            if ($affected > 0) {
                return [
                    'success' => true,
                    'message' => 'QR code deactivated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'QR code not found or access denied'
                ];
            }
            
        } catch (Exception $e) {
            error_log("QR deactivation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to deactivate QR code'
            ];
        }
    }
    
    public function cleanupExpiredQRCodes() {
        try {
            // Deactivate expired QR codes
            $this->db->execute(
                "UPDATE qr_codes SET is_active = 0 WHERE expires_at < NOW() AND is_active = 1"
            );
            
            // Delete old QR code images (older than 24 hours)
            $oldQRs = $this->db->fetchAll(
                "SELECT id, class_id FROM qr_codes WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            
            foreach ($oldQRs as $qr) {
                $filename = "qr_class_{$qr['class_id']}_{$qr['id']}.png";
                $filepath = __DIR__ . "/../assets/images/{$filename}";
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
            
            // Delete old QR code records (older than 7 days)
            $this->db->execute(
                "DELETE FROM qr_codes WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            
            return [
                'success' => true,
                'message' => 'Cleanup completed successfully'
            ];
            
        } catch (Exception $e) {
            error_log("QR cleanup error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Cleanup failed'
            ];
        }
    }
    
    private function generateToken($classId, $teacherId) {
        $data = [
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(16))
        ];
        
        return hash('sha256', json_encode($data));
    }
    
    public function getQRCodeStats($teacherId = null) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_generated,
                        COUNT(CASE WHEN is_active = 1 AND expires_at > NOW() THEN 1 END) as active_count,
                        COUNT(CASE WHEN expires_at < NOW() THEN 1 END) as expired_count
                    FROM qr_codes qc";
            
            $params = [];
            
            if ($teacherId) {
                $sql .= " WHERE qc.created_by = ?";
                $params[] = $teacherId;
            }
            
            $stats = $this->db->fetch($sql, $params);
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            error_log("QR stats error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get QR code statistics'
            ];
        }
    }
    
    public function generateBulkQRCodes($classes, $teacherId) {
        try {
            $results = [];
            
            foreach ($classes as $classId) {
                $result = $this->generateQRCode($classId, $teacherId);
                $results[] = [
                    'class_id' => $classId,
                    'result' => $result
                ];
            }
            
            return [
                'success' => true,
                'data' => $results
            ];
            
        } catch (Exception $e) {
            error_log("Bulk QR generation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Bulk QR generation failed'
            ];
        }
    }
}

// Global QR generator instance
function getQRGenerator() {
    static $qrGenerator = null;
    if ($qrGenerator === null) {
        $qrGenerator = new QRGenerator();
    }
    return $qrGenerator;
}

// Helper functions
function generateQRCode($classId, $teacherId) {
    return getQRGenerator()->generateQRCode($classId, $teacherId);
}

function validateQRCode($token) {
    return getQRGenerator()->validateQRCode($token);
}

function getActiveQRCodes($teacherId = null) {
    return getQRGenerator()->getActiveQRCodes($teacherId);
}

function deactivateQRCode($qrId, $teacherId = null) {
    return getQRGenerator()->deactivateQRCode($qrId, $teacherId);
}
?>
