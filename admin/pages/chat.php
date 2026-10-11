<?php
require_once '../../config/config.php';

requireAdmin();

$userId = $_SESSION['user_id'];
$user = ['full_name' => $_SESSION['full_name'] ?? 'Admin', 'email' => $_SESSION['email'] ?? ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat - Dumaran Hiring System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
    <style>
        .chat-container {
            display: flex;
            height: calc(100vh - 80px);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .chat-sidebar {
            width: 350px;
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            background: var(--gray-50);
        }

        .chat-sidebar-header {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }

        .chat-sidebar-header h3 {
            margin: 0 0 12px 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .new-chat-btn {
            width: 100%;
            padding: 10px;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .new-chat-btn:hover {
            background: var(--secondary-blue);
        }

        .search-box {
            margin-top: 12px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }

        .loading-indicator {
            padding: 12px;
            text-align: center;
            color: var(--gray-500);
            font-size: 0.85rem;
        }

        .conversation-item {
            padding: 12px;
            margin-bottom: 8px;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            border: 1px solid var(--gray-200);
        }

        .conversation-item:hover {
            background: var(--gray-50);
        }

        .conversation-item.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .conversation-item.active .conversation-name,
        .conversation-item.active .conversation-preview {
            color: white;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--gray-900);
        }

        .conversation-time {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .conversation-item.active .conversation-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .conversation-preview {
            font-size: 0.85rem;
            color: var(--gray-600);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .unread-badge {
            background: var(--danger-red);
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-header-info h4 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .chat-header-info p {
            margin: 4px 0 0 0;
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message {
            display: flex;
            gap: 10px;
            max-width: 70%;
        }

        .message.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .message-content {
            background: var(--gray-100);
            padding: 10px 14px;
            border-radius: 12px;
            word-wrap: break-word;
        }

        .message.sent .message-content {
            background: var(--primary-blue);
            color: white;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 4px;
            padding: 0 4px;
        }

        .message.sent .message-time {
            text-align: right;
        }

        .chat-input-area {
            padding: 16px 20px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 12px;
        }

        .chat-input-area input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 24px;
            font-size: 0.95rem;
        }

        .chat-input-area button {
            padding: 12px 24px;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 24px;
            font-weight: 600;
            cursor: pointer;
            cursor: pointer;
        }

        .chat-input-area button:hover {
            background: var(--secondary-blue);
        }

        .chat-input-area button:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
        }

        .empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
        }

        .empty-chat i {
            font-size: 4rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .users-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .users-modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
        }

        .users-modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .users-modal-header h3 {
            margin: 0;
        }

        .users-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
        }

        .users-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        .user-item {
            padding: 12px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .user-item:hover {
            background: var(--gray-50);
        }

        .user-item-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .user-item-email {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .user-item-status {
            margin-top: 8px;
            font-size: 0.8rem;
        }

        .user-item-status.can-chat {
            color: var(--success-green);
        }

        .user-item-status.cannot-chat {
            color: var(--danger-red);
        }

        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                height: calc(100vh - 60px);
            }

            .chat-sidebar {
                width: 100%;
                height: 40%;
            }

            .chat-main {
                height: 60%;
            }

            .message {
                max-width: 85%;
            }
        }
    </style>
</head>
<body class="admin-dashboard">
    <?php $adminSidebarActive = 'chat'; require __DIR__ . '/_admin_sidebar.php'; ?>

<!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Chat</h1>
            <div class="topbar-actions">
                <a href="settings.php" class="topbar-btn">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <div class="chat-container">
                <!-- Chat Sidebar -->
                <div class="chat-sidebar">
                    <div class="chat-sidebar-header">
                        <h3>Conversations</h3>
                        <button class="new-chat-btn" onclick="openNewChatModal()">
                            <i class="fas fa-plus"></i> New Chat
                        </button>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="conversationSearch" placeholder="Search conversations..." onkeyup="filterConversations()">
                        </div>
                    </div>
                    <div class="conversations-list" id="conversationsList">
                        <!-- Conversations will be loaded here -->
                    </div>
                </div>

                <!-- Chat Main Area -->
                <div class="chat-main">
                    <div id="emptyChat" class="empty-chat">
                        <i class="fas fa-comments"></i>
                        <h3>Select a conversation to start chatting</h3>
                    </div>
                    <div id="chatArea" style="display: none;">
                        <div class="chat-header">
                            <div class="chat-header-info">
                                <h4 id="chatUserName">User Name</h4>
                                <p id="chatUserEmail">user@example.com</p>
                            </div>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <!-- Messages will be loaded here -->
                        </div>
                        <div class="chat-input-area">
                            <input type="text" id="messageInput" placeholder="Type a message..." onkeypress="handleMessageKeyPress(event)">
                            <button onclick="sendMessage()" id="sendBtn">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Chat Modal -->
    <div id="usersModal" class="users-modal">
        <div class="users-modal-content">
            <div class="users-modal-header">
                <h3>New Chat</h3>
                <button class="users-modal-close" onclick="closeNewChatModal()">&times;</button>
            </div>
            <div class="users-modal-body">
                <div class="search-box" style="margin-bottom: 16px;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="userSearch" placeholder="Search users..." onkeyup="searchUsers()">
                </div>
                <div id="usersList">
                    <!-- Users will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
        let currentRecipientId = null;
        let eventSource = null;
        let conversations = [];
        let allUsers = [];

        let isLoadingConversations = false;
        let conversationOffset = 0;
        const conversationLimit = 20;

        // Load conversations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadConversations(true);
        });

        async function loadConversations(reset = false) {
            if (isLoadingConversations) return;
            
            if (reset) {
                conversationOffset = 0;
                conversations = [];
            }
            
            isLoadingConversations = true;
            const list = document.getElementById('conversationsList');
            if (reset) {
                list.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--gray-500);">Loading...</div>';
            }
            
            try {
                const response = await fetch(`${BASE_URL}api/messages.php?action=conversations&limit=${conversationLimit}&offset=${conversationOffset}`, {
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    if (reset) {
                        conversations = data.data;
                    } else {
                        conversations = conversations.concat(data.data);
                    }
                    renderConversations();
                    conversationOffset += data.data.length;
                    
                    // Remove loading indicator if exists
                    const loadingIndicator = list.querySelector('.loading-indicator');
                    if (loadingIndicator) {
                        loadingIndicator.remove();
                    }
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
                if (reset) {
                    list.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--danger-red);">Error loading conversations</div>';
                }
            } finally {
                isLoadingConversations = false;
            }
        }

        function renderConversations() {
            const list = document.getElementById('conversationsList');
            if (conversations.length === 0) {
                list.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--gray-500);">No conversations yet</div>';
                return;
            }

            // Use DocumentFragment for better performance
            const fragment = document.createDocumentFragment();
            const tempDiv = document.createElement('div');
            
            tempDiv.innerHTML = conversations.map(conv => {
                const time = conv.last_message_at ? formatTime(conv.last_message_at) : '';
                const unread = conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : '';
                const isActive = currentRecipientId == conv.user_id ? 'active' : '';
                return `
                    <div class="conversation-item ${isActive}" 
                         onclick="selectConversation(${conv.user_id}, '${escapeHtml(conv.full_name)}', '${escapeHtml(conv.email)}')">
                        <div class="conversation-header">
                            <span class="conversation-name">${escapeHtml(conv.full_name)}${unread}</span>
                            <span class="conversation-time">${time}</span>
                        </div>
                        <div class="conversation-preview">${escapeHtml(conv.last_message || 'No messages yet')}</div>
                    </div>
                `;
            }).join('');
            
            while (tempDiv.firstChild) {
                fragment.appendChild(tempDiv.firstChild);
            }
            
            list.innerHTML = '';
            list.appendChild(fragment);
            
            // Add scroll listener for infinite scroll
            if (!list.dataset.scrollListenerAdded) {
                list.addEventListener('scroll', function() {
                    if (list.scrollTop + list.clientHeight >= list.scrollHeight - 100) {
                        // Load more when near bottom
                        loadConversations(false);
                    }
                });
                list.dataset.scrollListenerAdded = 'true';
            }
        }

        let searchTimeout = null;
        function filterConversations() {
            // Debounce search to improve performance
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const search = document.getElementById('conversationSearch').value.toLowerCase();
                const items = document.querySelectorAll('.conversation-item');
                
                // Use requestAnimationFrame for smooth rendering
                requestAnimationFrame(() => {
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(search) ? 'block' : 'none';
                    });
                });
            }, 150);
        }

        function selectConversation(userId, userName, userEmail) {
            currentRecipientId = userId;
            document.getElementById('chatUserName').textContent = userName;
            document.getElementById('chatUserEmail').textContent = userEmail;
            document.getElementById('emptyChat').style.display = 'none';
            document.getElementById('chatArea').style.display = 'flex';
            document.getElementById('chatArea').style.flexDirection = 'column';
            
            // Update active conversation
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('onclick') && item.getAttribute('onclick').includes(`selectConversation(${userId},`)) {
                    item.classList.add('active');
                }
            });
            
            loadMessages();
            startSSE();
        }

        async function loadMessages() {
            if (!currentRecipientId) return;
            
            try {
                const response = await fetch(`${BASE_URL}api/messages.php?action=index&recipient_id=${currentRecipientId}`, {
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    renderMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('chatMessages');
            container.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == <?= $userId ?>;
                const time = formatTime(msg.created_at);
                const avatar = msg.sender_name ? msg.sender_name.charAt(0).toUpperCase() : 'U';
                return `
                    <div class="message ${isSent ? 'sent' : ''}">
                        <div class="message-avatar">${avatar}</div>
                        <div>
                            <div class="message-content">${escapeHtml(msg.message)}</div>
                            <div class="message-time">${time}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.scrollTop = container.scrollHeight;
        }

        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentRecipientId) return;
            
            const sendBtn = document.getElementById('sendBtn');
            const originalText = sendBtn.innerHTML;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            let controller = null;
            let timeoutId = null;
            
            try {
                // Create abort controller for timeout
                controller = new AbortController();
                timeoutId = setTimeout(() => {
                    console.warn('Request timeout - aborting fetch');
                    controller.abort();
                }, 30000); // 30 second timeout
                
                const response = await fetch(`${BASE_URL}api/messages.php?action=send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        recipient_id: currentRecipientId,
                        message: message
                    }),
                    signal: controller.signal
                });
                
                // Clear timeout on successful response
                if (timeoutId) {
                    clearTimeout(timeoutId);
                    timeoutId = null;
                }
                
                // Check if response is OK
                if (!response.ok) {
                    let errorText = '';
                    try {
                        errorText = await response.text();
                        console.error('Server error response:', errorText);
                    } catch (e) {
                        console.error('Could not read error response:', e);
                    }
                    throw new Error(`Server error: ${response.status} ${response.statusText}`);
                }
                
                // Check content type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    let text = '';
                    try {
                        text = await response.text();
                        console.error('Non-JSON response:', text);
                    } catch (e) {
                        console.error('Could not read non-JSON response:', e);
                    }
                    throw new Error('Server returned invalid response. Please check the console for details.');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    input.value = '';
                    loadMessages();
                    loadConversations();
                } else {
                    alert('Failed to send message: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                // Always clear timeout in catch block
                if (timeoutId) {
                    clearTimeout(timeoutId);
                    timeoutId = null;
                }
                
                console.error('Error sending message:', error);
                console.error('Error name:', error.name);
                console.error('Error message:', error.message);
                
                let errorMessage = 'Failed to send message';
                if (error.name === 'AbortError') {
                    errorMessage = 'Request timed out or was cancelled. The server may be taking too long to respond. Please check your connection and try again.';
                } else if (error instanceof TypeError && error.message.includes('fetch')) {
                    errorMessage = 'Network error: Could not connect to the server. Please check your internet connection.';
                } else if (error instanceof SyntaxError) {
                    errorMessage = 'Server returned invalid response. Please check the console for details.';
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                alert(errorMessage);
            } finally {
                // Ensure timeout is always cleared
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;
            }
        }

        function handleMessageKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }

        function startSSE() {
            if (eventSource) {
                eventSource.close();
            }
            
            if (!currentRecipientId) return;
            
            const url = `${BASE_URL}api/messages_sse.php?recipient_id=${currentRecipientId}&last_message_id=0`;
            eventSource = new EventSource(url);
            
            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                if (data.success && data.data) {
                    loadMessages();
                    loadConversations();
                }
            };
            
            eventSource.onerror = function() {
                eventSource.close();
                setTimeout(startSSE, 3000);
            };
        }

        function openNewChatModal() {
            document.getElementById('usersModal').style.display = 'block';
            searchUsers();
        }

        function closeNewChatModal() {
            document.getElementById('usersModal').style.display = 'none';
        }

        let userSearchTimeout = null;
        async function searchUsers() {
            // Debounce user search
            clearTimeout(userSearchTimeout);
            userSearchTimeout = setTimeout(async () => {
                const search = document.getElementById('userSearch').value;
                const usersList = document.getElementById('usersList');
                
                if (search.length === 0) {
                    usersList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--gray-500);">Start typing to search users...</div>';
                    return;
                }
                
                usersList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--gray-500);">Searching...</div>';
                
                try {
                    const response = await fetch(`${BASE_URL}api/messages.php?action=search-users&search=${encodeURIComponent(search)}`, {
                        headers: {
                            'X-CSRF-Token': CSRF_TOKEN
                        }
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        allUsers = data.data;
                        renderUsers();
                    }
                } catch (error) {
                    console.error('Error searching users:', error);
                    usersList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--danger-red);">Error searching users</div>';
                }
            }, 300);
        }

        function renderUsers() {
            const list = document.getElementById('usersList');
            if (allUsers.length === 0) {
                list.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--gray-500);">No users found</div>';
                return;
            }

            list.innerHTML = allUsers.map(user => {
                const status = user.can_chat ? 
                    '<span class="user-item-status can-chat">Can chat</span>' : 
                    '<span class="user-item-status cannot-chat">No approved/rejected applications</span>';
                return `
                    <div class="user-item" onclick="startChatWithUser(${user.id}, '${escapeHtml(user.full_name)}', '${escapeHtml(user.email)}')">
                        <div class="user-item-name">${escapeHtml(user.full_name)}</div>
                        <div class="user-item-email">${escapeHtml(user.email)}</div>
                        ${status}
                    </div>
                `;
            }).join('');
        }

        function startChatWithUser(userId, userName, userEmail) {
            closeNewChatModal();
            selectConversation(userId, userName, userEmail);
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);
            
            if (minutes < 1) return 'Just now';
            if (minutes < 60) return `${minutes}m ago`;
            if (hours < 24) return `${hours}h ago`;
            if (days < 7) return `${days}d ago`;
            
            return date.toLocaleDateString();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('usersModal');
            if (event.target == modal) {
                closeNewChatModal();
            }
        }
    </script>
</body>
</html>

