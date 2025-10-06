<?php
/**
 * نظام الشكاوى والاقتراحات
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        requireAuth();
        
        $status = $_GET['status'] ?? null;
        $type = $_GET['type'] ?? null;
        $priority = $_GET['priority'] ?? null;
        
        $query = "SELECT * FROM complaints_suggestions WHERE 1=1";
        
        if ($status) {
            $query .= " AND status = '$status'";
        }
        if ($type) {
            $query .= " AND type = '$type'";
        }
        if ($priority) {
            $query .= " AND priority = '$priority'";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $items = $supabase->query($query);
        
        echo json_encode(['success' => true, 'data' => $items ?: []]);
        
    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $action = $data['action'] ?? 'submit';
        
        if ($action === 'submit') {
            $sanitized = sanitizeArray($data);
            
            $errors = validateInput($sanitized, [
                'type' => ['required' => true, 'type' => 'string'],
                'title' => ['required' => true, 'type' => 'string', 'maxLength' => 255],
                'description' => ['required' => true, 'type' => 'string']
            ]);
            
            if ($errors) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            $complaintData = [
                'type' => $sanitized['type'],
                'category' => $sanitized['category'] ?? null,
                'title' => $sanitized['title'],
                'description' => $sanitized['description'],
                'priority' => $sanitized['priority'] ?? 'medium',
                'status' => 'pending',
                'submitted_by_type' => $sanitized['submitted_by_type'] ?? 'parent',
                'submitted_by_id' => $sanitized['submitted_by_id'] ?? ($_SESSION['parent_id'] ?? $_SESSION['user_id'] ?? null),
                'submitted_by_name' => $sanitized['submitted_by_name'] ?? null,
                'submitted_by_email' => $sanitized['submitted_by_email'] ?? null,
                'submitted_by_phone' => $sanitized['submitted_by_phone'] ?? null,
                'is_anonymous' => $sanitized['is_anonymous'] ?? false,
                'attachments' => $sanitized['attachments'] ?? null
            ];
            
            $result = $supabase->insert('complaints_suggestions', $complaintData);
            
            echo json_encode(['success' => true, 'message' => 'تم إرسال ' . ($sanitized['type'] === 'complaint' ? 'الشكوى' : 'الاقتراح') . ' بنجاح']);
            
        } elseif ($action === 'respond') {
            requireAuth();
            
            $id = intval($data['id'] ?? 0);
            $response = $data['response'] ?? '';
            
            if (!$id || empty($response)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'البيانات غير مكتملة']);
                exit;
            }
            
            $updateData = [
                'response' => $response,
                'response_date' => date('Y-m-d H:i:s'),
                'status' => 'responded',
                'assigned_to' => $_SESSION['user_id']
            ];
            
            $result = $supabase->update('complaints_suggestions', ['id' => $id], $updateData);
            
            logActivity('respond_complaint', 'complaints_suggestions', $id, 'الرد على شكوى/اقتراح');
            
            echo json_encode(['success' => true, 'message' => 'تم إرسال الرد بنجاح']);
            
        } elseif ($action === 'resolve') {
            requireAuth();
            
            $id = intval($data['id'] ?? 0);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'المعرف مطلوب']);
                exit;
            }
            
            $updateData = [
                'status' => 'resolved',
                'resolved_by' => $_SESSION['user_id'],
                'resolved_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $supabase->update('complaints_suggestions', ['id' => $id], $updateData);
            
            logActivity('resolve_complaint', 'complaints_suggestions', $id, 'إغلاق شكوى/اقتراح');
            
            echo json_encode(['success' => true, 'message' => 'تم إغلاق الشكوى/الاقتراح بنجاح']);
        }
    }
    
} catch (Exception $e) {
    error_log('Complaints error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في النظام']);
}
