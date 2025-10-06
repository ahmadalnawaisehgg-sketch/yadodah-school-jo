<?php
/**
 * API إدارة طلبات المواعيد من أولياء الأمور
 * للمسؤولين فقط
 */

require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/email_service.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        if ($action === 'list') {
            // جلب جميع طلبات المواعيد
            $status = $_GET['status'] ?? '';
            
            $where = [];
            if (!empty($status)) {
                $where['status'] = $status;
            }
            
            $requests = $supabase->query("
                SELECT mr.*, 
                    p.full_name as parent_name,
                    p.email as parent_email,
                    p.phone as parent_phone,
                    s.name as student_name,
                    s.grade,
                    s.section
                FROM meeting_requests mr
                INNER JOIN parents p ON mr.parent_id = p.id
                INNER JOIN students s ON mr.student_id = s.id
                " . (!empty($status) ? "WHERE mr.status = '$status'" : "") . "
                ORDER BY mr.created_at DESC
            ");
            
            echo json_encode($requests ?: []);
            
        } elseif ($action === 'stats') {
            // إحصائيات الطلبات
            $pending = $supabase->query("SELECT COUNT(*) as count FROM meeting_requests WHERE status = 'pending'");
            $approved = $supabase->query("SELECT COUNT(*) as count FROM meeting_requests WHERE status = 'approved'");
            $rejected = $supabase->query("SELECT COUNT(*) as count FROM meeting_requests WHERE status = 'rejected'");
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'pending' => $pending[0]['count'] ?? 0,
                    'approved' => $approved[0]['count'] ?? 0,
                    'rejected' => $rejected[0]['count'] ?? 0
                ]
            ]);
        }
        
    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $action = $data['action'] ?? '';
        
        if ($action === 'approve') {
            $requestId = (int)($data['request_id'] ?? 0);
            $approvedDate = $data['approved_date'] ?? '';
            $approvedTime = $data['approved_time'] ?? '';
            $notes = $data['notes'] ?? '';
            
            // جلب معلومات الطلب
            $request = $supabase->select('meeting_requests', '*', ['id' => $requestId]);
            
            if (empty($request)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'الطلب غير موجود']);
                exit;
            }
            
            $req = $request[0];
            
            // جلب معلومات الطالب وولي الأمر
            $student = $supabase->select('students', '*', ['id' => $req['student_id']]);
            $parent = $supabase->select('parents', '*', ['id' => $req['parent_id']]);
            
            // إنشاء موعد مقابلة
            $meeting = $supabase->insert('parent_meetings', [
                'student_id' => $req['student_id'],
                'student_name' => $student[0]['name'],
                'parent_id' => $req['parent_id'],
                'parent_name' => $parent[0]['full_name'],
                'parent_email' => $parent[0]['email'],
                'parent_phone' => $parent[0]['phone'],
                'date' => !empty($approvedDate) ? $approvedDate : $req['requested_date'],
                'time' => !empty($approvedTime) ? $approvedTime : $req['requested_time'],
                'topic' => $req['topic'],
                'notes' => $notes,
                'status' => 'scheduled',
                'counselor_id' => $_SESSION['user_id']
            ]);
            
            // تحديث حالة الطلب
            $supabase->update('meeting_requests', [
                'status' => 'approved',
                'approved_by' => $_SESSION['user_id'],
                'approved_at' => date('Y-m-d H:i:s'),
                'parent_meeting_id' => $meeting[0]['id']
            ], ['id' => $requestId]);
            
            // إرسال إشعار لولي الأمر
            $supabase->insert('notifications', [
                'user_id' => $req['parent_id'],
                'user_type' => 'parent',
                'type' => 'meeting_approved',
                'title' => 'تم الموافقة على طلب الموعد',
                'message' => 'تم الموافقة على طلب موعد المقابلة. الموعد: ' . (!empty($approvedDate) ? $approvedDate : $req['requested_date']) . ' الساعة ' . (!empty($approvedTime) ? $approvedTime : $req['requested_time']),
                'priority' => 'high'
            ], false);
            
            // إرسال إيميل
            if (!empty($parent[0]['email'])) {
                try {
                    sendEmailNotification(
                        $parent[0]['email'],
                        $parent[0]['full_name'],
                        'تمت الموافقة على طلب الموعد',
                        "تم الموافقة على طلبك لموعد مقابلة بخصوص الطالب/ة {$student[0]['name']}.\n\nالموعد: " . (!empty($approvedDate) ? $approvedDate : $req['requested_date']) . "\nالساعة: " . (!empty($approvedTime) ? $approvedTime : $req['requested_time']) . "\n\nنتطلع لرؤيتك."
                    );
                } catch (Exception $e) {
                    error_log("Failed to send email: " . $e->getMessage());
                }
            }
            
            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'approve_meeting_request', 'meeting_requests', $requestId, 
                'الموافقة على طلب موعد لولي الأمر ' . $parent[0]['full_name']);
            
            echo json_encode(['success' => true, 'message' => 'تمت الموافقة على الطلب وإنشاء الموعد']);
            
        } elseif ($action === 'reject') {
            $requestId = (int)($data['request_id'] ?? 0);
            $rejectionReason = trim($data['rejection_reason'] ?? '');
            
            if (empty($rejectionReason)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'يجب إدخال سبب الرفض']);
                exit;
            }
            
            // جلب معلومات الطلب
            $request = $supabase->select('meeting_requests', '*', ['id' => $requestId]);
            
            if (empty($request)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'الطلب غير موجود']);
                exit;
            }
            
            $req = $request[0];
            $parent = $supabase->select('parents', '*', ['id' => $req['parent_id']]);
            
            // تحديث حالة الطلب
            $supabase->update('meeting_requests', [
                'status' => 'rejected',
                'approved_by' => $_SESSION['user_id'],
                'approved_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => $rejectionReason
            ], ['id' => $requestId]);
            
            // إرسال إشعار
            $supabase->insert('notifications', [
                'user_id' => $req['parent_id'],
                'user_type' => 'parent',
                'type' => 'meeting_rejected',
                'title' => 'تم رفض طلب الموعد',
                'message' => 'نعتذر، لم نتمكن من تلبية طلبك. السبب: ' . $rejectionReason,
                'priority' => 'normal'
            ], false);
            
            // إرسال إيميل
            if (!empty($parent[0]['email'])) {
                try {
                    sendEmailNotification(
                        $parent[0]['email'],
                        $parent[0]['full_name'],
                        'بخصوص طلب الموعد',
                        "نعتذر، لم نتمكن من تلبية طلبك لموعد المقابلة.\n\nالسبب: {$rejectionReason}\n\nيرجى التواصل معنا لترتيب موعد بديل."
                    );
                } catch (Exception $e) {
                    error_log("Failed to send email: " . $e->getMessage());
                }
            }
            
            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'reject_meeting_request', 'meeting_requests', $requestId, 
                'رفض طلب موعد لولي الأمر ' . $parent[0]['full_name']);
            
            echo json_encode(['success' => true, 'message' => 'تم رفض الطلب وإرسال إشعار لولي الأمر']);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'الطريقة غير مسموح بها']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Meeting Requests error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $appEnv === 'production' ? 'حدث خطأ في الخادم' : $e->getMessage()
    ]);
}

