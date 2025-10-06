<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/email_service.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $students = $supabase->select('students', '*', [], 'created_at.desc');
        echo json_encode($students ?: []);
        
    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $sanitized = sanitizeArray($data);
        
        $errors = validateInput($sanitized, [
            'name' => ['required' => true, 'type' => 'string', 'maxLength' => 255, 'message' => 'اسم الطالب مطلوب'],
            'national_id' => ['required' => false, 'type' => 'string', 'maxLength' => 20],
            'email' => ['required' => false, 'type' => 'email', 'maxLength' => 255],
            'guardian' => ['required' => false, 'type' => 'string', 'maxLength' => 255],
            'guardian_email' => ['required' => false, 'type' => 'email', 'maxLength' => 255],
            'guardian_phone' => ['required' => false, 'type' => 'string', 'maxLength' => 50],
            'guardian_relation' => ['required' => false, 'type' => 'string', 'maxLength' => 50],
            'grade' => ['required' => false, 'type' => 'string', 'maxLength' => 255],
            'section' => ['required' => false, 'type' => 'string', 'maxLength' => 50],
            'phone' => ['required' => false, 'type' => 'string', 'maxLength' => 50],
            'address' => ['required' => false, 'type' => 'string']
        ]);
        
        if ($errors) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
        
        $studentData = [
            'name' => $sanitized['name'],
            'national_id' => !empty($sanitized['national_id']) ? $sanitized['national_id'] : null,
            'email' => !empty($sanitized['email']) ? $sanitized['email'] : null,
            'guardian' => !empty($sanitized['guardian']) ? $sanitized['guardian'] : null,
            'guardian_email' => !empty($sanitized['guardian_email']) ? $sanitized['guardian_email'] : null,
            'guardian_phone' => !empty($sanitized['guardian_phone']) ? $sanitized['guardian_phone'] : null,
            'guardian_relation' => !empty($sanitized['guardian_relation']) ? $sanitized['guardian_relation'] : null,
            'grade' => !empty($sanitized['grade']) ? $sanitized['grade'] : null,
            'section' => !empty($sanitized['section']) ? $sanitized['section'] : null,
            'phone' => !empty($sanitized['phone']) ? $sanitized['phone'] : null,
            'address' => !empty($sanitized['address']) ? $sanitized['address'] : null
        ];
        
        if (isset($sanitized['id']) && !empty($sanitized['id'])) {
            $result = $supabase->update('students', $studentData, ['id' => $sanitized['id']], false);
            $message = 'تم تحديث الطالب بنجاح';
        } else {
            $result = $supabase->insert('students', $studentData, true);
            $message = 'تم إضافة الطالب بنجاح';
            
            if ($result && !empty($studentData['email'])) {
                try {
                    $emailService = new EmailService();
                    $emailResult = $emailService->sendStudentRegistrationEmail($studentData);
                    
                    if ($emailResult['success']) {
                        $supabase->insert('email_notifications', [
                            'student_id' => $result[0]['id'] ?? null,
                            'recipient_email' => $studentData['email'],
                            'recipient_name' => $studentData['name'],
                            'notification_type' => 'registration',
                            'subject' => 'تم تسجيلك في نظام الإرشاد المدرسي',
                            'message' => "تم تسجيل الطالب/ة {$studentData['name']} بنجاح في النظام",
                            'status' => 'sent',
                            'sent_at' => date('Y-m-d H:i:s')
                        ], false);
                    } else {
                        $supabase->insert('email_notifications', [
                            'student_id' => $result[0]['id'] ?? null,
                            'recipient_email' => $studentData['email'],
                            'recipient_name' => $studentData['name'],
                            'notification_type' => 'registration',
                            'subject' => 'تم تسجيلك في نظام الإرشاد المدرسي',
                            'message' => "تم تسجيل الطالب/ة {$studentData['name']} بنجاح في النظام",
                            'status' => 'failed',
                            'error_message' => $emailResult['error'] ?? 'Unknown error'
                        ], false);
                    }
                } catch (Exception $emailError) {
                    error_log("Student registration email error: " . $emailError->getMessage());
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $result
        ]);
        
    } elseif ($method === 'DELETE') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'معرف غير صالح']);
            exit;
        }
        
        $supabase->delete('students', ['id' => $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف الطالب بنجاح'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => getenv('APP_ENV') === 'production' ? 'حدث خطأ في الخادم' : $e->getMessage()
    ]);
}

