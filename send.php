<?php
// send.php - Updated version
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get parameters
$room_id = $_POST['room'] ?? $_GET['room'] ?? '';
$message = $_POST['message'] ?? '';
$user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$typing = $_GET['typing'] ?? $_POST['typing'] ?? '0';
$clear = isset($_GET['clear']) ? $_GET['clear'] === 'true' : false;

// Validate room ID
if (empty($room_id) || strlen($room_id) !== 8) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit();
}

$chat_file = "chats/{$room_id}.json";
$typing_file = "chats/{$room_id}_typing.json";
$users_file = "chats/{$room_id}_users.json";

// Ensure chats directory exists
if (!file_exists('chats')) {
    mkdir('chats', 0777, true);
}

// Handle different actions
switch ($action) {
    case 'typing':
        handleTyping($typing_file, $user_id, $typing);
        break;
        
    case 'leave':
        handleLeave($users_file, $user_id);
        break;
        
    case 'send':
        $file = $_FILES['media'] ?? null;
        handleSendMessage($chat_file, $room_id, $user_id, $message, $file);
        break;
        
    default:
        if ($clear) {
            handleClearChat($chat_file, $typing_file, $users_file);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
}

function handleTyping($typing_file, $user_id, $typing) {
    $typing_data = [];
    if (file_exists($typing_file)) {
        $content = file_get_contents($typing_file);
        if ($content) {
            $typing_data = json_decode($content, true) ?: [];
        }
    }
    
    if ($typing === '1') {
        $typing_data[$user_id] = time();
    } else {
        unset($typing_data[$user_id]);
    }
    
    // Clean old entries (older than 10 seconds)
    foreach ($typing_data as $key => $timestamp) {
        if (time() - $timestamp > 10) {
            unset($typing_data[$key]);
        }
    }
    
    file_put_contents($typing_file, json_encode($typing_data));
    echo json_encode(['status' => 'success']);
}

function handleLeave($users_file, $user_id) {
    $users = [];
    if (file_exists($users_file)) {
        $content = file_get_contents($users_file);
        if ($content) {
            $users = json_decode($content, true) ?: [];
        }
    }
    
    unset($users[$user_id]);
    file_put_contents($users_file, json_encode($users));
    echo json_encode(['status' => 'success']);
}

function handleSendMessage($chat_file, $room_id, $user_id, $message, $file = null) {
    if (empty($message) && empty($file) || empty($user_id)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Message or user ID missing']);
        return;
    }
    
    $file_data = null;
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/' . $room_id . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'File type not allowed']);
            return;
        }
        
        // Limit to 100MB
        if ($file['size'] > 100 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'File too large (max 100MB)']);
            return;
        }
        
        $file_name = uniqid('media_') . '.' . $ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $file_data = [
                'name' => $file['name'],
                'path' => $file_path,
                'type' => strpos($file['type'], 'image/') === 0 ? 'image' : 'file',
                'size' => $file['size']
            ];
        }
    }

    $messages = [];
    if (file_exists($chat_file)) {
        $content = file_get_contents($chat_file);
        if ($content) {
            $messages = json_decode($content, true) ?: [];
        }
    }
    
    // Add new message
    $new_message = [
        'id' => uniqid(),
        'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
        'user_id' => $user_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => $file_data
    ];
    
    $messages[] = $new_message;
    
    // Limit to last 200 messages
    if (count($messages) > 200) {
        $messages = array_slice($messages, -200);
    }
    
    if (file_put_contents($chat_file, json_encode($messages))) {
        echo json_encode(['status' => 'success', 'message_id' => $new_message['id']]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save message']);
    }
}

function handleClearChat($chat_file, $typing_file, $users_file) {
    file_put_contents($chat_file, json_encode([]));
    
    if (file_exists($typing_file)) {
        unlink($typing_file);
    }
    
    if (file_exists($users_file)) {
        unlink($users_file);
    }
    
    echo json_encode(['status' => 'success', 'cleared' => true]);
}
?>