<?php
/**
 * Logout API Endpoint
 * Student Attendance System
 */

require_once __DIR__ . '/../includes/auth.php';

$auth = getAuth();

// Perform logout
$result = $auth->logout();

// Redirect to login page
header('Location: ../pages/index.php?success=' . urlencode('Logged out successfully'));
exit;
?>
