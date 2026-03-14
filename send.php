<?php
// send.php - MongoDB version
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';

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

// Handle different actions
switch ($action) {
    case 'typing':
        handleTyping($typingCollection, $room_id, $user_id, $typing);
        break;
        
    case 'leave':
        handleLeave($usersCollection, $room_id, $user_id);
        break;
        
    case 'send':
        $file = $_FILES['media'] ?? null;
        handleSendMessage($messagesCollection, $room_id, $user_id, $message, $file);
        break;
        
    default:
        if ($clear) {
            handleClearChat($messagesCollection, $typingCollection, $usersCollection, $room_id);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
}

function handleTyping($typingCollection, $room_id, $user_id, $typing) {
    try {
        if ($typing === '1') {
            $typingCollection->updateOne(
                ['room_id' => $room_id, 'user_id' => $user_id],
                ['$set' => ['timestamp' => time()]],
                ['upsert' => true]
            );
        } else {
            $typingCollection->deleteOne(['room_id' => $room_id, 'user_id' => $user_id]);
        }
        
        // Clean old entries (older than 10 seconds)
        $typingCollection->deleteMany(['timestamp' => ['$lt' => time() - 10]]);
        
        echo json_encode(['status' => 'success']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }
}

function handleLeave($usersCollection, $room_id, $user_id) {
    try {
        $usersCollection->deleteOne(['room_id' => $room_id, 'user_id' => $user_id]);
        echo json_encode(['status' => 'success']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }
}

function handleSendMessage($messagesCollection, $room_id, $user_id, $message, $file = null) {
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

    $message_id = uniqid();
    // Add new message
    $new_message = [
        'id' => $message_id,
        'room_id' => $room_id,
        'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
        'user_id' => $user_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'timestamp_ms' => round(microtime(true) * 1000),
        'file' => $file_data
    ];
    
    try {
        $messagesCollection->insertOne($new_message);
        
        // Keep only last 200 messages for this room
        $count = $messagesCollection->countDocuments(['room_id' => $room_id]);
        if ($count > 200) {
            // Find messages to delete (the oldest ones)
            $messagesToDelete = $count - 200;
            $cursor = $messagesCollection->find(['room_id' => $room_id], [
                'sort' => ['timestamp_ms' => 1],
                'limit' => $messagesToDelete
            ]);
            $idsToDelete = [];
            foreach ($cursor as $doc) {
                $idsToDelete[] = $doc['_id'];
            }
            if (!empty($idsToDelete)) {
                $messagesCollection->deleteMany(['_id' => ['$in' => $idsToDelete]]);
            }
        }
        
        echo json_encode(['status' => 'success', 'message_id' => $message_id]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save message']);
    }
}

function handleClearChat($messagesCollection, $typingCollection, $usersCollection, $room_id) {
    $messagesCollection->deleteMany(['room_id' => $room_id]);
    $typingCollection->deleteMany(['room_id' => $room_id]);
    $usersCollection->deleteMany(['room_id' => $room_id]);
    
    echo json_encode(['status' => 'success', 'cleared' => true]);
}
?>