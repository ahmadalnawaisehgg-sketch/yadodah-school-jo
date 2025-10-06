<?php
/**
 * نظام الدرجات والتقييمات الأكاديمية
 */

require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        if ($action === 'list') {
            $studentId = $_GET['student_id'] ?? null;
            $academicYear = $_GET['academic_year'] ?? null;
            $semester = $_GET['semester'] ?? null;
            $subject = $_GET['subject'] ?? null;
            
            $conditions = [];
            if ($studentId) $conditions['student_id'] = intval($studentId);
            if ($academicYear) $conditions['academic_year'] = $academicYear;
            if ($semester) $conditions['semester'] = $semester;
            if ($subject) $conditions['subject'] = $subject;
            
            $grades = $supabase->select('academic_grades', '*', $conditions, 'created_at.desc');
            echo json_encode(['success' => true, 'data' => $grades ?: []]);
            
        } elseif ($action === 'student_report') {
            $studentId = intval($_GET['student_id'] ?? 0);
            $academicYear = $_GET['academic_year'] ?? date('Y') . '-' . (date('Y') + 1);
            $semester = $_GET['semester'] ?? 'الفصل الأول';
            
            if (!$studentId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'معرف الطالب مطلوب']);
                exit;
            }
            
            $grades = $supabase->select('academic_grades', '*', [
                'student_id' => $studentId,
                'academic_year' => $academicYear,
                'semester' => $semester
            ]);
            
            $average = 0;
            $totalMarks = 0;
            $obtainedMarks = 0;
            
            if (!empty($grades)) {
                foreach ($grades as $grade) {
                    $totalMarks += $grade['total_marks'] ?? 0;
                    $obtainedMarks += $grade['obtained_marks'] ?? 0;
                }
                $average = $totalMarks > 0 ? ($obtainedMarks / $totalMarks) * 100 : 0;
            }
            
            echo json_encode([
                'success' => true,
                'grades' => $grades,
                'summary' => [
                    'total_marks' => $totalMarks,
                    'obtained_marks' => $obtainedMarks,
                    'average' => round($average, 2),
                    'subjects_count' => count($grades)
                ]
            ]);
        }
        
    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $sanitized = sanitizeArray($data);
        
        $errors = validateInput($sanitized, [
            'student_id' => ['required' => true, 'type' => 'integer'],
            'student_name' => ['required' => true, 'type' => 'string'],
            'academic_year' => ['required' => true, 'type' => 'string'],
            'semester' => ['required' => true, 'type' => 'string'],
            'subject' => ['required' => true, 'type' => 'string'],
            'total_marks' => ['required' => true, 'type' => 'number'],
            'obtained_marks' => ['required' => true, 'type' => 'number']
        ]);
        
        if ($errors) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
        
        $percentage = ($sanitized['obtained_marks'] / $sanitized['total_marks']) * 100;
        
        $grade = 'F';
        if ($percentage >= 90) $grade = 'A';
        elseif ($percentage >= 80) $grade = 'B';
        elseif ($percentage >= 70) $grade = 'C';
        elseif ($percentage >= 60) $grade = 'D';
        elseif ($percentage >= 50) $grade = 'E';
        
        $gradeData = [
            'student_id' => $sanitized['student_id'],
            'student_name' => $sanitized['student_name'],
            'grade_id' => $sanitized['grade_id'] ?? null,
            'section_id' => $sanitized['section_id'] ?? null,
            'academic_year' => $sanitized['academic_year'],
            'semester' => $sanitized['semester'],
            'subject' => $sanitized['subject'],
            'exam_type' => $sanitized['exam_type'] ?? 'نهائي',
            'total_marks' => $sanitized['total_marks'],
            'obtained_marks' => $sanitized['obtained_marks'],
            'percentage' => round($percentage, 2),
            'grade' => $grade,
            'teacher_id' => $sanitized['teacher_id'] ?? null,
            'notes' => $sanitized['notes'] ?? null,
            'recorded_by' => $_SESSION['user_id']
        ];
        
        $result = $supabase->insert('academic_grades', $gradeData);
        
        logActivity('add_grade', 'academic_grades', null, "إضافة درجة للطالب {$sanitized['student_name']} في مادة {$sanitized['subject']}");
        
        echo json_encode(['success' => true, 'message' => 'تم إضافة الدرجة بنجاح', 'grade' => $gradeData]);
    }
    
} catch (Exception $e) {
    error_log('Grades error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في النظام']);
}
