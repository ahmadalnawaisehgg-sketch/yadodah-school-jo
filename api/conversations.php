<?php
/**
 * API نظام المحادثات والشات
 * يدعم المحادثات بين أولياء الأمور والمسؤولين
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/email_service.php';

// التحقق من تسجيل الدخول (ولي أمر أو مستخدم)
$userType = $_SESSION['user_type'] ?? null;
$userId = null;

if ($userType === 'parent') {
    $userId = $_SESSION['parent_id'] ?? null;
} elseif (isset($_SESSION['user_id'])) {
    $userType = 'user';
    $userId = $_SESSION['user_id'];
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول', 'require_login' => true]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        // جلب جميع المحادثات
        if ($action === 'my_conversations') {
            $participants = $supabase->select('conversation_participants', '*', [
                'user_id' => $userId,
                'user_type' => $userType,
                'is_active' => true
            ]);
            
            $conversations = [];
            foreach ($participants as $p) {
                $conv = $supabase->select('conversations', '*', ['id' => $p['conversation_id']]);
                if (!empty($conv)) {
                    $conversation = $conv[0];
                    
                    // Count unread messages
                    $allMessages = $supabase->select('messages', '*', [
                        'conversation_id' => $conversation['id'],
                        'is_read' => false
                    ]);
                    $unread_count = 0;
                    foreach ($allMessages as $msg) {
                        if ($msg['sender_id'] != $userId || $msg['sender_type'] != $userType) {
                            $unread_count++;
                        }
                    }
                    
                    // Get last message
                    $allMsgs = $supabase->select('messages', '*', ['conversation_id' => $conversation['id']]);
                    $lastMsg = null;
                    if (!empty($allMsgs)) {
                        usort($allMsgs, function($a, $b) {
                            return strcmp($b['created_at'], $a['created_at']);
                        });
                        $lastMsg = $allMsgs[0];
                    }
                    
                    $conversation['unread_count'] = $unread_count;
                    $conversation['last_message'] = $lastMsg ? $lastMsg['message'] : null;
                    $conversation['last_message_time'] = $lastMsg ? $lastMsg['created_at'] : null;
                    $conversations[] = $conversation;
                }
            }
            
            // Sort by last message time
            usort($conversations, function($a, $b) {
                if (!$a['last_message_time'] && !$b['last_message_time']) return 0;
                if (!$a['last_message_time']) return 1;
                if (!$b['last_message_time']) return -1;
                return strcmp($b['last_message_time'], $a['last_message_time']);
            });

            echo json_encode(['success' => true, 'conversations' => $conversations]);

        } elseif ($action === 'conversation_messages') {
            $conversationId = (int)($_GET['conversation_id'] ?? 0);
            
            // التحقق من أن المستخدم مشارك في المحادثة
            $participants = $supabase->select('conversation_participants', '*', [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'user_type' => $userType
            ]);

            if (empty($participants)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية لعرض هذه المحادثة']);
                exit;
            }

            // جلب الرسائل
            $messages = $supabase->select('messages', '*', ['conversation_id' => $conversationId]);
            
            // إضافة اسم المرسل
            foreach ($messages as &$msg) {
                if (!isset($msg['sender_name']) || empty($msg['sender_name'])) {
                    if ($msg['sender_type'] === 'parent') {
                        $parents = $supabase->select('parents', 'full_name', ['id' => $msg['sender_id']]);
                        $msg['sender_full_name'] = !empty($parents) ? $parents[0]['full_name'] : 'ولي أمر';
                    } else {
                        $users = $supabase->select('users', 'full_name', ['id' => $msg['sender_id']]);
                        $msg['sender_full_name'] = !empty($users) ? $users[0]['full_name'] : 'مستخدم';
                    }
                } else {
                    $msg['sender_full_name'] = $msg['sender_name'];
                }
            }
            
            // ترتيب الرسائل حسب الوقت
            usort($messages, function($a, $b) {
                return strcmp($a['created_at'], $b['created_at']);
            });

            // تحديث حالة القراءة للرسائل غير المقروءة
            $messagesToUpdate = $supabase->select('messages', 'id', [
                'conversation_id' => $conversationId,
                'is_read' => false
            ]);
            
            foreach ($messagesToUpdate as $msgToUpdate) {
                $msg = $supabase->select('messages', '*', ['id' => $msgToUpdate['id']]);
                if (!empty($msg) && ($msg[0]['sender_id'] != $userId || $msg[0]['sender_type'] != $userType)) {
                    $supabase->update('messages', [
                        'is_read' => true,
                        'read_at' => date('Y-m-d H:i:s')
                    ], ['id' => $msgToUpdate['id']], false);
                }
            }

            // تحديث آخر وقت قراءة
            $supabase->update('conversation_participants', [
                'last_read_at' => date('Y-m-d H:i:s')
            ], [
                'conversation_id' => $conversationId,
                'user_id' => $userId
            ], false);

            echo json_encode(['success' => true, 'messages' => $messages]);

        } elseif ($action === 'conversation_participants') {
            $conversationId = (int)($_GET['conversation_id'] ?? 0);
            
            $participants = $supabase->select('conversation_participants', '*', [
                'conversation_id' => $conversationId,
                'is_active' => true
            ]);
            
            foreach ($participants as &$p) {
                if ($p['user_type'] === 'parent') {
                    $parents = $supabase->select('parents', 'full_name', ['id' => $p['user_id']]);
                    $p['participant_name'] = !empty($parents) ? $parents[0]['full_name'] : 'ولي أمر';
                } else {
                    $users = $supabase->select('users', 'full_name', ['id' => $p['user_id']]);
                    $p['participant_name'] = !empty($users) ? $users[0]['full_name'] : 'مستخدم';
                }
            }

            echo json_encode(['success' => true, 'participants' => $participants]);

        } elseif ($action === 'message_templates') {
            // قوالب الرسائل (للمسؤولين فقط)
            if ($userType !== 'user') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }

            $templates = $supabase->select('message_templates', '*', ['is_active' => true], 'category, title');
            echo json_encode(['success' => true, 'templates' => $templates ?: []]);
        }

    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $action = $data['action'] ?? '';

        // بدء محادثة جديدة
        if ($action === 'start_conversation') {
            $title = trim($data['title'] ?? '');
            $recipientId = (int)($data['recipient_id'] ?? 0);
            $recipientType = $data['recipient_type'] ?? 'user';
            $initialMessage = trim($data['message'] ?? '');

            if (empty($initialMessage)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'يجب إدخال رسالة']);
                exit;
            }

            // البحث عن محادثة موجودة بين نفس الأطراف
            $allConversations = $supabase->select('conversations', '*', ['type' => 'private']);
            $conversationId = null;
            
            foreach ($allConversations as $conv) {
                $participants = $supabase->select('conversation_participants', '*', ['conversation_id' => $conv['id']]);
                $participantIds = array_map(function($p) { 
                    return $p['user_id'] . '_' . $p['user_type']; 
                }, $participants);
                
                if (in_array($userId . '_' . $userType, $participantIds) && 
                    in_array($recipientId . '_' . $recipientType, $participantIds) &&
                    count($participants) == 2) {
                    $conversationId = $conv['id'];
                    break;
                }
            }

            if (!$conversationId) {
                // إنشاء محادثة جديدة
                $conversation = $supabase->insert('conversations', [
                    'title' => $title,
                    'type' => 'private'
                ]);

                $conversationId = $conversation[0]['id'];

                // إضافة المشاركين
                $supabase->insert('conversation_participants', [
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'role' => 'owner'
                ], false);

                $supabase->insert('conversation_participants', [
                    'conversation_id' => $conversationId,
                    'user_id' => $recipientId,
                    'user_type' => $recipientType,
                    'role' => 'participant'
                ], false);
            }

            // إرسال الرسالة الأولى
            $senderName = $userType === 'parent' ? $_SESSION['parent_name'] : $_SESSION['full_name'];
            
            $message = $supabase->insert('messages', [
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'sender_type' => $userType,
                'sender_name' => $senderName,
                'message' => $initialMessage,
                'message_type' => 'text'
            ]);

            // إشعار المستلم
            if ($recipientType === 'user') {
                $supabase->insert('notifications', [
                    'user_id' => $recipientId,
                    'user_type' => 'user',
                    'type' => 'new_message',
                    'title' => 'رسالة جديدة',
                    'message' => 'رسالة جديدة من ' . $senderName,
                    'link' => '/messages?conversation=' . $conversationId,
                    'priority' => 'normal'
                ], false);
            }

            echo json_encode([
                'success' => true,
                'message' => 'تم إرسال الرسالة بنجاح',
                'conversation_id' => $conversationId
            ]);

        } elseif ($action === 'send_message') {
            $conversationId = (int)($data['conversation_id'] ?? 0);
            $messageText = trim($data['message'] ?? '');

            if (empty($messageText)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'يجب إدخال رسالة']);
                exit;
            }

            // التحقق من المشاركة
            $participant = $supabase->query("
                SELECT * FROM conversation_participants 
                WHERE conversation_id = $conversationId AND user_id = $userId AND user_type = '$userType'
            ");

            if (empty($participant)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }

            $senderName = $userType === 'parent' ? $_SESSION['parent_name'] : $_SESSION['full_name'];

            // إرسال الرسالة
            $message = $supabase->insert('messages', [
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'sender_type' => $userType,
                'sender_name' => $senderName,
                'message' => $messageText,
                'message_type' => 'text'
            ]);

            // تحديث وقت آخر تحديث للمحادثة
            $supabase->update('conversations', [
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $conversationId], false);

            // إشعار المشاركين الآخرين
            $otherParticipants = $supabase->query("
                SELECT * FROM conversation_participants 
                WHERE conversation_id = $conversationId AND NOT (user_id = $userId AND user_type = '$userType')
            ");

            foreach ($otherParticipants as $p) {
                if ($p['user_type'] === 'user') {
                    $supabase->insert('notifications', [
                        'user_id' => $p['user_id'],
                        'user_type' => 'user',
                        'type' => 'new_message',
                        'title' => 'رسالة جديدة',
                        'message' => 'رسالة جديدة من ' . $senderName,
                        'link' => '/messages?conversation=' . $conversationId
                    ], false);
                } else {
                    // إشعار بالإيميل لولي الأمر
                    $parent = $supabase->select('parents', '*', ['id' => $p['user_id']]);
                    if (!empty($parent) && !empty($parent[0]['email'])) {
                        try {
                            sendEmailNotification(
                                $parent[0]['email'],
                                $parent[0]['full_name'],
                                'رسالة جديدة من المدرسة',
                                "لديك رسالة جديدة من {$senderName}. يرجى تسجيل الدخول للاطلاع عليها."
                            );
                        } catch (Exception $e) {
                            error_log("Failed to send email: " . $e->getMessage());
                        }
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'تم إرسال الرسالة بنجاح',
                'message_id' => $message[0]['id']
            ]);

        } elseif ($action === 'mark_as_read') {
            $conversationId = (int)($data['conversation_id'] ?? 0);
            
            $messagesToUpdate = $supabase->select('messages', 'id', [
                'conversation_id' => $conversationId,
                'is_read' => false
            ]);
            
            foreach ($messagesToUpdate as $msgToUpdate) {
                $msg = $supabase->select('messages', '*', ['id' => $msgToUpdate['id']]);
                if (!empty($msg) && ($msg[0]['sender_id'] != $userId || $msg[0]['sender_type'] != $userType)) {
                    $supabase->update('messages', [
                        'is_read' => true,
                        'read_at' => date('Y-m-d H:i:s')
                    ], ['id' => $msgToUpdate['id']], false);
                }
            }

            echo json_encode(['success' => true, 'message' => 'تم تحديث حالة القراءة']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'الطريقة غير مسموح بها']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Conversations error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في الخادم']);
}

