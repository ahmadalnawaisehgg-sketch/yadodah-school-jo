<?php
/**
 * نظام الحضور والغياب
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
            $date = $_GET['date'] ?? date('Y-m-d');
            $gradeId = $_GET['grade_id'] ?? null;
            $sectionId = $_GET['section_id'] ?? null;
            $studentId = $_GET['student_id'] ?? null;
            
            $conditions = [];
            if ($date) $conditions['date'] = $date;
            if ($gradeId) $conditions['grade_id'] = intval($gradeId);
            if ($sectionId) $conditions['section_id'] = intval($sectionId);
            if ($studentId) $conditions['student_id'] = intval($studentId);
            
            $attendance = $supabase->select('attendance', '*', $conditions, ['order' => 'date.desc']);
            echo json_encode(['success' => true, 'data' => $attendance ?: []]);
            
        } elseif ($action === 'statistics') {
            $studentId = intval($_GET['student_id'] ?? 0);
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            if (!$studentId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'معرف الطالب مطلوب']);
                exit;
            }
            
            $allRecords = $supabase->select('attendance', '*', [
                'student_id' => $studentId,
                'date' => ['gte' => $startDate, 'lte' => $endDate]
            ]);
            
            $totalCount = count($allRecords);
            $presentCount = count(array_filter($allRecords, fn($r) => $r['status'] === 'present'));
            $absentCount = count(array_filter($allRecords, fn($r) => $r['status'] === 'absent'));
            
            $percentage = $totalCount > 0 ? ($presentCount / $totalCount) * 100 : 0;
            
            echo json_encode([
                'success' => true,
                'statistics' => [
                    'total' => $totalCount,
                    'present' => $presentCount,
                    'absent' => $absentCount,
                    'percentage' => round($percentage, 2)
                ]
            ]);
        }
        
    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $action = $data['action'] ?? 'record';
        
        if ($action === 'record') {
            $sanitized = sanitizeArray($data);
            
            $errors = validateInput($sanitized, [
                'student_id' => ['required' => true, 'type' => 'integer'],
                'student_name' => ['required' => true, 'type' => 'string'],
                'date' => ['required' => true, 'type' => 'string'],
                'status' => ['required' => true, 'type' => 'string']
            ]);
            
            if ($errors) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            $attendanceData = [
                'student_id' => $sanitized['student_id'],
                'student_name' => $sanitized['student_name'],
                'grade_id' => $sanitized['grade_id'] ?? null,
                'section_id' => $sanitized['section_id'] ?? null,
                'date' => $sanitized['date'],
                'status' => $sanitized['status'],
                'notes' => $sanitized['notes'] ?? null,
                'recorded_by' => $_SESSION['user_id']
            ];
            
            // التحقق من وجود سجل سابق لنفس التاريخ
            $existing = $supabase->select('attendance', '*', [
                'student_id' => $attendanceData['student_id'],
                'date' => $attendanceData['date']
            ]);
            
            if (!empty($existing)) {
                // تحديث السجل الموجود
                $result = $supabase->update('attendance', ['id' => $existing[0]['id']], $attendanceData);
            } else {
                // إضافة سجل جديد
                $result = $supabase->insert('attendance', $attendanceData);
            }
            
            // إرسال إشعار لولي الأمر في حالة الغياب
            if ($sanitized['status'] === 'absent') {
                $student = $supabase->select('students', '*', ['id' => $sanitized['student_id']]);
                if (!empty($student) && !empty($student[0]['guardian_email'])) {
                    sendEmail(
                        $student[0]['guardian_email'],
                        'إشعار غياب الطالب',
                        "عزيزي ولي الأمر،\n\nنود إبلاغكم بأن الطالب/ة {$sanitized['student_name']} لم يحضر اليوم بتاريخ {$sanitized['date']}.\n\nيرجى التواصل مع المدرسة للاستفسار.\n\nمع تحيات إدارة المدرسة"
                    );
                }
            }
            
            logActivity('record_attendance', 'attendance', null, "تسجيل حضور للطالب {$sanitized['student_name']}");
            
            echo json_encode(['success' => true, 'message' => 'تم تسجيل الحضور بنجاح']);
            
        } elseif ($action === 'bulk_record') {
            $records = $data['records'] ?? [];
            
            if (empty($records)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'لا توجد بيانات للتسجيل']);
                exit;
            }
            
            $successCount = 0;
            foreach ($records as $record) {
                $record['recorded_by'] = $_SESSION['user_id'];
                
                $existing = $supabase->select('attendance', '*', [
                    'student_id' => $record['student_id'],
                    'date' => $record['date']
                ]);
                
                if (!empty($existing)) {
                    $supabase->update('attendance', ['id' => $existing[0]['id']], $record);
                } else {
                    $supabase->insert('attendance', $record);
                }
                
                $successCount++;
            }
            
            logActivity('bulk_record_attendance', 'attendance', null, "تسجيل حضور جماعي لـ $successCount طالب");
            
            echo json_encode(['success' => true, 'message' => "تم تسجيل الحضور لـ $successCount طالب"]);
        }
    }
    
} catch (Exception $e) {
    error_log('Attendance error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في النظام']);
}
