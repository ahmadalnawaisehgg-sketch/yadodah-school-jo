<?php
/**
 * التقويم المدرسي
 */

require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        $eventType = $_GET['event_type'] ?? null;
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $query = "SELECT * FROM school_calendar WHERE start_date BETWEEN '$startDate' AND '$endDate'";
        
        if ($eventType) {
            $query .= " AND event_type = '$eventType'";
        }
        
        $query .= " ORDER BY start_date ASC";
        
        $events = $supabase->query($query);
        
        echo json_encode(['success' => true, 'events' => $events ?: []]);
        
    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $sanitized = sanitizeArray($data);
        
        $errors = validateInput($sanitized, [
            'title' => ['required' => true, 'type' => 'string', 'maxLength' => 255],
            'event_type' => ['required' => true, 'type' => 'string'],
            'start_date' => ['required' => true, 'type' => 'string']
        ]);
        
        if ($errors) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
        
        $eventData = [
            'title' => $sanitized['title'],
            'description' => $sanitized['description'] ?? null,
            'event_type' => $sanitized['event_type'],
            'start_date' => $sanitized['start_date'],
            'end_date' => $sanitized['end_date'] ?? $sanitized['start_date'],
            'start_time' => $sanitized['start_time'] ?? null,
            'end_time' => $sanitized['end_time'] ?? null,
            'location' => $sanitized['location'] ?? null,
            'target_grades' => $sanitized['target_grades'] ?? null,
            'target_sections' => $sanitized['target_sections'] ?? null,
            'is_public' => $sanitized['is_public'] ?? true,
            'color' => $sanitized['color'] ?? '#6366f1',
            'reminder_enabled' => $sanitized['reminder_enabled'] ?? true,
            'reminder_days' => $sanitized['reminder_days'] ?? 1,
            'created_by' => $_SESSION['user_id']
        ];
        
        $result = $supabase->insert('school_calendar', $eventData);
        
        logActivity('add_event', 'school_calendar', null, "إضافة حدث: {$sanitized['title']}");
        
        echo json_encode(['success' => true, 'message' => 'تم إضافة الحدث بنجاح']);
        
    } elseif ($method === 'DELETE') {
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'المعرف مطلوب']);
            exit;
        }
        
        requireRole(['admin', 'counselor']);
        
        $result = $supabase->delete('school_calendar', ['id' => $id]);
        
        logActivity('delete_event', 'school_calendar', $id, 'حذف حدث من التقويم');
        
        echo json_encode(['success' => true, 'message' => 'تم حذف الحدث بنجاح']);
    }
    
} catch (Exception $e) {
    error_log('Calendar error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في النظام']);
}
