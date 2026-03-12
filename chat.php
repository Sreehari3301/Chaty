<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get room ID from URL
$room_id = isset($_GET['room']) ? $_GET['room'] : '';
if (empty($room_id) || strlen($room_id) !== 8) {
    header("Location: index.html");
    exit();
}

// Start session and clear previous chat data if it's a different room
session_start();
if (!isset($_SESSION['current_room']) || $_SESSION['current_room'] !== $room_id) {
    // Initialize empty chat for this room
    $chat_file = "chats/{$room_id}.json";
    if (!file_exists('chats')) {
        mkdir('chats', 0777, true);
    }
    file_put_contents($chat_file, json_encode([]));
    $_SESSION['current_room'] = $room_id;
}

// Generate a unique user ID for this session if not already set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'user_' . bin2hex(random_bytes(4));
}
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatGO - Room: <?php echo substr($room_id, 0, 4) . '...'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .logo {
            color: #667eea;
            font-size: 1.5em;
            font-weight: bold;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .room-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .room-id {
            background: #f0f7ff;
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.9em;
            color: #667eea;
            font-weight: 500;
        }
        
        .new-chat-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .new-chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 800px;
            margin: 20px auto;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            margin: 8px 0;
            padding: 12px 18px;
            border-radius: 18px;
            max-width: 75%;
            word-wrap: break-word;
            animation: fadeIn 0.3s;
            position: relative;
        }
        
        .message.sent {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        
        .message.received {
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        
        .message-time {
            font-size: 0.7em;
            opacity: 0.7;
            margin-top: 5px;
            display: block;
        }
        
        .message.sent .message-time {
            text-align: right;
            color: rgba(255,255,255,0.8);
        }
        
        .message.received .message-time {
            text-align: left;
            color: #666;
        }
        
        .message-status {
            font-size: 0.7em;
            margin-left: 5px;
            opacity: 0.7;
        }
        
        .chat-input-container {
            padding: 20px;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        
        .message-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-size: 1em;
            outline: none;
            transition: all 0.3s;
        }
        
        .message-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .send-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2em;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .send-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .send-btn:active {
            transform: scale(0.95);
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
            animation: slideIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .typing-indicator {
            padding: 10px 20px;
            color: #666;
            font-style: italic;
            font-size: 0.9em;
            display: none;
            background: white;
            border-radius: 15px;
            margin: 5px 20px;
            align-self: flex-start;
            border: 1px solid #eee;
        }
        
        .empty-chat {
            text-align: center;
            color: #999;
            padding: 40px;
            font-size: 1.1em;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .empty-chat h3 {
            margin-bottom: 10px;
            color: #667eea;
        }
        
        .user-count {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .connection-status {
            font-size: 0.8em;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: #fff3cd;
            border-radius: 50px;
            border: 1px solid #ffeaa7;
        }
        
        .online-dot {
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Scrollbar styling */
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .chat-media {
            max-width: 100%;
            border-radius: 10px;
            margin: 5px 0;
            display: block;
            cursor: pointer;
        }

        .file-attachment {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0,0,0,0.05);
            padding: 10px;
            border-radius: 8px;
            margin-top: 5px;
            text-decoration: none;
            color: inherit;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .message.sent .file-attachment {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
        }

        .media-preview-bar {
            padding: 5px 20px;
            background: #fff3e0;
            display: none;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85em;
            border-top: 1px solid #ffe0b2;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            💬 ChatGO
        </div>
        <div class="room-info">
            <div class="room-id">Room: <?php echo $room_id; ?></div>
            <div class="user-count" id="userCount">
                <span class="online-dot"></span>
                <span id="userCountText">1 user</span>
            </div>
            <button class="new-chat-btn" onclick="newChat()">New Chat</button>
        </div>
    </div>
    
    <div class="chat-container">
        <div class="chat-messages" id="chatMessages">
            <div class="empty-chat">
                <h3>Chat Started! 🎉</h3>
                <p>Send your first message to begin chatting</p>
                <p style="font-size: 0.9em; margin-top: 20px; color: #667eea;">
                    ⓘ This chat will be automatically cleared when you leave
                </p>
                <div class="connection-status" id="connectionStatus" style="margin-top: 20px;">
                    <span class="online-dot"></span>
                    <span>Connected to room: <?php echo $room_id; ?></span>
                </div>
            </div>
        </div>
        
        <div class="media-preview-bar" id="mediaPreviewBar">
            <span id="mediaFileName">No file selected</span>
            <button onclick="clearSelectedFile()" style="background:none; border:none; color:#e65100; cursor:pointer; font-weight:bold;">✕</button>
        </div>

        <div class="chat-input-container">
            <input type="file" id="mediaInput" style="display:none" accept="image/*,.pdf,.doc,.docx,.txt">
            <button class="send-btn" style="background:#f0f0f0; color:#666; width:40px; height:40px; margin-right:5px;" onclick="document.getElementById('mediaInput').click()">
                📎
            </button>
            <input type="text" 
                   class="message-input" 
                   id="messageInput" 
                   placeholder="Type your message here..."
                   autocomplete="off">
            <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                <span style="transform: translateY(-1px)">➤</span>
            </button>
        </div>
    </div>
    
    <div class="notification" id="notification"></div>

    <script>
        // Get room ID from PHP
        const roomId = '<?php echo $room_id; ?>';
        const userId = '<?php echo $user_id; ?>';
        
        // State variables
        let lastMessageId = 0;
        let typingTimeout = null;
        let isTyping = false;
        let isOnline = true;
        let reconnectAttempts = 0;
        let messageQueue = [];
        let activeUsers = new Set([userId]);
        let messagePollInterval = null;
        
        console.log('Chat initialized:', { roomId, userId });
        
        // DOM Elements
        const chatMessages = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const typingIndicator = document.getElementById('typingIndicator');
        const typingText = document.getElementById('typingText');
        const notification = document.getElementById('notification');
        const userCountText = document.getElementById('userCountText');
        const connectionStatus = document.getElementById('connectionStatus');
        const mediaInput = document.getElementById('mediaInput');
        const mediaPreviewBar = document.getElementById('mediaPreviewBar');
        const mediaFileName = document.getElementById('mediaFileName');
        
        let selectedFile = null;
        
        // Initialize chat
        initializeChat();
        
        function initializeChat() {
            // Load messages immediately
            loadMessages();
            
            // Start polling for messages
            messagePollInterval = setInterval(loadMessages, 1500);
            
            // Set up event listeners
            setupEventListeners();
            
            // Set up connectivity monitoring
            setupConnectivityMonitoring();
            
            // Update user count
            updateUserCount();
            
            // Focus on input
            setTimeout(() => {
                messageInput.focus();
            }, 100);
        }
        
        function setupEventListeners() {
            // Send message on Enter key
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            // Typing detection
            messageInput.addEventListener('input', () => {
                if (!isTyping && messageInput.value.trim() !== '') {
                    isTyping = true;
                    sendTypingIndicator(true);
                }
                
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    if (isTyping) {
                        isTyping = false;
                        sendTypingIndicator(false);
                    }
                }, 1500);
            });

            // Media input listener
            mediaInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    selectedFile = e.target.files[0];
                    mediaFileName.textContent = `📎 ${selectedFile.name}`;
                    mediaPreviewBar.style.display = 'flex';
                    messageInput.focus();
                }
            });
            
            // Clear typing when input loses focus
            messageInput.addEventListener('blur', () => {
                if (isTyping) {
                    isTyping = false;
                    sendTypingIndicator(false);
                }
            });
            
            // Handle beforeunload (when user leaves)
            window.addEventListener('beforeunload', () => {
                // Send leave notification
                fetch(`send.php?action=leave&room=${roomId}&user_id=${userId}`)
                    .catch(e => console.error('Leave notification failed:', e));
                
                // Clear the chat for this user
                fetch(`send.php?clear=true&room=${roomId}&user_id=${userId}`)
                    .catch(e => console.error('Clear chat failed:', e));
            });
        }
        
        function setupConnectivityMonitoring() {
            // Online/offline detection
            window.addEventListener('online', () => {
                isOnline = true;
                reconnectAttempts = 0;
                showNotification('Back online ✓');
                connectionStatus.innerHTML = '<span class="online-dot"></span><span>Connected</span>';
                loadMessages(); // Load any missed messages
            });
            
            window.addEventListener('offline', () => {
                isOnline = false;
                showNotification('You are offline. Reconnecting...', 'warning');
                connectionStatus.innerHTML = '<span style="background:#ff9800;"></span><span>Offline - Reconnecting...</span>';
            });
            
            // Check connection periodically
            setInterval(() => {
                if (!isOnline && navigator.onLine) {
                    isOnline = true;
                    showNotification('Reconnected successfully!');
                    connectionStatus.innerHTML = '<span class="online-dot"></span><span>Connected</span>';
                    loadMessages();
                }
            }, 5000);
        }
        
        async function loadMessages() {
            if (!isOnline) return;
            
            try {
                const response = await fetch(`load.php?room=${roomId}&_=${Date.now()}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const data = await response.json();
                
                // Update active users
                if (data.active_users) {
                    activeUsers = new Set(data.active_users);
                    updateUserCount();
                }
                
                // Update typing indicator
                if (data.typing_users && data.typing_users.length > 0) {
                    const otherTypers = data.typing_users.filter(id => id !== userId);
                    if (otherTypers.length > 0) {
                        typingIndicator.style.display = 'flex';
                        typingText.textContent = otherTypers.length === 1 ? 'Someone is typing...' : 'People are typing...';
                    } else {
                        typingIndicator.style.display = 'none';
                    }
                } else {
                    typingIndicator.style.display = 'none';
                }
                
                // Check if we have new messages
                if (data.messages && data.messages.length > 0) {
                    // Find the last message ID
                    const lastMsg = data.messages[data.messages.length - 1];
                    const currentMessageId = lastMsg.id || lastMsg.timestamp;
                    
                    if (currentMessageId !== lastMessageId) {
                        renderMessages(data.messages);
                        lastMessageId = currentMessageId;
                        
                        // Auto-scroll if user is near bottom
                        autoScrollToBottom();
                        
                        // Show notification for new messages from others
                        if (lastMsg.user_id !== userId && document.hidden) {
                            showNotification('New message received');
                        }
                    }
                }
                
                reconnectAttempts = 0;
                
            } catch (error) {
                console.error('Error loading messages:', error);
                if (reconnectAttempts < 5) {
                    reconnectAttempts++;
                    setTimeout(loadMessages, reconnectAttempts * 1000);
                } else {
                    showNotification('Connection lost. Please refresh the page.', 'error');
                    connectionStatus.innerHTML = '<span style="background:#f44336;"></span><span>Connection lost</span>';
                }
            }
        }
        
        function renderMessages(messages) {
            const container = document.getElementById('chatMessages');
            
            // Remove empty chat message if present
            const emptyChat = container.querySelector('.empty-chat');
            if (emptyChat && messages.length > 0) {
                emptyChat.remove();
            }
            
            // Clear existing messages and rebuild
            container.innerHTML = '';
            
            messages.forEach(msg => {
                const messageDiv = document.createElement('div');
                const isOwnMessage = msg.user_id === userId;
                messageDiv.className = `message ${isOwnMessage ? 'sent' : 'received'}`;
                
                // Format time
                const time = formatTime(msg.timestamp);
                
                let fileContent = '';
                if (msg.file) {
                    if (msg.file.type === 'image') {
                        fileContent = `<img src="${msg.file.path}" class="chat-media" onclick="window.open(this.src, '_blank')">`;
                    } else {
                        fileContent = `
                            <a href="${msg.file.path}" target="_blank" class="file-attachment">
                                <span>📄 ${msg.file.name}</span>
                                <small>(${Math.round(msg.file.size / 1024)} KB)</small>
                            </a>
                        `;
                    }
                }

                // Create message content
                messageDiv.innerHTML = `
                    ${fileContent}
                    <div style="${msg.file ? 'margin-top:5px;' : ''}">${escapeHtml(msg.message)}</div>
                    <span class="message-time">${time}${isOwnMessage ? '<span class="message-status">✓</span>' : ''}</span>
                `;
                
                container.appendChild(messageDiv);
            });
            
            // If no messages, show empty state
            if (messages.length === 0 && !container.querySelector('.empty-chat')) {
                container.innerHTML = `
                    <div class="empty-chat">
                        <h3>Chat Started! 🎉</h3>
                        <p>Send your first message to begin chatting</p>
                        <div class="connection-status" style="margin-top: 20px;">
                            <span class="online-dot"></span>
                            <span>Connected to room: ${roomId}</span>
                        </div>
                    </div>
                `;
            }
        }
        
        function formatTime(timestamp) {
            try {
                const date = new Date(timestamp);
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                
                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m ago`;
                
                // Format as time if today, otherwise as date
                if (date.toDateString() === now.toDateString()) {
                    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                } else {
                    return date.toLocaleDateString([], { month: 'short', day: 'numeric' }) + 
                           ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }
            } catch (e) {
                return 'Just now';
            }
        }
        
        async function sendMessage() {
            const message = messageInput.value.trim();
            
            if ((!message && !selectedFile) || !isOnline) return;
            
            console.log('Sending message:', message);
            
            // Disable send button and show loading
            sendBtn.disabled = true;
            const originalBtnHtml = sendBtn.innerHTML;
            sendBtn.innerHTML = '<div class="spinner"></div>';
            
            // Add message to local queue for immediate display
            addLocalMessage(message, selectedFile);
            
            // Clear inputs
            messageInput.value = '';
            const currentFile = selectedFile;
            clearSelectedFile();
            
            // Stop typing indicator
            if (isTyping) {
                isTyping = false;
                sendTypingIndicator(false);
            }
            
            try {
                // Send to server
                const formData = new FormData();
                formData.append('room', roomId);
                formData.append('message', message);
                formData.append('user_id', userId);
                formData.append('action', 'send');
                if (currentFile) {
                    formData.append('media', currentFile);
                }
                
                const response = await fetch('send.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    console.log('Message sent successfully');
                    // Update message status
                    updateLastMessageStatus('✓✓');
                } else {
                    throw new Error(data.message || 'Failed to send message');
                }
                
            } catch (error) {
                console.error('Error sending message:', error);
                showNotification('Failed to send message. Trying again...', 'error');
                
                // Queue message for retry
                messageQueue.push(message);
                
                // Try to resend after delay
                setTimeout(() => {
                    if (messageQueue.length > 0) {
                        sendQueuedMessages();
                    }
                }, 3000);
                
            } finally {
                // Re-enable send button
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalBtnHtml;
            }
        }

        function clearSelectedFile() {
            selectedFile = null;
            mediaInput.value = '';
            mediaPreviewBar.style.display = 'none';
        }
        
        function addLocalMessage(message, file = null) {
            const container = document.getElementById('chatMessages');
            const emptyChat = container.querySelector('.empty-chat');
            
            if (emptyChat) {
                emptyChat.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message sent';
            
            let fileHtml = '';
            if (file) {
                if (file.type.startsWith('image/')) {
                    fileHtml = `<img src="${URL.createObjectURL(file)}" class="chat-media">`;
                } else {
                    fileHtml = `<div class="file-attachment">📄 ${file.name}</div>`;
                }
            }

            messageDiv.innerHTML = `
                ${fileHtml}
                <div style="${file ? 'margin-top:5px;' : ''}">${escapeHtml(message)}</div>
                <span class="message-time">Sending...<span class="message-status">⏳</span></span>
            `;
            
            container.appendChild(messageDiv);
            autoScrollToBottom();
        }
        
        function updateLastMessageStatus(status) {
            const messages = chatMessages.querySelectorAll('.message.sent');
            if (messages.length > 0) {
                const lastMessage = messages[messages.length - 1];
                const statusSpan = lastMessage.querySelector('.message-status');
                if (statusSpan) {
                    statusSpan.textContent = status;
                }
            }
        }
        
        async function sendQueuedMessages() {
            if (messageQueue.length === 0 || !isOnline) return;
            
            const messagesToSend = [...messageQueue];
            messageQueue = [];
            
            for (const message of messagesToSend) {
                try {
                    const formData = new FormData();
                    formData.append('room', roomId);
                    formData.append('message', message);
                    formData.append('user_id', userId);
                    formData.append('action', 'send');
                    
                    const response = await fetch('send.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.status !== 'success') {
                        throw new Error('Send failed');
                    }
                } catch (error) {
                    // Re-add to queue if failed
                    messageQueue.push(message);
                }
            }
        }
        
        function sendTypingIndicator(typing) {
            if (!isOnline) return;
            
            fetch(`send.php?action=typing&room=${roomId}&user_id=${userId}&typing=${typing ? '1' : '0'}`)
                .catch(error => console.error('Error sending typing indicator:', error));
        }
        
        function updateUserCount() {
            const count = activeUsers.size;
            userCountText.textContent = `${count} user${count !== 1 ? 's' : ''}`;
        }
        
        function autoScrollToBottom() {
            const container = chatMessages;
            const isScrolledToBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 100;
            
            if (isScrolledToBottom) {
                setTimeout(() => {
                    container.scrollTop = container.scrollHeight;
                }, 100);
            }
        }
        
        function showNotification(text, type = 'success') {
            notification.textContent = text;
            notification.style.background = type === 'error' ? '#f44336' : 
                                          type === 'warning' ? '#ff9800' : 
                                          '#4CAF50';
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
        
        function newChat() {
            if (confirm('Start a new chat? This will clear the current chat and create a new room.')) {
                // Clear current chat
                fetch(`send.php?clear=true&room=${roomId}&user_id=${userId}`)
                    .then(() => {
                        window.location.href = 'index.html';
                    })
                    .catch(() => {
                        window.location.href = 'index.html';
                    });
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Handle page visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                loadMessages();
                messageInput.focus();
            }
        });
        
        // Initialize auto-scroll
        chatMessages.addEventListener('scroll', () => {
            // You could add logic to hide/show "scroll to bottom" button here
        });
        
        // Add a scroll-to-bottom button (optional enhancement)
        function addScrollToBottomButton() {
            const scrollBtn = document.createElement('button');
            scrollBtn.innerHTML = '▼';
            scrollBtn.style.cssText = `
                position: absolute;
                bottom: 80px;
                right: 20px;
                background: #667eea;
                color: white;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                cursor: pointer;
                display: none;
                align-items: center;
                justify-content: center;
                font-size: 1.2em;
                z-index: 100;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            `;
            scrollBtn.onclick = () => {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            };
            document.querySelector('.chat-container').appendChild(scrollBtn);
        }
        
        // Call this function if you want the scroll button
        // addScrollToBottomButton();
    </script>
</body>
</html>