<?php
require_once __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;

try {
    // Check if the application is running on Render, or locally
    // You should set this environment variable in your Render dashboard
    $mongoUri = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
    
    $client = new Client($mongoUri);
    // Use a database named 'chatgo'
    $db = $client->chatgo;
    
    // Collections
    $messagesCollection = $db->messages;
    $typingCollection = $db->typing;
    $usersCollection = $db->users;

} catch (Exception $e) {
    error_log("Failed to connect to MongoDB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}
?>
