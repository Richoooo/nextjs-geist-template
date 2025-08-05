<?php
/**
 * Student Dashboard
 * Student Attendance System
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';

// Require student login
requireLogin('student');

$auth = getAuth();
$user = $auth->getCurrentUser();
$attendanceManager = getAttendanceManager();

// Get student attendance data
$attendanceResult = $attendanceManager->getStudentAttendance($user['id'], 10);
$attendanceData = $attendanceResult['success'] ? $attendanceResult['data'] : ['attendance' => [], 'stats' => []];

// Get attendance stats
$stats = $attendanceData['stats']['stats'] ?? [];
$recentAttendance = $attendanceData['attendance'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo htmlspecialchars($user['name']); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../assets/css/custom.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .notifications-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        .notification-toast {
            margin-bottom: 10px;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="dashboard-container">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">📚 Student Portal</a>
            
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    Welcome, <?php echo htmlspecialchars($user['name']); ?>
                </span>
                <a class="nav-link text-white" href="../api/logout.php">🚪 Logout</a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- User Info Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">👨‍🎓 Student Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Name:</strong><br>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>NIS:</strong><br>
                                    <?php echo htmlspecialchars($user['nis']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Class:</strong><br>
                                    <?php echo htmlspecialchars($user['class']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Email:</strong><br>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card stats-present">
                        <div class="card-body text-center">
                            <div class="stats-number" id="present-count">
                                <?php echo $stats['present_days'] ?? 0; ?>
                            </div>
                            <div class="stats-label">Present Days</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card stats-late">
                        <div class="card-body text-center">
                            <div class="stats-number" id="late-count">
                                <?php echo $stats['late_days'] ?? 0; ?>
                            </div>
                            <div class="stats-label">Late Days</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card stats-absent">
                        <div class="card-body text-center">
                            <div class="stats-number" id="absent-count">
                                <?php echo $stats['absent_days'] ?? 0; ?>
                            </div>
                            <div class="stats-label">Absent Days</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-number text-info" id="attendance-rate">
                                <?php echo round($stats['attendance_percentage'] ?? 0, 1); ?>%
                            </div>
                            <div class="stats-label">Attendance Rate</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- QR Scanner Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">📱 Mark Attendance</h5>
                        </div>
                        <div class="card-body">
                            <div id="qr-scanner-container"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Attendance -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">📋 Recent Attendance</h5>
                            <button class="btn btn-sm btn-outline-primary" id="refresh-attendance-btn">
                                🔄 Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentAttendance)): ?>
                                <div class="alert alert-info">
                                    No attendance records found. Start by scanning a QR code!
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Class</th>
                                                <th>Date</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="attendance-list">
                                            <?php foreach ($recentAttendance as $record): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($record['class_name'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($record['date']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['time_in'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($record['time_out'] ?? '-'); ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo $record['status']; ?>">
                                                            <?php echo ucfirst($record['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Indicator -->
    <div id="loading-indicator" class="d-none position-fixed top-50 start-50 translate-middle">
        <div class="card">
            <div class="card-body text-center">
                <div class="spinner"></div>
                <div class="mt-2">Loading...</div>
            </div>
        </div>
    </div>
    
    <!-- Connection Status -->
    <div id="connection-status-container" class="position-fixed bottom-0 start-0 p-3">
        <small id="connection-status" class="badge bg-secondary">Connecting...</small>
    </div>
    
    <!-- Notifications Container -->
    <div id="notifications-container" class="notifications-container"></div>
    
    <!-- WebSocket and User Data -->
    <div 
        data-websocket="true" 
        data-ws-url="ws://localhost:8080"
        data-user-id="<?php echo $user['id']; ?>"
        data-user-type="student"
        data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
        style="display: none;"
    ></div>
    
    <!-- Meta tags for JavaScript -->
    <meta name="student-id" content="<?php echo $user['id']; ?>">
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="../assets/js/websocket-client.js"></script>
    <script src="../assets/js/qr-scanner.js"></script>
    <script src="../assets/js/attendance.js"></script>
    
    <script>
        // Global variables
        window.studentId = <?php echo $user['id']; ?>;
        
        // Initialize components when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Student Dashboard loaded for:', '<?php echo htmlspecialchars($user['name']); ?>');
            
            // Set up QR scanner event listeners
            if (window.qrScanner) {
                window.qrScanner.on('attendance_success', function(data) {
                    // Update attendance display
                    updateAttendanceDisplay(data.data);
                    
                    // Show success notification
                    if (window.attendanceManager) {
                        window.attendanceManager.showNotification(
                            `Attendance marked successfully: ${data.data.status}`, 
                            'success'
                        );
                    }
                });
                
                window.qrScanner.on('scan_error', function(error) {
                    console.error('QR Scanner error:', error);
                });
            }
            
            // Set up WebSocket event listeners
            if (window.attendanceWS) {
                window.attendanceWS.on('attendance_marked', function(data) {
                    console.log('Attendance marked via WebSocket:', data);
                    
                    // Refresh attendance data
                    if (window.attendanceManager) {
                        window.attendanceManager.refreshAttendanceData();
                    }
                });
            }
        });
        
        // Helper function to update attendance display
        function updateAttendanceDisplay(attendanceData) {
            // Update stats
            const presentCount = document.getElementById('present-count');
            const lateCount = document.getElementById('late-count');
            const totalCount = document.getElementById('total-count');
            
            if (attendanceData.status === 'present' && presentCount) {
                presentCount.textContent = parseInt(presentCount.textContent) + 1;
            } else if (attendanceData.status === 'late' && lateCount) {
                lateCount.textContent = parseInt(lateCount.textContent) + 1;
            }
            
            // Add to attendance list
            const attendanceList = document.getElementById('attendance-list');
            if (attendanceList) {
                const row = document.createElement('tr');
                row.className = 'fade-in';
                row.innerHTML = `
                    <td>${attendanceData.class_name}</td>
                    <td>${attendanceData.date}</td>
                    <td>${attendanceData.time}</td>
                    <td>-</td>
                    <td><span class="status-badge status-${attendanceData.status}">${attendanceData.status}</span></td>
                `;
                
                // Insert at the beginning
                if (attendanceList.firstChild) {
                    attendanceList.insertBefore(row, attendanceList.firstChild);
                } else {
                    attendanceList.appendChild(row);
                }
            }
            
            // Recalculate attendance rate
            recalculateAttendanceRate();
        }
        
        function recalculateAttendanceRate() {
            const presentCount = parseInt(document.getElementById('present-count')?.textContent || 0);
            const lateCount = parseInt(document.getElementById('late-count')?.textContent || 0);
            const absentCount = parseInt(document.getElementById('absent-count')?.textContent || 0);
            const total = presentCount + lateCount + absentCount;
            
            if (total > 0) {
                const rate = Math.round(((presentCount + lateCount) / total) * 100);
                const rateElement = document.getElementById('attendance-rate');
                if (rateElement) {
                    rateElement.textContent = `${rate}%`;
                }
            }
        }
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && window.attendanceManager) {
                // Refresh data when page becomes visible
                window.attendanceManager.refreshAttendanceData();
            }
        });
    </script>
</body>
</html>
