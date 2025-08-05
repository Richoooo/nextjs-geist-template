<?php
/**
 * Email Configuration
 * Student Attendance System
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailConfig {
    private $mailer;
    private $db;
    
    // Email configuration - Update these with your SMTP settings
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_port = 587;
    private $smtp_username = 'your-email@gmail.com';
    private $smtp_password = 'your-app-password';
    private $from_email = 'your-email@gmail.com';
    private $from_name = 'Student Attendance System';
    
    public function __construct() {
        $this->db = getDB();
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host       = $this->smtp_host;
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $this->smtp_username;
            $this->mailer->Password   = $this->smtp_password;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = $this->smtp_port;
            
            // Recipients
            $this->mailer->setFrom($this->from_email, $this->from_name);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Email configuration failed: " . $e->getMessage());
        }
    }
    
    public function sendAttendanceNotification($studentEmail, $studentName, $className, $status, $time) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($studentEmail, $studentName);
            
            $this->mailer->Subject = 'Attendance Confirmation - ' . $className;
            
            $statusText = ucfirst($status);
            $statusColor = $status === 'present' ? '#28a745' : ($status === 'late' ? '#ffc107' : '#dc3545');
            
            $body = $this->getAttendanceEmailTemplate($studentName, $className, $statusText, $time, $statusColor);
            $this->mailer->Body = $body;
            
            $this->mailer->send();
            
            // Log notification
            $this->logNotification($studentEmail, 'Attendance notification sent', 'email', 'sent');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            $this->logNotification($studentEmail, 'Email failed: ' . $e->getMessage(), 'email', 'failed');
            return false;
        }
    }
    
    public function sendDailyReminder($studentEmail, $studentName) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($studentEmail, $studentName);
            
            $this->mailer->Subject = 'Daily Attendance Reminder';
            
            $body = $this->getDailyReminderTemplate($studentName);
            $this->mailer->Body = $body;
            
            $this->mailer->send();
            
            $this->logNotification($studentEmail, 'Daily reminder sent', 'email', 'sent');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Daily reminder failed: " . $e->getMessage());
            $this->logNotification($studentEmail, 'Daily reminder failed: ' . $e->getMessage(), 'email', 'failed');
            return false;
        }
    }
    
    private function getAttendanceEmailTemplate($studentName, $className, $status, $time, $statusColor) {
        $currentDate = date('Y-m-d');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Attendance Confirmation</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>Attendance Confirmation</h1>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;'>
                <h2 style='color: #495057; margin-top: 0;'>Hello, {$studentName}!</h2>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid {$statusColor};'>
                    <h3 style='margin-top: 0; color: {$statusColor};'>Attendance Status: {$status}</h3>
                    <p><strong>Class:</strong> {$className}</p>
                    <p><strong>Date:</strong> {$currentDate}</p>
                    <p><strong>Time:</strong> {$time}</p>
                </div>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #1976d2;'>
                        <strong>📱 Tip:</strong> Keep scanning QR codes for accurate attendance tracking!
                    </p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #dee2e6; margin: 30px 0;'>
                
                <p style='color: #6c757d; font-size: 14px; text-align: center; margin: 0;'>
                    This is an automated message from Student Attendance System.<br>
                    Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getDailyReminderTemplate($studentName) {
        $currentDate = date('Y-m-d');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Daily Attendance Reminder</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>📚 Daily Reminder</h1>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;'>
                <h2 style='color: #495057; margin-top: 0;'>Good morning, {$studentName}!</h2>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;'>
                    <h3 style='color: #28a745; margin-top: 0;'>🎯 Don't forget to mark your attendance today!</h3>
                    <p style='font-size: 16px;'><strong>Date:</strong> {$currentDate}</p>
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h4 style='margin-top: 0; color: #856404;'>📱 How to mark attendance:</h4>
                    <ol style='color: #856404; margin: 0;'>
                        <li>Open your student dashboard</li>
                        <li>Click on 'Scan QR Code'</li>
                        <li>Scan the QR code displayed by your teacher</li>
                        <li>Confirm your attendance</li>
                    </ol>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='#' style='background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; display: inline-block; font-weight: bold;'>
                        Open Student Dashboard
                    </a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #dee2e6; margin: 30px 0;'>
                
                <p style='color: #6c757d; font-size: 14px; text-align: center; margin: 0;'>
                    This is an automated reminder from Student Attendance System.<br>
                    Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    private function logNotification($recipient, $message, $type, $status) {
        try {
            $sql = "INSERT INTO notifications (message, type, status, created_at) VALUES (?, ?, ?, NOW())";
            $this->db->execute($sql, ["To: {$recipient} - {$message}", $type, $status]);
        } catch (Exception $e) {
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }
    
    public function testEmailConfiguration() {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($this->smtp_username);
            $this->mailer->Subject = 'Test Email - Student Attendance System';
            $this->mailer->Body = '<h2>Email Configuration Test</h2><p>If you receive this email, your email configuration is working correctly!</p>';
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email test failed: " . $e->getMessage());
            return false;
        }
    }
}

// Global email instance
function getEmailConfig() {
    static $emailConfig = null;
    if ($emailConfig === null) {
        $emailConfig = new EmailConfig();
    }
    return $emailConfig;
}
?>
