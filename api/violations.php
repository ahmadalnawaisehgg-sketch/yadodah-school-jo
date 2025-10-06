<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/email_service.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $violations = $supabase->select('violations', '*', [], 'created_at.desc');
            echo json_encode($violations ?: []);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sanitized = sanitizeArray($data);
            
            $errors = validateInput($sanitized, [
                'student_name' => ['required' => true, 'type' => 'string', 'maxLength' => 255, 'message' => 'اسم الطالب مطلوب'],
                'student_id' => ['required' => false, 'type' => 'int'],
                'student_grade' => ['required' => false, 'type' => 'string', 'maxLength' => 255],
                'type' => ['required' => false, 'type' => 'string', 'maxLength' => 255],
                'severity' => ['required' => false, 'type' => 'string', 'maxLength' => 50],
                'date' => ['required' => false, 'type' => 'date'],
                'description' => ['required' => false, 'type' => 'string'],
                'action_taken' => ['required' => false, 'type' => 'string'],
                'reporter_email' => ['required' => false, 'type' => 'email', 'maxLength' => 255]
            ]);
            
            if ($errors) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            $violationData = [
                'student_name' => $sanitized['student_name'],
                'student_id' => !empty($sanitized['student_id']) ? (int)$sanitized['student_id'] : null,
                'student_grade' => $sanitized['student_grade'] ?? '',
                'type' => $sanitized['type'] ?? '',
                'severity' => $sanitized['severity'] ?? 'متوسطة',
                'date' => $sanitized['date'] ?? date('Y-m-d'),
                'description' => $sanitized['description'] ?? '',
                'action_taken' => $sanitized['action_taken'] ?? '',
                'reporter_email' => !empty($sanitized['reporter_email']) ? $sanitized['reporter_email'] : null,
                'guardian_notified' => false
            ];
            
            if (isset($sanitized['id']) && !empty($sanitized['id'])) {
                $result = $supabase->update('violations', $violationData, ['id' => $sanitized['id']], false);
                $message = 'تم تحديث المخالفة بنجاح';
            } else {
                $result = $supabase->insert('violations', $violationData, true);
                $message = 'تم حفظ المخالفة بنجاح';
                
                if ($result && !empty($violationData['student_id'])) {
                    try {
                        $students = $supabase->select('students', 'id,name,guardian,guardian_email', ['id' => $violationData['student_id']]);
                        
                        if (!empty($students) && !empty($students[0]['guardian_email'])) {
                            $student = $students[0];
                            $emailService = new EmailService();
                            
                            $emailResult = $emailService->sendViolationNotification(
                                $violationData,
                                $student['guardian_email'],
                                $student['guardian'] ?? ''
                            );
                            
                            if ($emailResult['success']) {
                                $supabase->update('violations', 
                                    ['guardian_notified' => true], 
                                    ['id' => $result[0]['id']],
                                    false
                                );
                                
                                $supabase->insert('email_notifications', [
                                    'student_id' => $violationData['student_id'],
                                    'recipient_email' => $student['guardian_email'],
                                    'recipient_name' => $student['guardian'] ?? '',
                                    'notification_type' => 'violation',
                                    'subject' => 'إشعار بتسجيل مخالفة للطالب/ة ' . $violationData['student_name'],
                                    'message' => $violationData['description'],
                                    'status' => 'sent',
                                    'sent_at' => date('Y-m-d H:i:s')
                                ], false);
                            } else {
                                $supabase->insert('email_notifications', [
                                    'student_id' => $violationData['student_id'],
                                    'recipient_email' => $student['guardian_email'],
                                    'recipient_name' => $student['guardian'] ?? '',
                                    'notification_type' => 'violation',
                                    'subject' => 'إشعار بتسجيل مخالفة للطالب/ة ' . $violationData['student_name'],
                                    'message' => $violationData['description'],
                                    'status' => 'failed',
                                    'error_message' => $emailResult['error'] ?? 'Unknown error'
                                ], false);
                            }
                        }
                    } catch (Exception $emailError) {
                        error_log("Violation notification error: " . $emailError->getMessage());
                    }
                }
                
                if ($result && !empty($violationData['reporter_email'])) {
                    try {
                        $emailService = new EmailService();
                        $reporterEmailResult = $emailService->sendReporterConfirmation(
                            $violationData,
                            $violationData['reporter_email']
                        );
                        
                        if ($reporterEmailResult['success']) {
                            $supabase->insert('email_notifications', [
                                'student_id' => $violationData['student_id'],
                                'recipient_email' => $violationData['reporter_email'],
                                'recipient_name' => 'المُبلّغ',
                                'notification_type' => 'reporter_confirmation',
                                'subject' => 'تأكيد تسجيل المخالفة - نظام الإرشاد المدرسي',
                                'message' => 'تم تسجيل المخالفة للطالب/ة ' . $violationData['student_name'],
                                'status' => 'sent',
                                'sent_at' => date('Y-m-d H:i:s')
                            ], false);
                        } else {
                            $supabase->insert('email_notifications', [
                                'student_id' => $violationData['student_id'],
                                'recipient_email' => $violationData['reporter_email'],
                                'recipient_name' => 'المُبلّغ',
                                'notification_type' => 'reporter_confirmation',
                                'subject' => 'تأكيد تسجيل المخالفة - نظام الإرشاد المدرسي',
                                'message' => 'تم تسجيل المخالفة للطالب/ة ' . $violationData['student_name'],
                                'status' => 'failed',
                                'error_message' => $reporterEmailResult['error'] ?? 'Unknown error'
                            ], false);
                        }
                    } catch (Exception $reporterEmailError) {
                        error_log("Reporter notification error: " . $reporterEmailError->getMessage());
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
            
            $supabase->delete('violations', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف المخالفة بنجاح']);
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

