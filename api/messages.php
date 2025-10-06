<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    $userId = requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'inbox';
            
            if ($action === 'inbox') {
                $messages = $supabase->select('messages', '*', ['recipient_id' => $userId], 'created_at.desc');
                foreach ($messages as &$msg) {
                    $sender = $supabase->select('users', 'full_name,username', ['id' => $msg['sender_id']]);
                    $msg['sender_name'] = $sender[0]['full_name'] ?? $sender[0]['username'] ?? 'مستخدم محذوف';
                }
                echo json_encode($messages ?: []);
            } elseif ($action === 'sent') {
                $messages = $supabase->select('messages', '*', ['sender_id' => $userId], 'created_at.desc');
                foreach ($messages as &$msg) {
                    $recipient = $supabase->select('users', 'full_name,username', ['id' => $msg['recipient_id']]);
                    $msg['recipient_name'] = $recipient[0]['full_name'] ?? $recipient[0]['username'] ?? 'مستخدم محذوف';
                }
                echo json_encode($messages ?: []);
            } elseif ($action === 'unread_count') {
                $messages = $supabase->select('messages', 'id', ['recipient_id' => $userId, 'is_read' => 'false']);
                echo json_encode(['count' => count($messages)]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'send';
            
            if ($action === 'send') {
                $messageData = [
                    'sender_id' => $userId,
                    'recipient_id' => (int)$data['recipient_id'],
                    'subject' => sanitizeInput($data['subject']),
                    'body' => sanitizeInput($data['body']),
                    'priority' => $data['priority'] ?? 'normal'
                ];
                
                $result = $supabase->insert('messages', $messageData, false);
                
                $notification = [
                    'user_id' => (int)$data['recipient_id'],
                    'type' => 'message',
                    'title' => 'رسالة جديدة',
                    'message' => 'لديك رسالة جديدة من ' . $_SESSION['username'],
                    'link' => '/messages',
                    'icon' => 'envelope',
                    'priority' => $data['priority'] ?? 'normal'
                ];
                $supabase->insert('notifications', $notification, false);
                
                echo json_encode(['success' => true, 'message' => 'تم إرسال الرسالة بنجاح']);
            } elseif ($action === 'mark_read') {
                $supabase->update('messages', ['is_read' => true, 'read_at' => date('Y-m-d H:i:s')], ['id' => $data['id']], false);
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)$data['id'];
            
            $message = $supabase->select('messages', 'sender_id,recipient_id', ['id' => $id]);
            if ($message && ($message[0]['sender_id'] == $userId || $message[0]['recipient_id'] == $userId)) {
                $supabase->delete('messages', ['id' => $id]);
                echo json_encode(['success' => true, 'message' => 'تم حذف الرسالة']);
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'غير مصرح لك بحذف هذه الرسالة']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'الطريقة غير مسموح بها']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => getenv('APP_ENV') === 'production' ? 'حدث خطأ في الخادم' : $e->getMessage()
    ]);
}

