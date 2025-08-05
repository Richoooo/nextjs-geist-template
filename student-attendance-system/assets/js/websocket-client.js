/**
 * WebSocket Client for Student Attendance System
 * Handles real-time communication between client and server
 */

class AttendanceWebSocket {
    constructor(url = 'ws://localhost:8080') {
        this.url = url;
        this.socket = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        this.userId = null;
        this.userType = null;
        this.callbacks = {};
        this.heartbeatInterval = null;
        
        this.init();
    }
    
    init() {
        this.connect();
        this.setupEventListeners();
    }
    
    connect() {
        try {
            console.log('Connecting to WebSocket server:', this.url);
            this.socket = new WebSocket(this.url);
            
            this.socket.onopen = (event) => {
                console.log('WebSocket connected successfully');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.showConnectionStatus('Connected', 'success');
                this.startHeartbeat();
                
                // Authenticate if user data is available
                if (this.userId && this.userType) {
                    this.authenticate(this.userId, this.userType);
                }
                
                this.trigger('connected', event);
            };
            
            this.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    console.log('WebSocket message received:', data);
                    this.handleMessage(data);
                } catch (error) {
                    console.error('Error parsing WebSocket message:', error);
                }
            };
            
            this.socket.onclose = (event) => {
                console.log('WebSocket connection closed:', event.code, event.reason);
                this.isConnected = false;
                this.stopHeartbeat();
                this.showConnectionStatus('Disconnected', 'warning');
                
                if (!event.wasClean && this.reconnectAttempts < this.maxReconnectAttempts) {
                    this.reconnect();
                }
                
                this.trigger('disconnected', event);
            };
            
