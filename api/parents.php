<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/email_service.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $parents = $supabase->select('parent_meetings', '*');
            echo json_encode($parents ?: []);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $errors = validateInput($sanitized, [
                'student_name' => ['required' => true, 'type' => 'string', 'maxLength' => 255, 'message' => 'اسم الطالب مطلوب'],
                'student_id' => ['required' => false, 'type' => 'int'],
                'parent_name' => ['required' => false, 'type' => 'string', 'maxLength' => 255],
                'parent_email' => ['required' => false, 'type' => 'email', 'maxLength' => 255],
                'date' => ['required' => false, 'type' => 'date'],
                'topic' => ['required' => false, 'type' => 'string']
            ]);
            
            if ($errors) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            $parentData = [
                'student_name' => $sanitized['student_name'],
                'student_id' => !empty($sanitized['student_id']) ? (int)$sanitized['student_id'] : null,
                'parent_name' => $sanitized['parent_name'] ?? '',
                'parent_email' => !empty($sanitized['parent_email']) ? $sanitized['parent_email'] : null,
                'date' => $sanitized['date'] ?? date('Y-m-d'),
                'topic' => $sanitized['topic'] ?? ''
            ];
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                $result = $supabase->update('parent_meetings', $parentData, ['id' => $sanitized['id']], false);
                $message = 'تم تحديث المقابلة بنجاح';
            } else {
                $result = $supabase->insert('parent_meetings', $parentData, false);
                $message = 'تم حفظ المقابلة بنجاح';
                
                if ($result) {
                    try {
                        $emailService = new EmailService();
                        $meetingData = [
                            'student_name' => $parentData['student_name'],
                            'date' => $parentData['date'],
                            'topic' => $parentData['topic']
                        ];
                        
                        $emailSent = false;
                        $recipientEmail = null;
                        $recipientName = '';
                        
                        if (!empty($parentData['student_id'])) {
                            $students = $supabase->select('students', 'id,name,guardian,guardian_email', ['id' => $parentData['student_id']]);
                            
                            if (!empty($students) && !empty($students[0]['guardian_email'])) {
                                $student = $students[0];
                                $recipientEmail = $student['guardian_email'];
                                $recipientName = $student['guardian'] ?? '';
                            }
                        }
                        
                        if (empty($recipientEmail) && !empty($parentData['parent_email'])) {
                            $recipientEmail = $parentData['parent_email'];
                            $recipientName = $parentData['parent_name'] ?? '';
                        }
                        
                        if (!empty($recipientEmail)) {
                            $emailResult = $emailService->sendParentMeetingReminder(
                                $meetingData,
                                $recipientEmail,
                                $recipientName
                            );
                            
                            if ($emailResult['success']) {
                                $supabase->insert('email_notifications', [
                                    'student_id' => $parentData['student_id'] ?? null,
                                    'recipient_email' => $recipientEmail,
                                    'recipient_name' => $recipientName,
                                    'notification_type' => 'parent_meeting',
                                    'subject' => 'تذكير بموعد مقابلة ولي الأمر',
                                    'message' => "مقابلة بخصوص الطالب/ة {$parentData['student_name']} - الموضوع: {$parentData['topic']}",
                                    'status' => 'sent',
                                    'sent_at' => date('Y-m-d H:i:s')
                                ], false);
                            } else {
                                $supabase->insert('email_notifications', [
                                    'student_id' => $parentData['student_id'] ?? null,
                                    'recipient_email' => $recipientEmail,
                                    'recipient_name' => $recipientName,
                                    'notification_type' => 'parent_meeting',
                                    'subject' => 'تذكير بموعد مقابلة ولي الأمر',
                                    'message' => "مقابلة بخصوص الطالب/ة {$parentData['student_name']} - الموضوع: {$parentData['topic']}",
                                    'status' => 'failed',
                                    'error_message' => $emailResult['error'] ?? 'Unknown error'
                                ], false);
                            }
                        }
                    } catch (Exception $emailError) {
                        error_log("Parent meeting notification error: " . $emailError->getMessage());
                    }
                }
            }
            
            echo json_encode(['success' => true, 'message' => $message, 'data' => $result]);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'معرف غير صالح']);
                exit;
            }
            
            $supabase->delete('parent_meetings', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف المقابلة بنجاح']);
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

