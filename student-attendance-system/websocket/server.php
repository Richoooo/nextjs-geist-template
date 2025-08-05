<?php
/**
 * WebSocket Server
 * Student Attendance System
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/websocket.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Jakarta');

echo "Starting WebSocket Server...\n";

try {
    // Get WebSocket configuration
    $config = getWebSocketConfig();
    
    echo "Server configuration:\n";
    echo "- Host: {$config['host']}\n";
    echo "- Port: {$config['port']}\n";
    echo "- Component: " . get_class($config['component']) . "\n";
    
    // Create WebSocket server
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                $config['component']
            )
        ),
        $config['port'],
        $config['host']
    );
    
    echo "\n=== WebSocket Server Started Successfully ===\n";
    echo "Listening on {$config['host']}:{$config['port']}\n";
    echo "Ready to accept connections...\n";
    echo "Press Ctrl+C to stop the server\n\n";
    
    // Start the server
    $server->run();
    
} catch (Exception $e) {
    echo "Error starting WebSocket server: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
