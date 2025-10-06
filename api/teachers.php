<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $teachers = $supabase->select('teachers', '*');
            echo json_encode($teachers ?: []);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $errors = validateInput($sanitized, [
                'name' => ['required' => true, 'type' => 'string', 'maxLength' => 255, 'message' => 'اسم المعلم مطلوب']
            ]);
            
            if ($errors) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            $teacherData = [
                'name' => $sanitized['name'],
                'subject' => $sanitized['subject'] ?? '',
                'phone' => $sanitized['phone'] ?? '',
                'notes' => $sanitized['notes'] ?? ''
            ];
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                $supabase->update('teachers', $teacherData, ['id' => $sanitized['id']], false);
            } else {
                $supabase->insert('teachers', $teacherData, false);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ المعلم بنجاح']);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'معرف غير صالح']);
                exit;
            }
            
            $supabase->delete('teachers', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف المعلم بنجاح']);
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

