<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    $userId = requireAuth();
    
    $currentUser = $supabase->select('users', 'role', ['id' => $userId]);
    if (!$currentUser || $currentUser[0]['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'غير مصرح لك بالوصول']);
        exit;
    }
    
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $users = $supabase->select('users', 'id,username,full_name,email,phone,role,is_active,last_login,created_at', [], 'created_at.desc');
            echo json_encode($users ?: []);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $userData = [
                'username' => $sanitized['username'],
                'full_name' => $sanitized['full_name'] ?? '',
                'email' => $sanitized['email'] ?? null,
                'phone' => $sanitized['phone'] ?? '',
                'role' => $sanitized['role'] ?? 'counselor'
            ];
            
            if (isset($sanitized['password']) && !empty($sanitized['password'])) {
                $userData['password_hash'] = password_hash($sanitized['password'], PASSWORD_DEFAULT);
            }
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                if (empty($sanitized['password'])) {
                    unset($userData['password_hash']);
                }
                $supabase->update('users', $userData, ['id' => $sanitized['id']], false);
                echo json_encode(['success' => true, 'message' => 'تم تحديث المستخدم بنجاح']);
            } else {
                if (!isset($userData['password_hash'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'كلمة المرور مطلوبة للمستخدم الجديد']);
                    exit;
                }
                $supabase->insert('users', $userData, false);
                echo json_encode(['success' => true, 'message' => 'تم إضافة المستخدم بنجاح']);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'معرف غير صالح']);
                exit;
            }
            
            if ($id === $userId) {
                http_response_code(400);
                echo json_encode(['error' => 'لا يمكنك حذف حسابك الخاص']);
                exit;
            }
            
            $supabase->delete('users', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف المستخدم بنجاح']);
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

