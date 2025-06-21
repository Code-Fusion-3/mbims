<?php
require_once __DIR__ . '/../config/database.php';

function get_inbox_messages($user_id, $page = 1, $per_page = 20)
{
    $conn = connect_mbims_db();
    $offset = ($page - 1) * $per_page;

    $sql = "SELECT m.*, mr.read_status, u.first_name, u.last_name 
            FROM messages m
            JOIN message_recipients mr ON m.id = mr.message_id
            JOIN users u ON m.sender_id = u.id
            WHERE mr.recipient_id = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $per_page, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM message_recipients WHERE recipient_id = ?";
    $stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    mysqli_close($conn);
    return ['messages' => $messages, 'total' => $total];
}

function get_sent_messages($user_id, $page = 1, $per_page = 20)
{
    $conn = connect_mbims_db();
    $offset = ($page - 1) * $per_page;

    $sql = "SELECT m.*, GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as recipients
            FROM messages m
            JOIN message_recipients mr ON m.id = mr.message_id
            JOIN users u ON mr.recipient_id = u.id
            WHERE m.sender_id = ?
            GROUP BY m.id
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $per_page, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM messages WHERE sender_id = ?";
    $stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    mysqli_close($conn);
    return ['messages' => $messages, 'total' => $total];
}

function get_message($message_id, $user_id)
{
    $conn = connect_mbims_db();

    $sql = "SELECT m.*, u.first_name, u.last_name 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.id = ? 
            AND (m.sender_id = ? OR EXISTS (
                SELECT 1 FROM message_recipients WHERE message_id = ? AND recipient_id = ?
            ))";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiii", $message_id, $user_id, $message_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $message = mysqli_fetch_assoc($result);

    if ($message) {
        // Mark as read
        $update_sql = "UPDATE message_recipients SET read_status = 'read', read_at = CURRENT_TIMESTAMP 
                       WHERE message_id = ? AND recipient_id = ? AND read_status = 'unread'";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "ii", $message_id, $user_id);
        mysqli_stmt_execute($stmt);
    }

    mysqli_close($conn);
    return $message;
}

function get_unread_message_count($user_id)
{
    $conn = connect_mbims_db();
    $sql = "SELECT COUNT(*) as unread_count FROM message_recipients WHERE recipient_id = ? AND read_status = 'unread'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $count = mysqli_fetch_assoc($result)['unread_count'];
    mysqli_close($conn);
    return $count;
}

function get_valid_recipients($user_id, $user_role)
{
    $conn = connect_mbims_db();
    $recipients = [];

    // All users can message admins
    $admin_sql = "SELECT id, first_name, last_name, role FROM users WHERE role = 'admin' AND id != ?";
    $stmt = mysqli_prepare($conn, $admin_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $recipients[$row['id']] = $row;
    }

    if ($user_role == 'admin') {
        // Admin can message everyone
        $all_users_sql = "SELECT id, first_name, last_name, role FROM users WHERE id != ?";
        $stmt = mysqli_prepare($conn, $all_users_sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $recipients[$row['id']] = $row;
        }
    } else {
        // Partners and Accountants have scoped permissions
        $business_ids_sql = "";
        if ($user_role == 'partner') {
            // Businesses owned by the partner
            $business_ids_sql = "SELECT id FROM businesses WHERE owner_id = ?";
        } elseif ($user_role == 'accountant') {
            // Businesses assigned to the accountant
            $business_ids_sql = "SELECT business_id FROM user_business_assignments WHERE user_id = ?";
        }

        if ($business_ids_sql) {
            $stmt = mysqli_prepare($conn, $business_ids_sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $business_ids = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $business_ids[] = $row['id'] ?? $row['business_id'];
            }

            if (!empty($business_ids)) {
                $business_ids_str = implode(',', $business_ids);

                // Get partners (owners) of these businesses
                $partner_sql = "SELECT id, first_name, last_name, role FROM users WHERE id IN (SELECT owner_id FROM businesses WHERE id IN ($business_ids_str)) AND id != ?";
                $stmt = mysqli_prepare($conn, $partner_sql);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $recipients[$row['id']] = $row;
                }

                // Get accountants assigned to these businesses
                $accountant_sql = "SELECT u.id, u.first_name, u.last_name, u.role FROM users u JOIN user_business_assignments uba ON u.id = uba.user_id WHERE uba.business_id IN ($business_ids_str) AND u.id != ?";
                $stmt = mysqli_prepare($conn, $accountant_sql);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $recipients[$row['id']] = $row;
                }
            }
        }
    }

    mysqli_close($conn);
    return array_values($recipients);
}

function send_message($sender_id, $recipient_ids, $subject, $body)
{
    $conn = connect_mbims_db();
    mysqli_begin_transaction($conn);

    try {
        // Insert into messages table
        $sql = "INSERT INTO messages (sender_id, subject, body) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $sender_id, $subject, $body);
        mysqli_stmt_execute($stmt);
        $message_id = mysqli_insert_id($conn);

        // Insert into message_recipients table
        $sql = "INSERT INTO message_recipients (message_id, recipient_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);

        $recipient_details = [];
        if (!empty($recipient_ids)) {
            $ids_str = implode(',', array_map('intval', $recipient_ids));
            $user_sql = "SELECT id, email, phone FROM users WHERE id IN ($ids_str)";
            $user_result = mysqli_query($conn, $user_sql);
            while ($row = mysqli_fetch_assoc($user_result)) {
                $recipient_details[$row['id']] = $row;
            }
        }

        foreach ($recipient_ids as $recipient_id) {
            mysqli_stmt_bind_param($stmt, "ii", $message_id, $recipient_id);
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($conn);

        // Get sender info
        $sender_sql = "SELECT first_name, last_name FROM users WHERE id = ?";
        $sender_stmt = mysqli_prepare($conn, $sender_sql);
        mysqli_stmt_bind_param($sender_stmt, "i", $sender_id);
        mysqli_stmt_execute($sender_stmt);
        $sender_info = mysqli_fetch_assoc(mysqli_stmt_get_result($sender_stmt));
        $sender_name = $sender_info['first_name'] . ' ' . $sender_info['last_name'];

        mysqli_close($conn);

        // Trigger notifications
        require_once __DIR__ . '/../includes/notification_service.php';
        $notification_service = new NotificationService();
        foreach ($recipient_ids as $recipient_id) {
            if (isset($recipient_details[$recipient_id])) {
                $notification_service->sendNewMessageNotification($recipient_details[$recipient_id], $sender_name, $subject, $body, $message_id);
            }
        }

        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_close($conn);
        error_log("Message sending failed: " . $e->getMessage());
        return false;
    }
}