<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $meetings = $supabase->select('meetings', '*');
            echo json_encode($meetings ?: []);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $meetingData = [
                'meeting_number' => $sanitized['meeting_number'] ?? '',
                'date' => $sanitized['date'] ?? date('Y-m-d'),
                'attendees_count' => $sanitized['attendees_count'] ?? 0,
                'topics' => $sanitized['topics'] ?? ''
            ];
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                $supabase->update('meetings', $meetingData, ['id' => $sanitized['id']], false);
            } else {
                $supabase->insert('meetings', $meetingData, false);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ الاجتماع بنجاح']);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'معرف غير صالح']);
                exit;
            }
            
            $supabase->delete('meetings', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف الاجتماع بنجاح']);
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

