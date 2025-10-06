<?php
/**
 * نظام الشات بين المعلم وولي الأمر
 */

require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/middleware.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'conversations';
        
        if ($action === 'conversations') {
            $userType = $_SESSION['user_type'] ?? 'staff';
            
            if ($userType === 'parent') {
                $parentId = $_SESSION['parent_id'] ?? 0;
                
                $conversations = $supabase->select('conversations', '*', ['parent_id' => $parentId], ['order' => 'last_message_at.desc']);
                
                foreach ($conversations as &$conv) {
                    $teacher = $supabase->select('teachers', 'name,subject', ['id' => $conv['teacher_id']]);
                    $conv['teacher_name'] = $teacher[0]['name'] ?? '';
                    $conv['teacher_subject'] = $teacher[0]['subject'] ?? '';
                    
                    $student = $supabase->select('students', 'name', ['id' => $conv['student_id']]);
                    $conv['student_name'] = $student[0]['name'] ?? '';
                    
                    $unreadMessages = $supabase->select('chat_messages', '*', [
                        'conversation_id' => $conv['id'],
                        'sender_type' => 'teacher',
                        'is_read' => false
                    ]);
                    $conv['unread_count'] = count($unreadMessages);
                }
                
            } else {
                $userId = $_SESSION['user_id'] ?? 0;
                
                $teacher = $supabase->select('teachers', '*', ['user_id' => $userId]);
                if (empty($teacher)) {
                    echo json_encode(['success' => true, 'conversations' => []]);
                    exit;
                }
                $teacherId = $teacher[0]['id'];
                
                $conversations = $supabase->select('conversations', '*', ['teacher_id' => $teacherId], ['order' => 'last_message_at.desc']);
                
                foreach ($conversations as &$conv) {
                    $parent = $supabase->select('parents', 'full_name', ['id' => $conv['parent_id']]);
                    $conv['parent_name'] = $parent[0]['full_name'] ?? '';
                    
                    $student = $supabase->select('students', 'name', ['id' => $conv['student_id']]);
                    $conv['student_name'] = $student[0]['name'] ?? '';
                    
                    $unreadMessages = $supabase->select('chat_messages', '*', [
                        'conversation_id' => $conv['id'],
                        'sender_type' => 'parent',
                        'is_read' => false
                    ]);
                    $conv['unread_count'] = count($unreadMessages);
                }
            }
            
            echo json_encode(['success' => true, 'conversations' => $conversations ?: []]);
            
        } elseif ($action === 'messages') {
            $conversationId = intval($_GET['conversation_id'] ?? 0);
            
            if (!$conversationId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'معرف المحادثة مطلوب']);
                exit;
            }
            
            $messages = $supabase->select('chat_messages', '*', [
                'conversation_id' => $conversationId
            ], ['order' => 'created_at.asc']);
            
            $userType = $_SESSION['user_type'] ?? 'staff';
            $senderType = $userType === 'parent' ? 'teacher' : 'parent';
            
            $unreadMessages = $supabase->select('chat_messages', '*', [
                'conversation_id' => $conversationId,
                'sender_type' => $senderType,
                'is_read' => false
            ]);
            
            foreach ($unreadMessages as $msg) {
                $supabase->update('chat_messages', [
                    'is_read' => true,
                    'read_at' => date('Y-m-d H:i:s')
                ], ['id' => $msg['id']], false);
            }
            
            echo json_encode(['success' => true, 'messages' => $messages ?: []]);
        }
        
    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $action = $data['action'] ?? 'send_message';
        
        if ($action === 'start_conversation') {
            $teacherId = intval($data['teacher_id'] ?? 0);
            $parentId = $_SESSION['parent_id'] ?? 0;
            $studentId = intval($data['student_id'] ?? 0);
            $subject = $data['subject'] ?? 'محادثة عامة';
            
            if (!$teacherId || !$parentId || !$studentId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'البيانات غير مكتملة']);
                exit;
            }
            
            $existing = $supabase->select('conversations', '*', [
                'teacher_id' => $teacherId,
                'parent_id' => $parentId,
                'student_id' => $studentId
            ]);
            
            if (!empty($existing)) {
                echo json_encode(['success' => true, 'conversation_id' => $existing[0]['id'], 'message' => 'المحادثة موجودة مسبقاً']);
                exit;
            }
            
            $conversationData = [
                'teacher_id' => $teacherId,
                'parent_id' => $parentId,
                'student_id' => $studentId,
                'subject' => $subject,
                'status' => 'active',
                'last_message_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $supabase->insert('conversations', $conversationData);
            $conversationId = $result[0]['id'] ?? null;
            
            echo json_encode(['success' => true, 'conversation_id' => $conversationId, 'message' => 'تم بدء المحادثة']);
            
        } elseif ($action === 'send_message') {
            $conversationId = intval($data['conversation_id'] ?? 0);
            $message = trim($data['message'] ?? '');
            
            if (!$conversationId || empty($message)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'البيانات غير مكتملة']);
                exit;
            }
            
            $userType = $_SESSION['user_type'] ?? 'staff';
            $senderType = $userType === 'parent' ? 'parent' : 'teacher';
            
            if ($senderType === 'parent') {
                $senderId = $_SESSION['parent_id'] ?? 0;
                $parent = $supabase->select('parents', '*', ['id' => $senderId]);
                $senderName = $parent[0]['full_name'] ?? 'ولي أمر';
            } else {
                $userId = $_SESSION['user_id'] ?? 0;
                $teacher = $supabase->select('teachers', '*', ['user_id' => $userId]);
                $senderId = $teacher[0]['id'] ?? 0;
                $senderName = $teacher[0]['name'] ?? 'معلم';
            }
            
            $messageData = [
                'conversation_id' => $conversationId,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'message' => $message,
                'attachment_url' => $data['attachment_url'] ?? null,
                'attachment_name' => $data['attachment_name'] ?? null
            ];
            
            $result = $supabase->insert('chat_messages', $messageData);
            
            $supabase->update('conversations', ['id' => $conversationId], [
                'last_message_at' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode(['success' => true, 'message' => 'تم إرسال الرسالة']);
        }
    }
    
} catch (Exception $e) {
    error_log('Chat error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في النظام']);
}
