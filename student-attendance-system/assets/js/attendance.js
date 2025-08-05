/**
 * Attendance Management JavaScript
 * Student Attendance System
 */

class AttendanceManager {
    constructor() {
        this.currentUser = null;
        this.attendanceData = [];
        this.qrTimer = null;
        this.refreshInterval = null;
        
        this.init();
    }
    
    init() {
        this.loadUserData();
        this.setupEventListeners();
        this.initializeComponents();
    }
    
    loadUserData() {
        // Try to get user data from meta tags or data attributes
        const userElement = document.querySelector('[data-user-id]');
        if (userElement) {
            this.currentUser = {
                id: userElement.getAttribute('data-user-id'),
                type: userElement.getAttribute('data-user-type'),
                name: userElement.getAttribute('data-user-name')
            };
        }
    }
    
    setupEventListeners() {
        // QR Code generation
        const generateQRBtn = document.getElementById('generate-qr-btn');
        if (generateQRBtn) {
            generateQRBtn.addEventListener('click', () => this.generateQRCode());
        }
        
        // Refresh attendance data
        const refreshBtn = document.getElementById('refresh-attendance-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refreshAttendanceData());
        }
        
        // Export attendance
        const exportBtn = document.getElementById('export-attendance-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportAttendance());
        }
        
        // Class selection change
        const classSelect = document.getElementById('class-select');
        if (classSelect) {
            classSelect.addEventListener('change', (e) => this.onClassChange(e.target.value));
        }
        
        // Date range selection
        const dateFrom = document.getElementById('date-from');
        const dateTo = document.getElementById('date-to');
        if (dateFrom && dateTo) {
            dateFrom.addEventListener('change', () => this.onDateRangeChange());
            dateTo.addEventListener('change', () => this.onDateRangeChange());
        }
        
        // Auto-refresh toggle
        const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => this.toggleAutoRefresh(e.target.checked));
        }
        
        // Manual attendance marking
        const markAttendanceBtn = document.getElementById('mark-attendance-btn');
        if (markAttendanceBtn) {
            markAttendanceBtn.addEventListener('click', () => this.showManualAttendanceModal());
        }
    }
    
    initializeComponents() {
        // Initialize data tables if present
        this.initializeDataTables();
        
        // Load initial data
        this.loadInitialData();
        
        // Start QR timer if QR code is displayed
        this.startQRTimer();
        
        // Setup WebSocket event listeners
        this.setupWebSocketListeners();
    }
    
    initializeDataTables() {
        // Initialize attendance table with DataTables if available
        const attendanceTable = document.getElementById('attendance-table');
        if (attendanceTable && typeof $ !== 'undefined' && $.fn.DataTable) {
            $(attendanceTable).DataTable({
                responsive: true,
                pageLength: 25,
                order: [[4, 'desc']], // Sort by date descending
                columnDefs: [
                    { targets: [3], orderable: false } // Status column
                ]
            });
        }
    }
    
    async loadInitialData() {
        try {
            // Load attendance statistics
            await this.loadAttendanceStats();
            
            // Load recent attendance
            await this.loadRecentAttendance();
            
            // Load active QR codes for teachers
            if (this.currentUser?.type === 'teacher') {
                await this.loadActiveQRCodes();
            }
            
        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showNotification('Error loading data', 'danger');
        }
    }
    
    async loadAttendanceStats() {
        try {
            const endpoint = this.currentUser?.type === 'student' 
                ? `/student-attendance-system/api/attendance.php?action=student_stats&student_id=${this.currentUser.id}`
                : `/student-attendance-system/api/attendance.php?action=class_stats`;
            
            const response = await fetch(endpoint);
            const result = await response.json();
            
            if (result.success) {
                this.updateStatsDisplay(result.data);
            }
        } catch (error) {
            console.error('Error loading attendance stats:', error);
        }
    }
    
    async loadRecentAttendance() {
        try {
            const endpoint = this.currentUser?.type === 'student'
                ? `/student-attendance-system/api/attendance.php?action=student_attendance&student_id=${this.currentUser.id}&limit=10`
                : `/student-attendance-system/api/attendance.php?action=recent_attendance&limit=20`;
            
            const response = await fetch(endpoint);
            const result = await response.json();
            
            if (result.success) {
                this.attendanceData = result.data.attendance || result.data;
                this.updateAttendanceDisplay();
            }
        } catch (error) {
            console.error('Error loading recent attendance:', error);
        }
    }
    
    async loadActiveQRCodes() {
        try {
            const response = await fetch(`/student-attendance-system/api/qr-codes.php?action=active&teacher_id=${this.currentUser.id}`);
            const result = await response.json();
            
            if (result.success) {
                this.updateQRCodesDisplay(result.data);
            }
        } catch (error) {
            console.error('Error loading QR codes:', error);
        }
    }
    
    async generateQRCode() {
        try {
            const classId = document.getElementById('class-select')?.value;
            if (!classId) {
                this.showNotification('Please select a class', 'warning');
                return;
            }
            
            this.showLoading('Generating QR Code...');
            
            const response = await fetch('/student-attendance-system/api/qr-codes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'generate',
                    class_id: classId,
                    teacher_id: this.currentUser.id
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.displayQRCode(result.data);
                this.showNotification('QR Code generated successfully!', 'success');
                this.startQRTimer(result.data.expiry_minutes);
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('Error generating QR code:', error);
            this.showNotification(`Error: ${error.message}`, 'danger');
        } finally {
            this.hideLoading();
        }
    }
    
    displayQRCode(qrData) {
        const qrContainer = document.getElementById('qr-display-container');
        if (!qrContainer) return;
        
        qrContainer.innerHTML = `
            <div class="qr-code-display">
                <div class="qr-header">
                    <h4>${qrData.class_name}</h4>
                    <p>Teacher: ${qrData.teacher_name}</p>
                </div>
                
                <div class="qr-image">
                    <img src="${qrData.image_path}" alt="QR Code" class="img-fluid">
                </div>
                
                <div class="qr-info">
                    <div class="qr-timer" id="qr-timer">
                        <span id="timer-display">Loading...</span>
                    </div>
                    <p class="text-muted">QR Code expires in <span id="expiry-time">${qrData.expiry_minutes}</span> minutes</p>
                    <small class="text-muted">Students can scan this code to mark attendance</small>
                </div>
                
                <div class="qr-actions mt-3">
                    <button class="btn btn-warning btn-sm" onclick="attendanceManager.deactivateQRCode('${qrData.qr_id}')">
                        Deactivate QR Code
                    </button>
                    <button class="btn btn-info btn-sm" onclick="attendanceManager.refreshQRCode()">
                        Generate New QR
                    </button>
                </div>
            </div>
        `;
        
        // Start countdown timer
        this.startQRTimer(qrData.expiry_minutes);
    }
    
    startQRTimer(expiryMinutes = 15) {
        const timerDisplay = document.getElementById('timer-display');
        if (!timerDisplay) return;
        
        // Clear existing timer
        if (this.qrTimer) {
            clearInterval(this.qrTimer);
        }
        
        const expiryTime = new Date().getTime() + (expiryMinutes * 60 * 1000);
        
        this.qrTimer = setInterval(() => {
            const now = new Date().getTime();
            const timeLeft = expiryTime - now;
            
            if (timeLeft <= 0) {
                timerDisplay.innerHTML = '<span class="text-danger">EXPIRED</span>';
                clearInterval(this.qrTimer);
                this.onQRCodeExpired();
                return;
            }
            
            const minutes = Math.floor(timeLeft / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            const color = minutes < 2 ? 'text-danger' : minutes < 5 ? 'text-warning' : 'text-success';
            timerDisplay.innerHTML = `<span class="${color}">${minutes}:${seconds.toString().padStart(2, '0')}</span>`;
            
        }, 1000);
    }
    
    onQRCodeExpired() {
        this.showNotification('QR Code has expired', 'warning');
        
        // Optionally auto-generate new QR code
        const autoRenew = document.getElementById('auto-renew-qr')?.checked;
        if (autoRenew) {
            setTimeout(() => this.generateQRCode(), 2000);
        }
    }
    
    async deactivateQRCode(qrId) {
        try {
            const response = await fetch('/student-attendance-system/api/qr-codes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'deactivate',
                    qr_id: qrId,
                    teacher_id: this.currentUser.id
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('QR Code deactivated', 'info');
                document.getElementById('qr-display-container').innerHTML = 
                    '<div class="alert alert-info">QR Code has been deactivated</div>';
                
                if (this.qrTimer) {
                    clearInterval(this.qrTimer);
                }
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('Error deactivating QR code:', error);
            this.showNotification(`Error: ${error.message}`, 'danger');
        }
    }
    
    refreshQRCode() {
        this.generateQRCode();
    }
    
    async refreshAttendanceData() {
        this.showLoading('Refreshing data...');
        
        try {
            await this.loadAttendanceStats();
            await this.loadRecentAttendance();
            this.showNotification('Data refreshed successfully', 'success');
        } catch (error) {
            console.error('Error refreshing data:', error);
            this.showNotification('Error refreshing data', 'danger');
        } finally {
            this.hideLoading();
        }
    }
    
    updateStatsDisplay(stats) {
        // Update stats cards
        const presentCount = document.getElementById('present-count');
        const lateCount = document.getElementById('late-count');
        const absentCount = document.getElementById('absent-count');
        const totalCount = document.getElementById('total-count');
        const attendanceRate = document.getElementById('attendance-rate');
        
        if (presentCount) presentCount.textContent = stats.stats?.present_count || stats.present_days || 0;
        if (lateCount) lateCount.textContent = stats.stats?.late_count || stats.late_days || 0;
        if (absentCount) absentCount.textContent = stats.stats?.absent_count || stats.absent_days || 0;
        if (totalCount) totalCount.textContent = stats.stats?.total_students || stats.total_days || 0;
        if (attendanceRate) attendanceRate.textContent = `${stats.stats?.attendance_rate || stats.attendance_percentage || 0}%`;
    }
    
    updateAttendanceDisplay() {
        const attendanceList = document.getElementById('attendance-list');
        if (!attendanceList || !this.attendanceData.length) return;
        
        let html = '';
        this.attendanceData.forEach(record => {
            html += `
                <tr>
                    <td>${record.student_name || record.name || 'N/A'}</td>
                    <td>${record.class_name || 'N/A'}</td>
                    <td>${record.time_in || 'N/A'}</td>
                    <td><span class="status-badge status-${record.status}">${record.status}</span></td>
                    <td>${record.date}</td>
                    ${this.currentUser?.type === 'teacher' ? `
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="attendanceManager.editAttendance('${record.id}')">
                                Edit
                            </button>
                        </td>
                    ` : ''}
                </tr>
            `;
        });
        
        attendanceList.innerHTML = html;
    }
    
    updateQRCodesDisplay(qrCodes) {
        const qrList = document.getElementById('active-qr-list');
        if (!qrList) return;
        
        if (!qrCodes.length) {
            qrList.innerHTML = '<div class="alert alert-info">No active QR codes</div>';
            return;
        }
        
        let html = '<div class="row">';
        qrCodes.forEach(qr => {
            html += `
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">${qr.class_name}</h6>
                            <p class="card-text">
                                <small class="text-muted">
                                    Expires in ${qr.remaining_minutes} minutes
                                </small>
                            </p>
                            <button class="btn btn-sm btn-danger" onclick="attendanceManager.deactivateQRCode('${qr.id}')">
                                Deactivate
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        qrList.innerHTML = html;
    }
    
    setupWebSocketListeners() {
        if (window.attendanceWS) {
            window.attendanceWS.on('attendance_marked', (data) => {
                this.onAttendanceMarked(data);
            });
            
            window.attendanceWS.on('new_attendance', (data) => {
                this.onNewAttendance(data);
            });
        }
    }
    
    onAttendanceMarked(data) {
        // Update UI when attendance is marked via WebSocket
        this.showNotification(`Attendance marked: ${data.data.status}`, 'success');
        this.refreshAttendanceData();
    }
    
    onNewAttendance(data) {
        // Handle new attendance notifications for teachers
        if (this.currentUser?.type === 'teacher') {
            this.addAttendanceToList(data.data);
            this.updateStatsAfterNewAttendance(data.data);
        }
    }
    
    addAttendanceToList(attendanceData) {
        const attendanceList = document.getElementById('attendance-list');
        if (!attendanceList) return;
        
        const row = document.createElement('tr');
        row.className = 'fade-in';
        row.innerHTML = `
            <td>${attendanceData.student_name}</td>
            <td>${attendanceData.class_name}</td>
            <td>${attendanceData.time}</td>
            <td><span class="status-badge status-${attendanceData.status}">${attendanceData.status}</span></td>
            <td>${attendanceData.date}</td>
            ${this.currentUser?.type === 'teacher' ? `
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="attendanceManager.editAttendance('${attendanceData.attendance_id}')">
                        Edit
                    </button>
                </td>
            ` : ''}
        `;
        
        // Insert at the beginning
        if (attendanceList.firstChild) {
            attendanceList.insertBefore(row, attendanceList.firstChild);
        } else {
            attendanceList.appendChild(row);
        }
    }
    
    updateStatsAfterNewAttendance(attendanceData) {
        // Update stats counters
        const totalCount = document.getElementById('total-count');
        if (totalCount) {
            totalCount.textContent = parseInt(totalCount.textContent) + 1;
        }
        
        const statusCount = document.getElementById(`${attendanceData.status}-count`);
        if (statusCount) {
            statusCount.textContent = parseInt(statusCount.textContent) + 1;
        }
        
        // Recalculate attendance rate
        this.recalculateAttendanceRate();
    }
    
    recalculateAttendanceRate() {
        const presentCount = parseInt(document.getElementById('present-count')?.textContent || 0);
        const lateCount = parseInt(document.getElementById('late-count')?.textContent || 0);
        const totalCount = parseInt(document.getElementById('total-count')?.textContent || 0);
        
        if (totalCount > 0) {
            const rate = Math.round(((presentCount + lateCount) / totalCount) * 100);
            const rateElement = document.getElementById('attendance-rate');
            if (rateElement) {
                rateElement.textContent = `${rate}%`;
            }
        }
    }
    
    toggleAutoRefresh(enabled) {
        if (enabled) {
            this.refreshInterval = setInterval(() => {
                this.refreshAttendanceData();
            }, 30000); // Refresh every 30 seconds
            this.showNotification('Auto-refresh enabled', 'info');
        } else {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
            this.showNotification('Auto-refresh disabled', 'info');
        }
    }
    
    onClassChange(classId) {
        // Reload data for selected class
        this.loadAttendanceStats();
        this.loadRecentAttendance();
    }
    
    onDateRangeChange() {
        // Reload data for selected date range
        this.loadRecentAttendance();
    }
    
    async exportAttendance() {
        try {
            const classId = document.getElementById('class-select')?.value;
            const dateFrom = document.getElementById('date-from')?.value;
            const dateTo = document.getElementById('date-to')?.value;
            
            const params = new URLSearchParams({
                action: 'export',
                format: 'csv'
            });
            
            if (classId) params.append('class_id', classId);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            const url = `/student-attendance-system/api/attendance.php?${params.toString()}`;
            
            // Create download link
            const link = document.createElement('a');
            link.href = url;
            link.download = `attendance_report_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            this.showNotification('Attendance report downloaded', 'success');
            
        } catch (error) {
            console.error('Error exporting attendance:', error);
            this.showNotification('Error exporting attendance', 'danger');
        }
    }
    
    showLoading(message = 'Loading...') {
        const loadingElement = document.getElementById('loading-indicator');
        if (loadingElement) {
            loadingElement.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="spinner"></div>
                    <span class="ml-2">${message}</span>
                </div>
            `;
            loadingElement.classList.remove('d-none');
        }
    }
    
    hideLoading() {
        const loadingElement = document.getElementById('loading-indicator');
        if (loadingElement) {
            loadingElement.classList.add('d-none');
        }
    }
    
    showNotification(message, type = 'info', duration = 5000) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show notification-toast`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        `;
        
        // Add to notifications container
        let container = document.getElementById('notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.className = 'notifications-container position-fixed';
            container.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            document.body.appendChild(container);
        }
        
        container.appendChild(notification);
        
        // Auto remove
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }
    
    // Cleanup method
    destroy() {
        if (this.qrTimer) {
            clearInterval(this.qrTimer);
        }
        
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Global attendance manager instance
let attendanceManager = null;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    attendanceManager = new AttendanceManager();
    
    // Make available globally
    window.attendanceManager = attendanceManager;
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (attendanceManager) {
        attendanceManager.destroy();
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AttendanceManager;
}
