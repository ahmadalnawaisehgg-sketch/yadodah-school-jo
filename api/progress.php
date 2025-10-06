<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $studentId = $_GET['student_id'] ?? null;
            
            if (!$studentId) {
                http_response_code(400);
                echo json_encode(['error' => 'معرف الطالب مطلوب']);
                exit;
            }
            
            $progress = $supabase->select('student_progress', '*', ['student_id' => (int)$studentId], 'created_at.desc');
            echo json_encode($progress ?: []);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $progressData = [
                'student_id' => (int)$sanitized['student_id'],
                'academic_year' => $sanitized['academic_year'] ?? date('Y') . '-' . (date('Y') + 1),
                'semester' => $sanitized['semester'] ?? 'الأول',
                'subject' => $sanitized['subject'] ?? '',
                'grade' => (float)($sanitized['grade'] ?? 0),
                'attendance_percentage' => (float)($sanitized['attendance_percentage'] ?? 0),
                'behavior_score' => (float)($sanitized['behavior_score'] ?? 0),
                'notes' => $sanitized['notes'] ?? '',
                'recorded_by' => $_SESSION['user_id']
            ];
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                $supabase->update('student_progress', $progressData, ['id' => $sanitized['id']], false);
            } else {
                $supabase->insert('student_progress', $progressData, false);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ سجل التقدم بنجاح']);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'معرف غير صالح']);
                exit;
            }
            
            $supabase->delete('student_progress', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف السجل بنجاح']);
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

