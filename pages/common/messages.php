<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/messaging_functions.php';

// Require login
require_login();

$page_title = 'Messages';
$current_user = get_logged_user();

// Get view parameters
$folder = isset($_GET['folder']) ? $_GET['folder'] : 'inbox';
$view_message_id = isset($_GET['view']) ? (int) $_GET['view'] : null;

$messages = [];
$total_messages = 0;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 15;

if ($view_message_id) {
    $message = get_message($view_message_id, $current_user['id']);
} else {
    if ($folder == 'inbox') {
        $data = get_inbox_messages($current_user['id'], $current_page, $per_page);
        $messages = $data['messages'];
        $total_messages = $data['total'];
    } elseif ($folder == 'sent') {
        $data = get_sent_messages($current_user['id'], $current_page, $per_page);
        $messages = $data['messages'];
        $total_messages = $data['total'];
    }
}

$total_pages = ceil($total_messages / $per_page);
$unread_count = get_unread_message_count($current_user['id']);

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $recipients = $_POST['recipients'] ?? [];
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);

    if (!empty($recipients) && !empty($subject) && !empty($body)) {
        $success = send_message($current_user['id'], $recipients, $subject, $body);
        if ($success) {
            // TODO: Add notification logic here
            header('Location: messages.php?folder=sent&success=1');
            exit();
        } else {
            $error_message = "Failed to send message.";
        }
    } else {
        $error_message = "Recipients, subject, and body are required.";
    }
}

$valid_recipients = get_valid_recipients($current_user['id'], $current_user['role']);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Messaging Center</h1>
                <p class="text-gray-600">Communicate with other users in the system.</p>
            </div>
            <button onclick="showComposeModal()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Compose Message
            </button>
        </div>
    </div>

    <!-- Messaging Interface -->
    <div class="bg-white rounded-lg shadow">
        <div class="flex min-h-[600px]">
            <!-- Sidebar for message folders -->
            <div class="w-1/4 border-r border-gray-200">
                <div class="p-4">
                    <h2 class="text-lg font-semibold text-gray-800">Folders</h2>
                    <nav class="mt-4 space-y-2">
                        <a href="?folder=inbox"
                            class="flex items-center space-x-3 <?php echo ($folder == 'inbox' && !$view_message_id) ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-50'; ?> p-2 rounded-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            <span>Inbox</span>
                            <?php if ($unread_count > 0): ?>
                                <span class="ml-auto bg-blue-600 text-white text-xs font-semibold px-2 py-1 rounded-full">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <a href="?folder=sent"
                            class="flex items-center space-x-3 <?php echo $folder == 'sent' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-50'; ?> p-2 rounded-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 19v-9a2 2 0 012-2h14a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2zm0 0v-2a2 2 0 012-2h14a2 2 0 012 2v2">
                                </path>
                            </svg>
                            <span>Sent</span>
                        </a>
                        <a href="#" class="flex items-center space-x-3 text-gray-600 hover:bg-gray-50 p-2 rounded-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                </path>
                            </svg>
                            <span>Trash</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Message List / View -->
            <div class="w-3/4">
                <?php if ($view_message_id): ?>
                    <?php if ($message): ?>
                        <?php
                        $reply_subject = $message['subject'];
                        if (stripos(trim($reply_subject), 're:') !== 0) {
                            $reply_subject = 'Re: ' . $reply_subject;
                        }
                        $original_message_quote = "\n\n\n--- Original Message ---\n" .
                            "From: " . htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) . "\n" .
                            "Sent: " . format_datetime($message['created_at']) . "\n" .
                            "Subject: " . htmlspecialchars($message['subject']) . "\n\n" .
                            htmlspecialchars($message['body']);
                        ?>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <a href="?folder=inbox" class="text-blue-600 hover:underline inline-block">&larr; Back to
                                    Inbox</a>
                                <button onclick="showReplyForm()"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                    </svg>
                                    Reply
                                </button>
                            </div>

                            <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($message['subject']); ?>
                            </h2>
                            <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                                <span>From:
                                    <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?></span>
                                <span>|</span>
                                <span><?php echo format_datetime($message['created_at']); ?></span>
                            </div>
                            <div class="mt-6 border-t pt-6 prose max-w-none">
                                <?php echo nl2br(htmlspecialchars($message['body'])); ?>
                            </div>

                            <!-- Reply Form -->
                            <div id="replyForm" class="hidden mt-8 border-t pt-6">
                                <h3 class="text-xl font-semibold text-gray-800 mb-4">Reply to Message</h3>
                                <form method="POST">
                                    <input type="hidden" name="recipients[]" value="<?php echo $message['sender_id']; ?>">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">To:</label>
                                            <input type="text"
                                                value="<?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>"
                                                readonly
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject:</label>
                                            <input type="text" name="subject"
                                                value="<?php echo htmlspecialchars($reply_subject); ?>" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Message:</label>
                                            <textarea name="body" rows="8" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md"><?php echo $original_message_quote; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="flex justify-end space-x-3 pt-4">
                                        <button type="button" onclick="hideReplyForm()"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                                            Cancel
                                        </button>
                                        <button type="submit" name="send_message"
                                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                            Send Reply
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-20">
                            <h3 class="text-lg font-medium text-gray-900">Message not found</h3>
                            <p class="mt-1 text-sm text-gray-500">The requested message could not be found or you don't have
                                permission to view it.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="p-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800"><?php echo ucfirst($folder); ?></h2>
                    </div>
                    <?php if (!empty($messages)): ?>
                        <ul class="divide-y divide-gray-200">
                            <?php foreach ($messages as $msg): ?>
                                <li
                                    class="p-4 hover:bg-gray-50 <?php echo ($folder == 'inbox' && $msg['read_status'] == 'unread') ? 'font-bold' : ''; ?>">
                                    <a href="?view=<?php echo $msg['id']; ?>">
                                        <div class="flex justify-between">
                                            <div class="w-1/4">
                                                <?php if ($folder == 'inbox'): ?>
                                                    <?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($msg['recipients']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="w-1/2">
                                                <p class="truncate"><?php echo htmlspecialchars($msg['subject']); ?></p>
                                            </div>
                                            <div class="w-1/4 text-right text-sm text-gray-500">
                                                <?php echo format_datetime($msg['created_at']); ?>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-20">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No messages</h3>
                            <p class="mt-1 text-sm text-gray-500">This folder is empty.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Compose Message Modal -->
<div id="composeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <form method="POST">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">New Message</h3>
                <button type="button" onclick="hideComposeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To:</label>
                    <select name="recipients[]" multiple required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <?php foreach ($valid_recipients as $recipient): ?>
                            <option value="<?php echo $recipient['id']; ?>">
                                <?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name'] . ' (' . $recipient['role'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">You can select multiple recipients by holding Ctrl/Cmd.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject:</label>
                    <input type="text" name="subject" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message:</label>
                    <textarea name="body" rows="8" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                </div>
            </div>
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" onclick="hideComposeModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                    Cancel
                </button>
                <button type="submit" name="send_message"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                    Send Message
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function showComposeModal() {
        document.getElementById('composeModal').classList.remove('hidden');
    }

    function hideComposeModal() {
        document.getElementById('composeModal').classList.add('hidden');
    }

    function showReplyForm() {
        document.getElementById('replyForm').classList.remove('hidden');
    }

    function hideReplyForm() {
        document.getElementById('replyForm').classList.add('hidden');
    }
</script>

<?php
include '../../components/footer.php';
?>