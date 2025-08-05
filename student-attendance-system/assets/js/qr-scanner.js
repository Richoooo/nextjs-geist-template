/**
 * QR Code Scanner for Student Attendance System
 * Uses html5-qrcode library for QR code scanning
 */

class QRScanner {
    constructor(containerId = 'qr-scanner-container') {
        this.containerId = containerId;
        this.scanner = null;
        this.isScanning = false;
        this.config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0,
            disableFlip: false,
            videoConstraints: {
                facingMode: "environment" // Use back camera
            }
        };
        this.callbacks = {};
        
        this.init();
    }
    
    init() {
        this.createScannerUI();
        this.setupEventListeners();
    }
    
    createScannerUI() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.error('QR Scanner container not found:', this.containerId);
            return;
        }
        
        container.innerHTML = `
            <div class="qr-scanner-wrapper">
                <div class="scanner-header">
                    <h3>Scan QR Code for Attendance</h3>
                    <p>Position the QR code within the frame</p>
                </div>
                
                <div class="scanner-controls mb-3">
                    <button id="start-scan-btn" class="btn btn-success">
                        📷 Start Scanner
                    </button>
                    <button id="stop-scan-btn" class="btn btn-danger d-none">
                        ⏹️ Stop Scanner
                    </button>
                    <button id="switch-camera-btn" class="btn btn-info d-none">
                        🔄 Switch Camera
                    </button>
                </div>
                
                <div id="qr-reader" class="qr-reader-container"></div>
                
                <div id="scan-result" class="scan-result d-none"></div>
                
                <div class="scanner-info mt-3">
                    <div class="alert alert-info">
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Allow camera access when prompted</li>
                            <li>Hold your device steady</li>
                            <li>Ensure good lighting</li>
                            <li>Position QR code within the frame</li>
                        </ul>
                    </div>
                </div>
                
                <div id="scanner-status" class="scanner-status mt-2"></div>
            </div>
        `;
    }
    
    setupEventListeners() {
        const startBtn = document.getElementById('start-scan-btn');
        const stopBtn = document.getElementById('stop-scan-btn');
        const switchBtn = document.getElementById('switch-camera-btn');
        
        if (startBtn) {
            startBtn.addEventListener('click', () => this.startScanning());
        }
        
        if (stopBtn) {
            stopBtn.addEventListener('click', () => this.stopScanning());
        }
        
        if (switchBtn) {
            switchBtn.addEventListener('click', () => this.switchCamera());
        }
    }
    
    async startScanning() {
        try {
            this.showStatus('Initializing camera...', 'info');
            
            // Check if html5-qrcode is available
            if (typeof Html5Qrcode === 'undefined') {
                throw new Error('html5-qrcode library not loaded');
            }
            
            // Get available cameras
            const cameras = await Html5Qrcode.getCameras();
            if (cameras.length === 0) {
                throw new Error('No cameras found');
            }
            
            // Initialize scanner
            this.scanner = new Html5Qrcode("qr-reader");
            
            // Start scanning
            await this.scanner.start(
                cameras[0].id, // Use first camera
                this.config,
                (decodedText, decodedResult) => {
                    this.handleScanSuccess(decodedText, decodedResult);
                },
                (errorMessage) => {
                    // Handle scan errors (usually just no QR code found)
                    // Don't log these as they're normal
                }
            );
            
            this.isScanning = true;
            this.updateUI();
            this.showStatus('Scanner active - Point camera at QR code', 'success');
            
            // Show switch camera button if multiple cameras available
            if (cameras.length > 1) {
                document.getElementById('switch-camera-btn').classList.remove('d-none');
            }
            
        } catch (error) {
            console.error('Error starting QR scanner:', error);
            this.showStatus(`Error: ${error.message}`, 'danger');
            this.handleScanError(error);
        }
    }
    
    async stopScanning() {
        try {
            if (this.scanner && this.isScanning) {
                await this.scanner.stop();
                this.scanner.clear();
                this.scanner = null;
                this.isScanning = false;
                this.updateUI();
                this.showStatus('Scanner stopped', 'info');
            }
        } catch (error) {
            console.error('Error stopping QR scanner:', error);
            this.showStatus(`Error stopping scanner: ${error.message}`, 'danger');
        }
    }
    
    async switchCamera() {
        if (!this.isScanning) return;
        
        try {
            const cameras = await Html5Qrcode.getCameras();
            if (cameras.length < 2) return;
            
            // Stop current scanner
            await this.stopScanning();
            
            // Start with next camera (simple toggle for now)
            this.config.videoConstraints.facingMode = 
                this.config.videoConstraints.facingMode === "environment" ? "user" : "environment";
            
            // Restart scanner
            setTimeout(() => this.startScanning(), 500);
            
        } catch (error) {
            console.error('Error switching camera:', error);
            this.showStatus(`Error switching camera: ${error.message}`, 'danger');
        }
    }
    
    handleScanSuccess(decodedText, decodedResult) {
        console.log('QR Code scanned:', decodedText);
        
        try {
            // Try to parse as JSON first
            let qrData;
            try {
                qrData = JSON.parse(decodedText);
            } catch (e) {
                // If not JSON, treat as plain text
                qrData = { token: decodedText };
            }
            
            this.showScanResult(qrData, 'success');
            this.trigger('scan_success', { data: qrData, raw: decodedText });
            
            // Process attendance if we have the necessary data
            this.processAttendance(qrData);
            
        } catch (error) {
            console.error('Error processing scan result:', error);
            this.showScanResult({ error: error.message }, 'error');
            this.trigger('scan_error', error);
        }
    }
    
    handleScanError(error) {
        console.error('QR Scanner error:', error);
        this.showScanResult({ error: error.message }, 'error');
        this.trigger('scan_error', error);
    }
    
    async processAttendance(qrData) {
        try {
            // Get student ID from session or user input
            const studentId = this.getStudentId();
            if (!studentId) {
                throw new Error('Student ID not found. Please login again.');
            }
            
            const token = qrData.token || qrData;
            
            // Send via WebSocket if available
            if (window.attendanceWS && window.attendanceWS.isConnectedToServer()) {
                const success = window.attendanceWS.scanQRCode(token, studentId);
                if (success) {
                    this.showStatus('Processing attendance via WebSocket...', 'info');
                    return;
                }
            }
            
            // Fallback to HTTP request
            this.showStatus('Processing attendance...', 'info');
            
            const response = await fetch('/student-attendance-system/api/scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    qr_token: token,
                    student_id: studentId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showStatus('Attendance marked successfully!', 'success');
                this.showScanResult(result.data, 'success');
                this.trigger('attendance_success', result);
                
                // Stop scanning after successful attendance
                setTimeout(() => this.stopScanning(), 2000);
                
            } else {
                throw new Error(result.message || 'Failed to mark attendance');
            }
            
        } catch (error) {
            console.error('Error processing attendance:', error);
            this.showStatus(`Error: ${error.message}`, 'danger');
            this.showScanResult({ error: error.message }, 'error');
        }
    }
    
    getStudentId() {
        // Try to get student ID from various sources
        const wsElement = document.querySelector('[data-websocket="true"]');
        if (wsElement) {
            return wsElement.getAttribute('data-user-id');
        }
        
        // Try from meta tag
        const metaTag = document.querySelector('meta[name="student-id"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }
        
        // Try from global variable
        if (typeof window.studentId !== 'undefined') {
            return window.studentId;
        }
        
        return null;
    }
    
    showScanResult(data, type) {
        const resultContainer = document.getElementById('scan-result');
        if (!resultContainer) return;
        
        resultContainer.classList.remove('d-none');
        
        let html = '';
        let className = type === 'success' ? 'scanner-success' : 'scanner-error';
        
        if (type === 'success' && data.student_name) {
            html = `
                <div class="${className}">
                    <h4>✅ Attendance Marked!</h4>
                    <div class="result-details">
                        <p><strong>Student:</strong> ${data.student_name}</p>
                        <p><strong>Class:</strong> ${data.class_name}</p>
                        <p><strong>Status:</strong> <span class="status-badge status-${data.status}">${data.status}</span></p>
                        <p><strong>Time:</strong> ${data.time}</p>
                        <p><strong>Date:</strong> ${data.date}</p>
                    </div>
                </div>
            `;
        } else if (type === 'error') {
            html = `
                <div class="${className}">
                    <h4>❌ Error</h4>
                    <p>${data.error || 'Unknown error occurred'}</p>
                </div>
            `;
        } else {
            html = `
                <div class="${className}">
                    <h4>📱 QR Code Detected</h4>
                    <p>Processing attendance...</p>
                </div>
            `;
        }
        
        resultContainer.innerHTML = html;
        
        // Auto-hide after 5 seconds for success, 10 seconds for error
        const hideDelay = type === 'success' ? 5000 : 10000;
        setTimeout(() => {
            resultContainer.classList.add('d-none');
        }, hideDelay);
    }
    
    showStatus(message, type) {
        const statusContainer = document.getElementById('scanner-status');
        if (!statusContainer) return;
        
        const className = `alert alert-${type}`;
        statusContainer.innerHTML = `<div class="${className}">${message}</div>`;
        
        // Auto-hide info messages after 3 seconds
        if (type === 'info') {
            setTimeout(() => {
                statusContainer.innerHTML = '';
            }, 3000);
        }
    }
    
    updateUI() {
        const startBtn = document.getElementById('start-scan-btn');
        const stopBtn = document.getElementById('stop-scan-btn');
        const switchBtn = document.getElementById('switch-camera-btn');
        
        if (this.isScanning) {
            startBtn?.classList.add('d-none');
            stopBtn?.classList.remove('d-none');
        } else {
            startBtn?.classList.remove('d-none');
            stopBtn?.classList.add('d-none');
            switchBtn?.classList.add('d-none');
        }
    }
    
    // Event system
    on(event, callback) {
        if (!this.callbacks[event]) {
            this.callbacks[event] = [];
        }
        this.callbacks[event].push(callback);
    }
    
    off(event, callback) {
        if (this.callbacks[event]) {
            const index = this.callbacks[event].indexOf(callback);
            if (index > -1) {
                this.callbacks[event].splice(index, 1);
            }
        }
    }
    
    trigger(event, data) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('Error in QR scanner callback:', error);
                }
            });
        }
    }
    
    // Public methods
    isActive() {
        return this.isScanning;
    }
    
    destroy() {
        this.stopScanning();
        const container = document.getElementById(this.containerId);
        if (container) {
            container.innerHTML = '';
        }
    }
}

// Auto-initialize QR scanner when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const scannerContainer = document.getElementById('qr-scanner-container');
    if (scannerContainer) {
        const qrScanner = new QRScanner('qr-scanner-container');
        
        // Make scanner available globally
        window.qrScanner = qrScanner;
        
        // Set up event listeners for attendance updates
        qrScanner.on('attendance_success', function(data) {
            // Refresh attendance data or update UI
            if (typeof updateAttendanceDisplay === 'function') {
                updateAttendanceDisplay(data);
            }
        });
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = QRScanner;
}
