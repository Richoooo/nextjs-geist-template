<?php
/**
 * Teacher Dashboard
 * Student Attendance System
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr_generator.php';
require_once __DIR__ . '/../includes/attendance.php';

// Require teacher login
requireLogin('teacher');

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Get teacher's classes
$classes = $db->fetchAll(
    "SELECT * FROM classes WHERE teacher_id = ? AND is_active = 1 ORDER BY class_name",
    [$user['id']]
);

// Get today's attendance summary
$todayStats = [];
if (!empty($classes)) {
    $classIds = array_column($classes, 'id');
    $placeholders = str_repeat('?,', count($classIds) - 1) . '?';
    
    $todayStats = $db->fetch(
        "SELECT 
            COUNT(*) as total_marked,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count
         FROM attendance 
         WHERE class_id IN ($placeholders) AND date = CURDATE()",
        $classIds
    ) ?: ['total_marked' => 0, 'present_count' => 0, 'late_count' => 0, 'absent_count' => 0];
}

// Get recent attendance
$recentAttendance = [];
if (!empty($classes)) {
    $recentAttendance = $db->fetchAll(
        "SELECT a.*, s.name as student_name, s.nis, c.class_name 
         FROM attendance a 
         JOIN students s ON a.student_id = s.id 
         JOIN classes c ON a.class_id = c.id 
         WHERE a.class_id IN ($placeholders) 
         ORDER BY a.created_at DESC 
         LIMIT 20",
        $classIds
    );
}

// Get active QR codes
$qrGenerator = getQRGenerator();
$activeQRs = $qrGenerator->getActiveQRCodes($user['id']);
$activeQRCodes = $activeQRs['success'] ? $activeQRs['data'] : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo htmlspecialchars($user['name']); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../assets/css/custom.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .qr-code-card {
            border: 2px dashed #dee2e6;
            transition: all 0.3s ease;
        }
        .qr-code-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        .qr-code-active {
            border-color: var(--success-color);
            background-color: #f8fff9;
        }
    </style>
</head>
<body class="dashboard-container">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">👨‍🏫 Teacher Portal</a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="dashboard.php">📊 Admin Dashboard</a>
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
            <!-- Teacher Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">👨‍🏫 Teacher Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Name:</strong><br>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Email:</strong><br>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Role:</strong><br>
                                    <?php echo ucfirst($user['role']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Today's Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-number text-info" id="total-count">
                                <?php echo $todayStats['total_marked']; ?>
                            </div>
                            <div class="stats-label">Total Marked</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card stats-present">
                        <div class="card-body text-center">
                            <div class="stats-number" id="present-count">
                                <?php echo $todayStats['present_count']; ?>
                            </div>
                            <div class="stats-label">Present</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card stats-late">
                        <div class="card-body text-center">
                            <div class="stats-number" id="late-count">
                                <?php echo $todayStats['late_count']; ?>
                            </div>
                            <div class="stats-label">Late</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card stats-absent">
                        <div class="card-body text-center">
                            <div class="stats-number" id="absent-count">
                                <?php echo $todayStats['absent_count']; ?>
                            </div>
                            <div class="stats-label">Absent</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- QR Code Generation -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">📱 Generate QR Code</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($classes)): ?>
                                <div class="alert alert-warning">
                                    No classes assigned. Please contact administrator.
                                </div>
                            <?php else: ?>
                                <div class="form-group mb-3">
                                    <label for="class-select" class="form-label">Select Class</label>
                                    <select class="form-control" id="class-select">
                                        <option value="">Choose a class...</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="auto-renew-qr">
                                    <label class="form-check-label" for="auto-renew-qr">
                                        Auto-renew QR code when expired
                                    </label>
                                </div>
                                
                                <button class="btn btn-primary" id="generate-qr-btn">
                                    🎯 Generate QR Code
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">📋 Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" id="refresh-attendance-btn">
                                    🔄 Refresh Attendance Data
                                </button>
                                <button class="btn btn-outline-success" id="export-attendance-btn">
                                    📊 Export Today's Attendance
                                </button>
                                <a href="attendance-report.php" class="btn btn-outline-info">
                                    📈 View Detailed Reports
                                </a>
                            </div>
                            
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="auto-refresh-toggle">
                                <label class="form-check-label" for="auto-refresh-toggle">
                                    Auto-refresh data (30s)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- QR Code Display -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card qr-code-card" id="qr-display-container">
                        <div class="card-body text-center">
                            <h5 class="text-muted">📱 QR Code will appear here</h5>
                            <p class="text-muted">Select a class and click "Generate QR Code" to start</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active QR Codes -->
            <?php if (!empty($activeQRCodes)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">⚡ Active QR Codes</h5>
                        </div>
                        <div class="card-body">
                            <div id="active-qr-list">
                                <div class="row">
                                    <?php foreach ($activeQRCodes as $qr): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card qr-code-active">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($qr['class_name']); ?></h6>
                                                    <p class="card-text">
                                                        <small class="text-success">
                                                            ⏰ Expires in <?php echo $qr['remaining_minutes']; ?> minutes
                                                        </small>
                                                    </p>
                                                    <button class="btn btn-sm btn-danger" onclick="attendanceManager.deactivateQRCode('<?php echo $qr['id']; ?>')">
                                                        ❌ Deactivate
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Attendance -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">📋 Recent Attendance</h5>
                            <div>
                                <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                                    🔄 Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentAttendance)): ?>
                                <div class="alert alert-info">
                                    No attendance records found for today.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="attendance-table">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>NIS</th>
                                                <th>Class</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody id="attendance-list">
                                            <?php foreach ($recentAttendance as $record): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['nis']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['class_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['time_in'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo $record['status']; ?>">
                                                            <?php echo ucfirst($record['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($record['date']); ?></td>
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
        data-user-type="teacher"
        data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
        style="display: none;"
    ></div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="../assets/js/websocket-client.js"></script>
    <script src="../assets/js/attendance.js"></script>
    
    <script>
        // Global variables
        window.teacherId = <?php echo $user['id']; ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Teacher Dashboard loaded for:', '<?php echo htmlspecialchars($user['name']); ?>');
            
            // Set up WebSocket event listeners for real-time updates
            if (window.attendanceWS) {
                window.attendanceWS.on('new_attendance', function(data) {
                    console.log('New attendance received:', data);
                    
                    // Show notification
                    if (window.attendanceManager) {
                        const statusColor = data.data.status === 'late' ? 'warning' : 'success';
                        window.attendanceManager.showNotification(
                            `${data.data.student_name} marked attendance: ${data.data.status}`,
                            statusColor
                        );
                    }
                    
                    // Update stats
                    updateStatsAfterNewAttendance(data.data);
                    
                    // Add to attendance list
                    addAttendanceToList(data.data);
                });
            }
        });
        
        function updateStatsAfterNewAttendance(attendanceData) {
            // Update total count
            const totalCount = document.getElementById('total-count');
            if (totalCount) {
                totalCount.textContent = parseInt(totalCount.textContent) + 1;
            }
            
            // Update status-specific count
            const statusCount = document.getElementById(`${attendanceData.status}-count`);
            if (statusCount) {
                statusCount.textContent = parseInt(statusCount.textContent) + 1;
            }
        }
        
        function addAttendanceToList(attendanceData) {
            const attendanceList = document.getElementById('attendance-list');
            if (!attendanceList) return;
            
            const row = document.createElement('tr');
            row.className = 'fade-in';
            row.innerHTML = `
                <td>${attendanceData.student_name}</td>
                <td>${attendanceData.student_nis || 'N/A'}</td>
                <td>${attendanceData.class_name}</td>
                <td>${attendanceData.time}</td>
                <td><span class="status-badge status-${attendanceData.status}">${attendanceData.status}</span></td>
                <td>${attendanceData.date}</td>
            `;
            
            // Insert at the beginning
            if (attendanceList.firstChild) {
                attendanceList.insertBefore(row, attendanceList.firstChild);
            } else {
                attendanceList.appendChild(row);
            }
            
            // Remove old rows if too many (keep last 20)
            const maxRows = 20;
            while (attendanceList.children.length > maxRows) {
                attendanceList.removeChild(attendanceList.lastChild);
            }
        }
        
        // Handle QR code generation
        document.getElementById('generate-qr-btn').addEventListener('click', function() {
            const classSelect = document.getElementById('class-select');
            if (!classSelect.value) {
                alert('Please select a class first');
                return;
            }
            
            if (window.attendanceManager) {
                window.attendanceManager.generateQRCode();
            }
        });
        
        // Handle auto-refresh toggle
        document.getElementById('auto-refresh-toggle').addEventListener('change', function(e) {
            if (window.attendanceManager) {
                window.attendanceManager.toggleAutoRefresh(e.target.checked);
            }
        });
        
        // Handle export button
        document.getElementById('export-attendance-btn').addEventListener('click', function() {
            if (window.attendanceManager) {
                window.attendanceManager.exportAttendance();
            }
        });
        
        // Handle refresh button
        document.getElementById('refresh-attendance-btn').addEventListener('click', function() {
            if (window.attendanceManager) {
                window.attendanceManager.refreshAttendanceData();
            }
        });
    </script>
</body>
</html>
