<?php
/**
 * API بوابة أولياء الأمور
 * عرض معلومات الطلاب، المخالفات، المواعيد، إلخ
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

// التحقق من أن المستخدم هو ولي أمر
if (!isset($_SESSION['parent_id']) || $_SESSION['user_type'] !== 'parent') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول كولي أمر', 'require_login' => true]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$parentId = $_SESSION['parent_id'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        // جلب معلومات الطلاب المرتبطين
        if ($action === 'my_students') {
            $links = $supabase->select('parent_student_link', '*', ['parent_id' => $parentId]);
            $students = [];
            
            foreach ($links as $link) {
                $studentData = $supabase->select('students', '*', ['id' => $link['student_id']]);
                if (!empty($studentData)) {
                    $student = $studentData[0];
                    $student['relation'] = $link['relation'] ?? 'ولي أمر';
                    $student['can_view_violations'] = $link['can_view_violations'] ?? true;
                    $student['can_view_meetings'] = $link['can_view_meetings'] ?? true;
                    $student['can_view_progress'] = $link['can_view_progress'] ?? true;
                    $students[] = $student;
                }
            }

            echo json_encode(['success' => true, 'students' => $students]);

        } elseif ($action === 'student_violations') {
            $studentId = (int)($_GET['student_id'] ?? 0);
            
            // التحقق من أن الطالب مرتبط بولي الأمر
            $links = $supabase->select('parent_student_link', '*', [
                'parent_id' => $parentId,
                'student_id' => $studentId
            ]);

            if (empty($links)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية لعرض هذه البيانات']);
                exit;
            }

            $link = $links[0];
            if (isset($link['can_view_violations']) && !$link['can_view_violations']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية لعرض المخالفات']);
                exit;
            }

            $violations = $supabase->select('violations', '*', ['student_id' => $studentId]);
            echo json_encode(['success' => true, 'violations' => $violations ?: []]);

        } elseif ($action === 'student_meetings') {
            $studentId = (int)($_GET['student_id'] ?? 0);
            
            // التحقق من الصلاحية
            $links = $supabase->select('parent_student_link', '*', [
                'parent_id' => $parentId,
                'student_id' => $studentId
            ]);

            if (empty($links)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية لعرض هذه البيانات']);
                exit;
            }

            $link = $links[0];
            if (isset($link['can_view_meetings']) && !$link['can_view_meetings']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية لعرض المواعيد']);
                exit;
            }

            $meetings = $supabase->select('parent_meetings', '*', ['student_id' => $studentId]);
            echo json_encode(['success' => true, 'meetings' => $meetings ?: []]);

        } elseif ($action === 'student_guidance') {
            $studentId = (int)($_GET['student_id'] ?? 0);
            
            // التحقق من الصلاحية
            $links = $supabase->select('parent_student_link', '*', [
                'parent_id' => $parentId,
                'student_id' => $studentId
            ]);

            if (empty($links)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية لعرض هذه البيانات']);
                exit;
            }

            // جلب جلسات التوجيه
            $attendance = $supabase->select('guidance_attendance', '*', [
                'student_id' => $studentId,
                'attended' => true
            ]);
            
            $guidance = [];
            foreach ($attendance as $att) {
                $sessions = $supabase->select('guidance_sessions', '*', ['id' => $att['session_id']]);
                if (!empty($sessions)) {
                    $guidance[] = $sessions[0];
                }
            }

            echo json_encode(['success' => true, 'guidance_sessions' => $guidance]);

        } elseif ($action === 'student_dashboard') {
            $studentId = (int)($_GET['student_id'] ?? 0);
            
            // التحقق من الصلاحية
            $links = $supabase->select('parent_student_link', '*', [
                'parent_id' => $parentId,
                'student_id' => $studentId
            ]);

            if (empty($links)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية لعرض هذه البيانات']);
                exit;
            }

            // جلب إحصائيات شاملة
            $student = $supabase->select('students', '*', ['id' => $studentId]);
            
            $violations = $supabase->select('violations', '*', ['student_id' => $studentId]);
            $meetings = $supabase->select('parent_meetings', '*', ['student_id' => $studentId]);
            $guidance = $supabase->select('guidance_attendance', '*', ['student_id' => $studentId]);
            
            // المواعيد القادمة
            $upcomingMeetings = [];
            $today = date('Y-m-d');
            foreach ($meetings as $meeting) {
                if (isset($meeting['date']) && $meeting['date'] >= $today && 
                    isset($meeting['status']) && $meeting['status'] === 'scheduled') {
                    $upcomingMeetings[] = $meeting;
                }
            }
            usort($upcomingMeetings, function($a, $b) {
                return strcmp($a['date'], $b['date']);
            });
            $upcomingMeetings = array_slice($upcomingMeetings, 0, 5);

            echo json_encode([
                'success' => true,
                'student' => $student[0] ?? null,
                'stats' => [
                    'total_violations' => count($violations),
                    'violations_this_month' => count(array_filter($violations, function($v) {
                        return isset($v['date']) && date('Y-m', strtotime($v['date'])) === date('Y-m');
                    })),
                    'total_meetings' => count($meetings),
                    'total_guidance' => count($guidance)
                ],
                'upcoming_meetings' => $upcomingMeetings
            ]);

        } elseif ($action === 'unread_messages_count') {
            // عدد الرسائل غير المقروءة
            $participants = $supabase->select('conversation_participants', '*', [
                'user_id' => $parentId,
                'user_type' => 'parent'
            ]);
            
            $unreadCount = 0;
            foreach ($participants as $participant) {
                $messages = $supabase->select('messages', '*', [
                    'conversation_id' => $participant['conversation_id'],
                    'is_read' => false
                ]);
                
                foreach ($messages as $msg) {
                    if ($msg['sender_id'] != $parentId || $msg['sender_type'] != 'parent') {
                        $unreadCount++;
                    }
                }
            }

            echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
        }

    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $action = $data['action'] ?? '';

        // تأكيد حضور موعد
        if ($action === 'confirm_meeting') {
            $meetingId = (int)($data['meeting_id'] ?? 0);
            
            // التحقق من أن الموعد يخص هذا ولي الأمر
            $meeting = $supabase->select('parent_meetings', '*', ['id' => $meetingId]);
            
            if (empty($meeting)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'الموعد غير موجود']);
                exit;
            }

            $studentId = $meeting[0]['student_id'];
            $links = $supabase->select('parent_student_link', '*', [
                'parent_id' => $parentId,
                'student_id' => $studentId
            ]);

            if (empty($links)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'غير مصرح لك بهذا الإجراء']);
                exit;
            }

            // تأكيد الحضور
            $updated = $supabase->update('parent_meetings', [
                'attendance_confirmed' => true,
                'confirmed_at' => date('Y-m-d H:i:s')
            ], ['id' => $meetingId]);

            // تسجيل النشاط
            $supabase->insert('activity_log', [
                'user_id' => $parentId,
                'user_type' => 'parent',
                'action_type' => 'confirm_meeting',
                'description' => 'تأكيد حضور موعد مقابلة',
                'record_id' => $meetingId
            ], false);

            echo json_encode(['success' => true, 'message' => 'تم تأكيد الحضور بنجاح']);

        } elseif ($action === 'request_meeting') {
            // طلب موعد مقابلة جديد
            $studentId = (int)($data['student_id'] ?? 0);
            $requestedDate = $data['requested_date'] ?? '';
            $requestedTime = $data['requested_time'] ?? '';
            $topic = trim($data['topic'] ?? '');
            $reason = trim($data['reason'] ?? '');

            // التحقق
            $links = $supabase->select('parent_student_link', '*', [
                'parent_id' => $parentId,
                'student_id' => $studentId
            ]);

            if (empty($links)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'غير مصرح لك بهذا الإجراء']);
                exit;
            }

            if (empty($topic)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'يجب إدخال موضوع المقابلة']);
                exit;
            }

            // إنشاء طلب جديد
            $request = $supabase->insert('meeting_requests', [
                'parent_id' => $parentId,
                'student_id' => $studentId,
                'requested_date' => $requestedDate,
                'requested_time' => $requestedTime,
                'topic' => $topic,
                'reason' => $reason,
                'status' => 'pending'
            ]);

            // إشعار المسؤولين
            $parent = $supabase->select('parents', '*', ['id' => $parentId]);
            $student = $supabase->select('students', '*', ['id' => $studentId]);
            
            $supabase->insert('notifications', [
                'user_id' => 1,
                'user_type' => 'user',
                'type' => 'meeting_request',
                'title' => 'طلب موعد مقابلة جديد',
                'message' => 'ولي الأمر ' . $parent[0]['full_name'] . ' يطلب موعد مقابلة للطالب ' . $student[0]['name'],
                'priority' => 'high',
                'action_required' => true
            ], false);

            echo json_encode(['success' => true, 'message' => 'تم إرسال طلبك بنجاح. سيتم التواصل معك قريباً']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'الطريقة غير مسموح بها']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Parent Portal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في الخادم']);
}

