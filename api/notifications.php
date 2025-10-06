<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    $userId = requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'all';
            
            if ($action === 'all') {
                $notifications = $supabase->select('notifications', '*', ['user_id' => $userId], 'created_at.desc');
                echo json_encode($notifications ?: []);
            } elseif ($action === 'unread') {
                $notifications = $supabase->select('notifications', '*', ['user_id' => $userId, 'is_read' => 'false'], 'created_at.desc');
                echo json_encode($notifications ?: []);
            } elseif ($action === 'unread_count') {
                $notifications = $supabase->select('notifications', 'id', ['user_id' => $userId, 'is_read' => 'false']);
                echo json_encode(['count' => count($notifications)]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'create';
            
            if ($action === 'create') {
                $notificationData = [
                    'user_id' => (int)$data['user_id'],
                    'type' => sanitizeInput($data['type']),
                    'title' => sanitizeInput($data['title']),
                    'message' => sanitizeInput($data['message']),
                    'link' => sanitizeInput($data['link'] ?? ''),
                    'icon' => sanitizeInput($data['icon'] ?? 'bell'),
                    'priority' => $data['priority'] ?? 'normal'
                ];
                
                $supabase->insert('notifications', $notificationData, false);
                echo json_encode(['success' => true, 'message' => 'تم إنشاء الإشعار']);
            } elseif ($action === 'mark_read') {
                $supabase->update('notifications', ['is_read' => true, 'read_at' => date('Y-m-d H:i:s')], ['id' => $data['id']], false);
                echo json_encode(['success' => true]);
            } elseif ($action === 'mark_all_read') {
                $supabase->update('notifications', ['is_read' => true, 'read_at' => date('Y-m-d H:i:s')], ['user_id' => $userId, 'is_read' => 'false'], false);
                echo json_encode(['success' => true, 'message' => 'تم تعليم جميع الإشعارات كمقروءة']);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)$data['id'];
            
            $supabase->delete('notifications', ['id' => $id, 'user_id' => $userId]);
            echo json_encode(['success' => true, 'message' => 'تم حذف الإشعار']);
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

