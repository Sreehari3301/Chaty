<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get room ID
$room_id = isset($_GET['room']) ? $_GET['room'] : '';
error_log("load.php called for room: $room_id");

if (empty($room_id) || strlen($room_id) !== 8) {
    error_log("Invalid room ID in load.php: $room_id");
    http_response_code(400);
    die('Invalid room ID');
}

$chat_file = "chats/{$room_id}.json";
$typing_file = "chats/{$room_id}_typing.json";

// Ensure chats directory exists
if (!file_exists('chats')) {
    mkdir('chats', 0777, true);
}

// Load messages
$messages = [];
if (file_exists($chat_file)) {
    $content = file_get_contents($chat_file);
    if (!empty($content)) {
        $messages = json_decode($content, true);
        if (!is_array($messages)) {
            $messages = [];
            file_put_contents($chat_file, json_encode($messages));
        }
    }
} else {
    // Create empty chat file
    file_put_contents($chat_file, json_encode($messages));
}

// Check typing status
$typing = false;
$typing_users = [];
if (file_exists($typing_file)) {
    $typing_content = file_get_contents($typing_file);
    if (!empty($typing_content)) {
        $typing_data = json_decode($typing_content, true);
        if (is_array($typing_data)) {
            // Remove old typing indicators
            foreach ($typing_data as $user => $timestamp) {
                if (time() - $timestamp <= 5) {
                    $typing = true;
                    $typing_users[] = $user;
                }
            }
            
            // Clean up old entries
            $active_typing = [];
            foreach ($typing_data as $user => $timestamp) {
                if (time() - $timestamp <= 5) {
                    $active_typing[$user] = $timestamp;
                }
            }
            file_put_contents($typing_file, json_encode($active_typing));
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'messages' => $messages,
    'typing' => $typing,
    'typing_users' => $typing_users,
    'room' => $room_id,
    'timestamp' => time(),
    'count' => count($messages)
]);
?>