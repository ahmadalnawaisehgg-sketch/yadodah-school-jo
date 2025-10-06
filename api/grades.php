<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'all';
            
            if ($action === 'all') {
                $grades = $supabase->select('grades', '*', [], 'level.asc');
                echo json_encode($grades ?: []);
            } elseif ($action === 'with_sections') {
                $grades = $supabase->select('grades', '*', [], 'level.asc');
                foreach ($grades as &$grade) {
                    $sections = $supabase->select('sections', '*', ['grade_id' => $grade['id']], 'name.asc');
                    $grade['sections'] = $sections ?: [];
                }
                echo json_encode($grades ?: []);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $gradeData = [
                'name' => $sanitized['name'],
                'level' => (int)($sanitized['level'] ?? 0),
                'academic_year' => $sanitized['academic_year'] ?? date('Y') . '-' . (date('Y') + 1),
                'notes' => $sanitized['notes'] ?? ''
            ];
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                $supabase->update('grades', $gradeData, ['id' => $sanitized['id']], false);
            } else {
                $supabase->insert('grades', $gradeData, false);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ الصف بنجاح']);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'معرف غير صالح']);
                exit;
            }
            
            $supabase->delete('grades', ['id' => $id]);
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

