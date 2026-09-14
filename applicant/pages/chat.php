<?php
require_once '../../config/config.php';

requireAuth();

// Redirect admin to admin dashboard
if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/chat.php');
    exit();
}

// Require privacy waiver acceptance
requirePrivacyWaiver();

require_once '../../includes/models.php';

$userId = $_SESSION['user_id'];
$userModel = new User();
$applicationModel = new Application();

$user = $userModel->findById($userId);

// Check if user has approved or rejected applications
$applications = $applicationModel->findAll('user_id = ? AND status IN (?, ?)', [
    $userId, 
    STATUS_APPROVED, 
    STATUS_REJECTED
]);

$canChat = !empty($applications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <?php include '../../includes/pwa-header.php'; ?>
    <style>
        .chat-container {
            display: flex;
            height: calc(100vh - 80px);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
            background: var(--white);
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
            background: var(--white);
        }

        .chat-sidebar-header h3 {
            margin: 0 0 12px 0;
            font-size: 1.25rem;
            font-weight: 600;
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

        .conversation-item {
            padding: 12px;
            margin-bottom: 8px;
            background: var(--white);
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
            background: var(--white);
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

        .chat-disabled {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
        }

        .chat-disabled i {
            font-size: 4rem;
            color: var(--gray-400);
            margin-bottom: 20px;
        }

        .chat-disabled h3 {
            color: var(--gray-700);
            margin-bottom: 12px;
        }

        .chat-disabled p {
            color: var(--gray-600);
            margin-bottom: 20px;
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
<body data-theme="system">
<?php include '../../includes/applicant-theme-init.php'; ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../../assets/logo.png" alt="WPU Logo" style="height: 32px; width: auto;">
                <span>WPU Jobs</span>
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="positions.php" class="sidebar-nav-item">
                <i class="fas fa-briefcase"></i>
                <span>Browse Jobs</span>
            </a>
            <a href="my-applications.php" class="sidebar-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>My Applications</span>
            </a>
            <a href="chat.php" class="sidebar-nav-item active">
                <i class="fas fa-comments"></i>
                <span>Chat</span>
            </a>
            <a href="profile.php" class="sidebar-nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="sidebar-user-role">Applicant</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Chat</h1>
            <div class="topbar-actions">
                <?php include '../../includes/applicant-theme-toggle.php'; ?>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <?php if (!$canChat): ?>
            <div class="chat-disabled">
                <i class="fas fa-comment-slash"></i>
                <h3>Chat Not Available</h3>
                <p>You can only chat with administrators after your application has been approved or rejected.</p>
                <a href="my-applications.php" class="btn btn-primary">
                    <i class="fas fa-file-alt"></i> View My Applications
                </a>
            </div>
            <?php else: ?>
            <div class="chat-container">
                <!-- Chat Sidebar -->
                <div class="chat-sidebar">
                    <div class="chat-sidebar-header">
                        <h3>Conversations</h3>
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
                        <h3>No conversations yet</h3>
                        <p>You can only reply to conversations. An administrator will message you first.</p>
                    </div>
                    <div id="chatArea" style="display: none;">
                        <div class="chat-header">
                            <div class="chat-header-info">
                                <h4 id="chatUserName">Admin</h4>
                                <p id="chatUserEmail">admin@example.com</p>
                            </div>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <!-- Messages will be loaded here -->
                        </div>
                        <div class="chat-input-area">
                            <input type="text" id="messageInput" placeholder="Type a reply..." onkeypress="handleMessageKeyPress(event)" disabled>
                            <button onclick="sendMessage()" id="sendBtn" disabled>
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                        <div id="chatInfo" style="padding: 12px 20px; background: var(--gray-50); border-top: 1px solid var(--gray-200); font-size: 0.85rem; color: var(--gray-600); display: none;">
                            <i class="fas fa-info-circle"></i> You can only reply to conversations. An administrator must message you first.
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/applicant-theme.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($canChat): ?>
        const BASE_URL = '<?= BASE_URL ?>';
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
        let currentRecipientId = null;
        let eventSource = null;
        let conversations = [];

        // Load conversations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadConversations();
        });

        async function loadConversations() {
            try {
                const response = await fetch(`${BASE_URL}api/messages.php?action=conversations`, {
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    conversations = data.data;
                    renderConversations();
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
            }
        }

        function renderConversations() {
            const list = document.getElementById('conversationsList');
            if (conversations.length === 0) {
                list.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--gray-500);">No conversations yet. An administrator will message you first.</div>';
                return;
            }

            list.innerHTML = conversations.map(conv => {
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
        }

        function filterConversations() {
            const search = document.getElementById('conversationSearch').value.toLowerCase();
            const items = document.querySelectorAll('.conversation-item');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(search) ? 'block' : 'none';
            });
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
        
        function checkCanReply(messages) {
            // Check if there's at least one message from an admin (not from the applicant)
            const hasAdminMessage = messages.some(msg => {
                return msg.sender_id != <?= $userId ?> && (msg.sender_role === 'admin' || !msg.sender_role);
            });
            const messageInput = document.getElementById('messageInput');
            const sendBtn = document.getElementById('sendBtn');
            const chatInfo = document.getElementById('chatInfo');
            
            if (hasAdminMessage && messages.length > 0) {
                messageInput.disabled = false;
                sendBtn.disabled = false;
                chatInfo.style.display = 'none';
                messageInput.placeholder = 'Type a reply...';
            } else {
                messageInput.disabled = true;
                sendBtn.disabled = true;
                chatInfo.style.display = 'block';
                messageInput.placeholder = 'Wait for administrator to message you first...';
            }
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
                const avatar = msg.sender_name ? msg.sender_name.charAt(0).toUpperCase() : 'A';
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
            
            // Check if applicant can reply (must have at least one admin message)
            checkCanReply(messages);
        }

        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentRecipientId) return;
            
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            
            try {
                // Find application_id for this recipient if available
                let applicationId = null;
                const conv = conversations.find(c => c.user_id == currentRecipientId);
                if (conv && conv.application_id) {
                    applicationId = conv.application_id;
                }
                
                const body = {
                    recipient_id: currentRecipientId,
                    message: message
                };
                
                if (applicationId) {
                    body.application_id = applicationId;
                }
                
                const response = await fetch(`${BASE_URL}api/messages.php?action=send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify(body)
                });
                
                // Check if response is OK and content type is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
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
                console.error('Error sending message:', error);
                if (error instanceof SyntaxError) {
                    alert('Failed to send message: Server returned invalid response. Please check the console for details.');
                } else {
                    alert('Failed to send message: ' + (error.message || 'Unknown error'));
                }
            } finally {
                sendBtn.disabled = false;
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
        <?php endif; ?>
    </script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>

