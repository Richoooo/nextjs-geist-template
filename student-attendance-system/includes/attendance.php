<?php
/**
 * Attendance Processing Functions
 * Student Attendance System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/qr_generator.php';

class AttendanceManager {
    private $db;
    private $lateThresholdMinutes;
    
    public function __construct() {
        $this->db = getDB();
        $this->lateThresholdMinutes = (int)$this->db->getSetting('late_threshold_minutes', 10);
    }
    
    public function markAttendance($studentId, $qrToken) {
        try {
            // Validate QR code
            $qrValidation = validateQRCode($qrToken);
            if (!$qrValidation['success']) {
                return $qrValidation;
            }
            
            $qrData = $qrValidation['data'];
            $classId = $qrData['class_id'];
            
            // Check if student exists and is active
            $student = $this->db->fetch(
                "SELECT * FROM students WHERE id = ? AND is_active = 1",
                [$studentId]
            );
            
            if (!$student) {
                return [
                    'success' => false,
                    'message' => 'Student not found or inactive'
                ];
            }
            
            // Check if attendance already marked today
            $existingAttendance = $this->db->fetch(
                "SELECT * FROM attendance 
                 WHERE student_id = ? AND class_id = ? AND date = CURDATE()",
                [$studentId, $classId]
            );
            
            if ($existingAttendance) {
                return [
                    'success' => false,
                    'message' => 'Attendance already marked for today',
                    'data' => [
                        'existing_attendance' => $existingAttendance,
                        'class_name' => $qrData['class_name']
                    ]
                ];
            }
            
            // Determine attendance status
            $currentTime = date('H:i:s');
            $status = $this->determineAttendanceStatus($currentTime, $classId);
            
            // Mark attendance
            $this->db->execute(
                "INSERT INTO attendance (student_id, class_id, date, time_in, status, qr_code) 
                 VALUES (?, ?, CURDATE(), ?, ?, ?)",
                [$studentId, $classId, $currentTime, $status, $qrToken]
            );
            
            $attendanceId = $this->db->lastInsertId();
            
            // Get complete attendance record
            $attendanceRecord = $this->db->fetch(
                "SELECT a.*, c.class_name, s.name as student_name, s.nis 
                 FROM attendance a 
                 JOIN classes c ON a.class_id = c.id 
                 JOIN students s ON a.student_id = s.id 
                 WHERE a.id = ?",
                [$attendanceId]
            );
            
            return [
                'success' => true,
                'message' => 'Attendance marked successfully',
                'data' => [
                    'attendance_id' => $attendanceId,
                    'student_name' => $student['name'],
                    'student_nis' => $student['nis'],
                    'class_name' => $qrData['class_name'],
                    'status' => $status,
                    'time_in' => $currentTime,
                    'date' => date('Y-m-d'),
                    'record' => $attendanceRecord
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Mark attendance error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to mark attendance'
            ];
        }
    }
    
    public function markTimeOut($attendanceId, $studentId) {
        try {
            $attendance = $this->db->fetch(
                "SELECT * FROM attendance WHERE id = ? AND student_id = ? AND date = CURDATE()",
                [$attendanceId, $studentId]
            );
            
            if (!$attendance) {
                return [
                    'success' => false,
                    'message' => 'Attendance record not found'
                ];
            }
            
            if ($attendance['time_out']) {
                return [
                    'success' => false,
                    'message' => 'Time out already marked'
                ];
            }
            
            $currentTime = date('H:i:s');
            
            $this->db->execute(
                "UPDATE attendance SET time_out = ?, updated_at = NOW() WHERE id = ?",
                [$currentTime, $attendanceId]
            );
            
            return [
                'success' => true,
                'message' => 'Time out marked successfully',
                'data' => [
                    'time_out' => $currentTime,
                    'attendance_id' => $attendanceId
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Mark time out error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to mark time out'
            ];
        }
    }
    
    public function getStudentAttendance($studentId, $limit = 20, $offset = 0) {
        try {
            $attendance = $this->db->fetchAll(
                "SELECT a.*, c.class_name, t.name as teacher_name 
                 FROM attendance a 
                 JOIN classes c ON a.class_id = c.id 
                 JOIN teachers t ON c.teacher_id = t.id 
                 WHERE a.student_id = ? 
                 ORDER BY a.date DESC, a.time_in DESC 
                 LIMIT ? OFFSET ?",
                [$studentId, $limit, $offset]
            );
            
            // Get attendance statistics
            $stats = $this->getStudentAttendanceStats($studentId);
            
            return [
                'success' => true,
                'data' => [
                    'attendance' => $attendance,
                    'stats' => $stats['data']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get student attendance error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve attendance'
            ];
        }
    }
    
    public function getClassAttendance($classId, $date = null, $limit = 50, $offset = 0) {
        try {
            $dateCondition = $date ? "AND a.date = ?" : "AND a.date = CURDATE()";
            $params = [$classId];
            if ($date) {
                $params[] = $date;
            }
            $params[] = $limit;
            $params[] = $offset;
            
            $attendance = $this->db->fetchAll(
                "SELECT a.*, s.name as student_name, s.nis, s.email 
                 FROM attendance a 
                 JOIN students s ON a.student_id = s.id 
                 WHERE a.class_id = ? {$dateCondition}
                 ORDER BY a.time_in ASC 
                 LIMIT ? OFFSET ?",
                $params
            );
            
            // Get class statistics
            $stats = $this->getClassAttendanceStats($classId, $date);
            
            return [
                'success' => true,
                'data' => [
                    'attendance' => $attendance,
                    'stats' => $stats['data']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get class attendance error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve class attendance'
            ];
        }
    }
    
    public function getStudentAttendanceStats($studentId, $startDate = null, $endDate = null) {
        try {
            $dateCondition = "";
            $params = [$studentId];
            
            if ($startDate && $endDate) {
                $dateCondition = "AND date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            } elseif ($startDate) {
                $dateCondition = "AND date >= ?";
                $params[] = $startDate;
            } elseif ($endDate) {
                $dateCondition = "AND date <= ?";
                $params[] = $endDate;
            }
            
            $stats = $this->db->fetch(
                "SELECT 
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_percentage
                 FROM attendance 
                 WHERE student_id = ? {$dateCondition}",
                $params
            );
            
            // Get recent attendance
            $recentAttendance = $this->db->fetchAll(
                "SELECT a.*, c.class_name 
                 FROM attendance a 
                 JOIN classes c ON a.class_id = c.id 
                 WHERE a.student_id = ? 
                 ORDER BY a.date DESC, a.time_in DESC 
                 LIMIT 5",
                [$studentId]
            );
            
            return [
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_attendance' => $recentAttendance
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get student stats error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve attendance statistics'
            ];
        }
    }
    
    public function getClassAttendanceStats($classId, $date = null) {
        try {
            $dateCondition = $date ? "AND date = ?" : "AND date = CURDATE()";
            $params = [$classId];
            if ($date) {
                $params[] = $date;
            }
            
            $stats = $this->db->fetch(
                "SELECT 
                    COUNT(*) as total_students,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                    ROUND((COUNT(CASE WHEN status IN ('present', 'late') THEN 1 END) / COUNT(*)) * 100, 2) as attendance_rate
                 FROM attendance 
                 WHERE class_id = ? {$dateCondition}",
                $params
            );
            
            // Get total enrolled students for this class
            $totalEnrolled = $this->db->fetch(
                "SELECT COUNT(*) as total FROM students WHERE class = (SELECT class_name FROM classes WHERE id = ?) AND is_active = 1",
                [$classId]
            );
            
            return [
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'total_enrolled' => $totalEnrolled['total'],
                    'not_marked' => $totalEnrolled['total'] - ($stats['total_students'] ?? 0)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get class stats error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve class statistics'
            ];
        }
    }
    
    public function generateAttendanceReport($classId = null, $studentId = null, $startDate = null, $endDate = null) {
        try {
            $conditions = [];
            $params = [];
            
            if ($classId) {
                $conditions[] = "a.class_id = ?";
                $params[] = $classId;
            }
            
            if ($studentId) {
                $conditions[] = "a.student_id = ?";
                $params[] = $studentId;
            }
            
            if ($startDate) {
                $conditions[] = "a.date >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $conditions[] = "a.date <= ?";
                $params[] = $endDate;
            }
            
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            $report = $this->db->fetchAll(
                "SELECT 
                    a.*,
                    s.name as student_name,
                    s.nis,
                    s.email as student_email,
                    c.class_name,
                    t.name as teacher_name
                 FROM attendance a
                 JOIN students s ON a.student_id = s.id
                 JOIN classes c ON a.class_id = c.id
                 JOIN teachers t ON c.teacher_id = t.id
                 {$whereClause}
                 ORDER BY a.date DESC, c.class_name, s.name",
                $params
            );
            
            // Generate summary statistics
            $summary = [
                'total_records' => count($report),
                'present_count' => 0,
                'late_count' => 0,
                'absent_count' => 0,
                'unique_students' => [],
                'unique_classes' => [],
                'date_range' => [
                    'start' => null,
                    'end' => null
                ]
            ];
            
            foreach ($report as $record) {
                $summary[$record['status'] . '_count']++;
                $summary['unique_students'][$record['student_id']] = $record['student_name'];
                $summary['unique_classes'][$record['class_id']] = $record['class_name'];
                
                if (!$summary['date_range']['start'] || $record['date'] < $summary['date_range']['start']) {
                    $summary['date_range']['start'] = $record['date'];
                }
                if (!$summary['date_range']['end'] || $record['date'] > $summary['date_range']['end']) {
                    $summary['date_range']['end'] = $record['date'];
                }
            }
            
            $summary['unique_students_count'] = count($summary['unique_students']);
            $summary['unique_classes_count'] = count($summary['unique_classes']);
            
            return [
                'success' => true,
                'data' => [
                    'report' => $report,
                    'summary' => $summary
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Generate report error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate attendance report'
            ];
        }
    }
    
    private function determineAttendanceStatus($currentTime, $classId) {
        // Get class schedule (you can make this more sophisticated)
        $classStartTime = '08:00:00'; // Default start time
        
        // You can extend this to get actual class schedule from database
        // $schedule = $this->db->fetch("SELECT start_time FROM class_schedules WHERE class_id = ? AND day_of_week = ?", [$classId, date('w')]);
        
        $currentTimestamp = strtotime($currentTime);
        $startTimestamp = strtotime($classStartTime);
        $lateThreshold = $startTimestamp + ($this->lateThresholdMinutes * 60);
        
        if ($currentTimestamp <= $startTimestamp) {
            return 'present';
        } elseif ($currentTimestamp <= $lateThreshold) {
            return 'late';
        } else {
            return 'late'; // Still allow marking as late, but you might want to make this configurable
        }
    }
    
    public function updateAttendanceStatus($attendanceId, $newStatus, $notes = null) {
        try {
            $validStatuses = ['present', 'late', 'absent'];
            if (!in_array($newStatus, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status'
                ];
            }
            
            $params = [$newStatus];
            $sql = "UPDATE attendance SET status = ?";
            
            if ($notes !== null) {
                $sql .= ", notes = ?";
                $params[] = $notes;
            }
            
            $sql .= ", updated_at = NOW() WHERE id = ?";
            $params[] = $attendanceId;
            
            $affected = $this->db->execute($sql, $params);
            
            if ($affected > 0) {
                return [
                    'success' => true,
                    'message' => 'Attendance status updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Attendance record not found'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Update attendance status error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update attendance status'
            ];
        }
    }
}

// Global attendance manager instance
function getAttendanceManager() {
    static $attendanceManager = null;
    if ($attendanceManager === null) {
        $attendanceManager = new AttendanceManager();
    }
    return $attendanceManager;
}

// Helper functions
function markAttendance($studentId, $qrToken) {
    return getAttendanceManager()->markAttendance($studentId, $qrToken);
}

function getStudentAttendance($studentId, $limit = 20, $offset = 0) {
    return getAttendanceManager()->getStudentAttendance($studentId, $limit, $offset);
}

function getClassAttendance($classId, $date = null, $limit = 50, $offset = 0) {
    return getAttendanceManager()->getClassAttendance($classId, $date, $limit, $offset);
}

function generateAttendanceReport($classId = null, $studentId = null, $startDate = null, $endDate = null) {
    return getAttendanceManager()->generateAttendanceReport($classId, $studentId, $startDate, $endDate);
}
?>