            this.socket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.showConnectionStatus('Connection Error', 'danger');
                this.trigger('error', error);
            };
            
        } catch (error) {
            console.error('Failed to create WebSocket connection:', error);
            this.showConnectionStatus('Connection Failed', 'danger');
        }
    }
    
    reconnect() {
        this.reconnectAttempts++;
        console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);
        this.showConnectionStatus(`Reconnecting... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`, 'info');
        
        setTimeout(() => {
            this.connect();
        }, this.reconnectDelay);
    }
    
    authenticate(userId, userType) {
        this.userId = userId;
        this.userType = userType;
        
        if (this.isConnected) {
            this.send({
                type: 'auth',
                user_id: userId,
                user_type: userType
            });
        }
    }
    
    send(data) {
        if (this.isConnected && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(data));
            return true;
        } else {
            console.warn('WebSocket is not connected. Message not sent:', data);
            return false;
        }
    }
    
    handleMessage(data) {
        switch (data.type) {
            case 'system':
                this.handleSystemMessage(data);
                break;
                
            case 'auth_success':
                this.handleAuthSuccess(data);
                break;
                
            case 'attendance_success':
                this.handleAttendanceSuccess(data);
                break;
                
            case 'new_attendance':
                this.handleNewAttendance(data);
                break;
                
            case 'attendance_data':
                this.handleAttendanceData(data);
                break;
                
            case 'error':
                this.handleError(data);
                break;
                
            case 'pong':
                // Heartbeat response
                break;
                
            default:
                console.log('Unknown message type:', data.type);
                this.trigger('message', data);
        }
    }
    
    handleSystemMessage(data) {
        console.log('System message:', data.message);
        this.showNotification(data.message, 'info');
        this.trigger('system', data);
    }
    
    handleAuthSuccess(data) {
        console.log('Authentication successful:', data.user);
        this.showNotification(`Welcome, ${data.user.name}!`, 'success');
        this.trigger('authenticated', data);
    }
    
    handleAttendanceSuccess(data) {
        console.log('Attendance marked successfully:', data.data);
        this.showNotification(
            `Attendance marked: ${data.data.status} for ${data.data.class_name}`,
            'success'
        );
        this.trigger('attendance_marked', data);
        
        // Update UI elements
        this.updateAttendanceDisplay(data.data);
    }
    
    handleNewAttendance(data) {
        console.log('New attendance notification:', data.data);
        
        if (this.userType === 'teacher') {
            this.showNotification(
                `${data.data.student_name} marked attendance: ${data.data.status}`,
                data.data.status === 'late' ? 'warning' : 'success'
            );
        }
        
        this.trigger('new_attendance', data);
        this.updateAttendanceList(data.data);
    }
    
    handleAttendanceData(data) {
        console.log('Attendance data received:', data.data);
        this.trigger('attendance_data', data);
        this.displayAttendanceData(data.data);
    }
    
    handleError(data) {
        console.error('WebSocket error:', data.message);
        this.showNotification(data.message, 'danger');
        this.trigger('error', data);
    }
    
    // Public methods for sending specific messages
    scanQRCode(qrToken, studentId) {
        return this.send({
            type: 'attendance_scan',
            qr_token: qrToken,
            student_id: studentId
        });
    }
    
    getAttendance(studentId, limit = 10) {
        return this.send({
            type: 'get_attendance',
            student_id: studentId,
            limit: limit
        });
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
                    console.error('Error in event callback:', error);
                }
            });
        }
    }
    
    // UI Helper methods
    showConnectionStatus(message, type) {
        const statusElement = document.getElementById('connection-status');
        if (statusElement) {
            statusElement.textContent = message;
            statusElement.className = `connection-status ${type}`;
        }
    }
    
    showNotification(message, type = 'info', duration = 5000) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} fade-in`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Add to page
        let container = document.getElementById('notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
        
        container.appendChild(notification);
        
        // Auto remove after duration
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }
    
    updateAttendanceDisplay(attendanceData) {
        // Update attendance stats if elements exist
        const presentCount = document.getElementById('present-count');
        const lateCount = document.getElementById('late-count');
        const totalCount = document.getElementById('total-count');
        
        if (presentCount && attendanceData.status === 'present') {
            presentCount.textContent = parseInt(presentCount.textContent) + 1;
        }
        
        if (lateCount && attendanceData.status === 'late') {
            lateCount.textContent = parseInt(lateCount.textContent) + 1;
        }
        
        if (totalCount) {
            totalCount.textContent = parseInt(totalCount.textContent) + 1;
        }
        
        // Add to recent attendance list
        this.addToAttendanceList(attendanceData);
    }
    
    updateAttendanceList(attendanceData) {
        this.addToAttendanceList(attendanceData);
    }
    
    addToAttendanceList(attendanceData) {
        const attendanceList = document.getElementById('attendance-list');
        if (attendanceList) {
            const row = document.createElement('tr');
            row.className = 'fade-in';
            row.innerHTML = `
                <td>${attendanceData.student_name || 'N/A'}</td>
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
            
            // Remove old rows if too many
            const maxRows = 10;
            while (attendanceList.children.length > maxRows) {
                attendanceList.removeChild(attendanceList.lastChild);
            }
        }
    }
    
    displayAttendanceData(attendanceData) {
        const dataContainer = document.getElementById('attendance-data');
        if (dataContainer && Array.isArray(attendanceData)) {
            let html = '<div class="attendance-history">';
            
            attendanceData.forEach(record => {
                html += `
                    <div class="attendance-record">
                        <div class="record-header">
                            <span class="class-name">${record.class_name}</span>
                            <span class="status-badge status-${record.status}">${record.status}</span>
                        </div>
                        <div class="record-details">
                            <span class="date">${record.date}</span>
                            <span class="time">${record.time_in}</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            dataContainer.innerHTML = html;
        }
    }
    
    // Heartbeat to keep connection alive
    startHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            if (this.isConnected) {
                this.send({ type: 'ping' });
            }
        }, 30000); // Send ping every 30 seconds
    }
    
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }
    
    setupEventListeners() {
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Page is hidden, reduce activity
                this.stopHeartbeat();
            } else {
                // Page is visible, resume activity
                if (this.isConnected) {
                    this.startHeartbeat();
                } else {
                    this.connect();
                }
            }
        });
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            this.disconnect();
        });
    }
    
    disconnect() {
        if (this.socket) {
            this.socket.close(1000, 'Client disconnecting');
        }
        this.stopHeartbeat();
    }
    
    // Utility methods
    isConnectedToServer() {
        return this.isConnected && this.socket && this.socket.readyState === WebSocket.OPEN;
    }
    
    getConnectionState() {
        if (!this.socket) return 'Not initialized';
        
        switch (this.socket.readyState) {
            case WebSocket.CONNECTING: return 'Connecting';
            case WebSocket.OPEN: return 'Connected';
            case WebSocket.CLOSING: return 'Closing';
            case WebSocket.CLOSED: return 'Closed';
            default: return 'Unknown';
        }
    }
}

// Global WebSocket instance
let attendanceWS = null;

// Initialize WebSocket when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if WebSocket should be initialized
    const wsEnabled = document.querySelector('[data-websocket="true"]');
    if (wsEnabled) {
        const wsUrl = wsEnabled.getAttribute('data-ws-url') || 'ws://localhost:8080';
        attendanceWS = new AttendanceWebSocket(wsUrl);
        
        // Auto-authenticate if user data is available
        const userId = wsEnabled.getAttribute('data-user-id');
        const userType = wsEnabled.getAttribute('data-user-type');
        
        if (userId && userType) {
            attendanceWS.authenticate(userId, userType);
        }
        
        // Make WebSocket available globally
        window.attendanceWS = attendanceWS;
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AttendanceWebSocket;
}
