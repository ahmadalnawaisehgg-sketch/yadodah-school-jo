<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $gradeId = $_GET['grade_id'] ?? null;
            
            $filters = [];
            if ($gradeId) $filters['grade_id'] = (int)$gradeId;
            
            $sections = $supabase->select('sections', '*', $filters, 'name.asc');
            echo json_encode($sections ?: []);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $sectionData = [
                'grade_id' => (int)$sanitized['grade_id'],
                'name' => $sanitized['name'],
                'capacity' => (int)($sanitized['capacity'] ?? 30),
                'classroom' => $sanitized['classroom'] ?? '',
                'notes' => $sanitized['notes'] ?? ''
            ];
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                $supabase->update('sections', $sectionData, ['id' => $sanitized['id']], false);
            } else {
                $supabase->insert('sections', $sectionData, false);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ الشعبة بنجاح']);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'معرف غير صالح']);
                exit;
            }
            
            $supabase->delete('sections', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم الحذف بنجاح']);
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

