<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $guidance = $supabase->select('guidance_sessions', '*');
            echo json_encode($guidance ?: []);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $guidanceData = [
                'topic' => $sanitized['topic'] ?? '',
                'date' => $sanitized['date'] ?? date('Y-m-d'),
                'grade' => $sanitized['grade'] ?? '',
                'notes' => $sanitized['notes'] ?? ''
            ];
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                $supabase->update('guidance_sessions', $guidanceData, ['id' => $sanitized['id']], false);
            } else {
                $supabase->insert('guidance_sessions', $guidanceData, false);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ البيانات بنجاح']);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'معرف غير صالح']);
                exit;
            }
            
            $supabase->delete('guidance_sessions', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم الحذف بنجاح']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'الطريقة غير مسموح بها']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => getenv('APP_ENV') === 'production' ? 'حدث خطأ في الخادم' : $e->getMessage()
    ]);
}

