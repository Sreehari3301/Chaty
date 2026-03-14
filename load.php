<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';

// Get room ID
$room_id = isset($_GET['room']) ? $_GET['room'] : '';
error_log("load.php called for room: $room_id");

if (empty($room_id) || strlen($room_id) !== 8) {
    error_log("Invalid room ID in load.php: $room_id");
    http_response_code(400);
    die('Invalid room ID');
}

try {
    // Load messages from MongoDB
    // Get the last 200 messages sorted by timestamp ascending
    $cursor = $messagesCollection->find(['room_id' => $room_id], [
        'sort' => ['timestamp_ms' => -1],
        'limit' => 200,
        'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']
    ]);
    
    $messages = array_reverse(iterator_to_array($cursor));
    // remove mongo internal _id for json encoding
    $messages = array_map(function($msg) {
        unset($msg['_id']);
        return $msg;
    }, $messages);
    
    // Check typing status
    $typing = false;
    $typing_users = [];
    $active_threshold = time() - 5; // 5 seconds ago
    
    $typing_cursor = $typingCollection->find([
        'room_id' => $room_id,
        'timestamp' => ['$gte' => $active_threshold]
    ], [
        'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']
    ]);
    
    foreach ($typing_cursor as $typing_doc) {
        $typing = true;
        $typing_users[] = $typing_doc['user_id'];
    }
    
    // Clean up old entries
    $typingCollection->deleteMany(['timestamp' => ['$lt' => $active_threshold]]);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'messages' => array_values($messages),
        'typing' => $typing,
        'typing_users' => $typing_users,
        'room' => $room_id,
        'timestamp' => time(),
        'count' => count($messages)
    ]);

} catch (Exception $e) {
    error_log("MongoDB Error in load.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
?>