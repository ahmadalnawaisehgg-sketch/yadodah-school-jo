<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $sanitized = sanitizeArray($data);
        
        $errors = validateInput($sanitized, [
            'student_id' => ['required' => false, 'type' => 'int'],
            'student_email' => ['required' => true, 'type' => 'email', 'message' => 'البريد الإلكتروني مطلوب'],
            'notification_type' => ['required' => true, 'type' => 'string', 'maxLength' => 100],
            'subject' => ['required' => true, 'type' => 'string', 'maxLength' => 255],
            'message' => ['required' => true, 'type' => 'string']
        ]);
        
        if ($errors) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
        
        $emailData = [
            'student_id' => $sanitized['student_id'] ?? null,
            'student_email' => $sanitized['student_email'],
            'notification_type' => $sanitized['notification_type'],
            'subject' => $sanitized['subject'],
            'message' => $sanitized['message'],
            'status' => 'pending'
        ];
        
        $result = $supabase->insert('email_notifications', $emailData, false);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة الإشعار إلى قائمة الانتظار',
            'data' => $result
        ]);
        
    } elseif ($method === 'GET') {
        $notifications = $supabase->select('email_notifications', '*', [], 'created_at.desc', 50);
        echo json_encode($notifications ?: []);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => getenv('APP_ENV') === 'production' ? 'حدث خطأ في الخادم' : $e->getMessage()
    ]);
}

