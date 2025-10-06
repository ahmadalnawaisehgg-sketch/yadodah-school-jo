<?php

class EmailService {
    private $resendApiKey;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->resendApiKey = getenv('RESEND_API_KEY');
        $this->fromEmail = getenv('FROM_EMAIL') ?: 'noreply@school.com';
        $this->fromName = getenv('FROM_NAME') ?: 'نظام الإرشاد المدرسي';
    }
    
    public function sendEmail($to, $subject, $htmlContent, $toName = '') {
        if (empty($this->resendApiKey)) {
            error_log('RESEND_API_KEY not configured');
            return [
                'success' => false,
                'error' => 'Email service not configured'
            ];
        }
        
        $emailData = [
            'from' => "{$this->fromName} <{$this->fromEmail}>",
            'to' => empty($toName) ? [$to] : ["{$toName} <{$to}>"],
            'subject' => $subject,
            'html' => $htmlContent
        ];
        
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->resendApiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("Email API Request - To: $to, HTTP Code: $httpCode");
        error_log("Email API Response: $response");
        
        if ($error) {
            error_log("Email sending CURL error: $error");
            return [
                'success' => false,
                'error' => $error
            ];
        }
        
        if ($httpCode >= 400) {
            error_log("Email API error (HTTP $httpCode): $response");
            $decodedResponse = json_decode($response, true);
            $errorMessage = isset($decodedResponse['message']) ? $decodedResponse['message'] : $response;
            return [
                'success' => false,
                'error' => "HTTP $httpCode: $errorMessage",
                'full_response' => $decodedResponse
            ];
        }
        
        error_log("Email sent successfully to: $to");
        return [
            'success' => true,
            'response' => json_decode($response, true)
        ];
    }
    
    public function sendStudentRegistrationEmail($studentData) {
        if (empty($studentData['email'])) {
            return ['success' => false, 'error' => 'No email provided'];
        }
        
        $subject = 'تم تسجيلك في نظام الإرشاد المدرسي';
        $html = $this->getRegistrationEmailTemplate($studentData);
        
        return $this->sendEmail(
            $studentData['email'],
            $subject,
            $html,
            $studentData['name'] ?? ''
        );
    }
    
    public function sendViolationNotification($violationData, $guardianEmail, $guardianName = '') {
        if (empty($guardianEmail)) {
            return ['success' => false, 'error' => 'No guardian email provided'];
        }
        
        $subject = 'إشعار بتسجيل مخالفة للطالب/ة ' . ($violationData['student_name'] ?? '');
        $html = $this->getViolationEmailTemplate($violationData, $guardianName);
        
        return $this->sendEmail($guardianEmail, $subject, $html, $guardianName);
    }
    
    public function sendParentMeetingReminder($meetingData, $parentEmail, $parentName = '') {
        if (empty($parentEmail)) {
            return ['success' => false, 'error' => 'No parent email provided'];
        }
        
        $subject = 'تذكير بموعد مقابلة ولي الأمر';
        $html = $this->getParentMeetingTemplate($meetingData, $parentName);
        
        return $this->sendEmail($parentEmail, $subject, $html, $parentName);
    }
    
    public function sendReporterConfirmation($violationData, $reporterEmail) {
        if (empty($reporterEmail)) {
            return ['success' => false, 'error' => 'No reporter email provided'];
        }
        
        $subject = 'تأكيد تسجيل المخالفة - نظام الإرشاد المدرسي';
        $html = $this->getReporterConfirmationTemplate($violationData);
        
        return $this->sendEmail($reporterEmail, $subject, $html);
    }
    
    private function getRegistrationEmailTemplate($studentData) {
        $name = htmlspecialchars($studentData['name'] ?? 'الطالب/ة');
        $grade = htmlspecialchars($studentData['grade'] ?? 'غير محدد');
        $section = htmlspecialchars($studentData['section'] ?? 'غير محدد');
        
        return "
        <!DOCTYPE html>
        <html lang='ar' dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; direction: rtl; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .info-box { background: #f8f9fa; border-right: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 6px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎓 مرحباً بك في نظام الإرشاد المدرسي</h1>
                </div>
                <div class='content'>
                    <p>عزيزي/عزيزتي <strong>{$name}</strong>،</p>
                    <p>تم تسجيلك بنجاح في نظام الإرشاد المدرسي. نحن سعداء بانضمامك إلينا! 🌟</p>
                    
                    <div class='info-box'>
                        <h3>معلومات التسجيل:</h3>
                        <p><strong>الصف:</strong> {$grade}</p>
                        <p><strong>الشعبة:</strong> {$section}</p>
                        <p><strong>تاريخ التسجيل:</strong> " . date('Y-m-d') . "</p>
                    </div>
                    
                    <p>يمكنك الآن الاستفادة من خدمات الإرشاد والتوجيه المدرسي. إذا كان لديك أي استفسار، لا تتردد في التواصل معنا.</p>
                    
                    <p style='margin-top: 30px;'>مع أطيب التمنيات بالتوفيق والنجاح! 📚</p>
                </div>
                <div class='footer'>
                    <p>نظام الإرشاد المدرسي © 2025</p>
                    <p>هذا البريد تلقائي، يرجى عدم الرد عليه مباشرة</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getViolationEmailTemplate($violationData, $guardianName = '') {
        $studentName = htmlspecialchars($violationData['student_name'] ?? 'الطالب/ة');
        $grade = htmlspecialchars($violationData['student_grade'] ?? 'غير محدد');
        $type = htmlspecialchars($violationData['type'] ?? 'غير محدد');
        $severity = htmlspecialchars($violationData['severity'] ?? 'متوسطة');
        $date = htmlspecialchars($violationData['date'] ?? date('Y-m-d'));
        $description = htmlspecialchars($violationData['description'] ?? 'لا يوجد وصف');
        $guardianName = htmlspecialchars($guardianName ?: 'ولي الأمر');
        
        $severityColor = $severity === 'خطيرة' ? '#dc3545' : ($severity === 'متوسطة' ? '#ffc107' : '#28a745');
        
        return "
        <!DOCTYPE html>
        <html lang='ar' dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; direction: rtl; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .alert-box { background: #fff3cd; border-right: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 6px; }
                .info-box { background: #f8f9fa; border-right: 4px solid {$severityColor}; padding: 15px; margin: 20px 0; border-radius: 6px; }
                .severity { display: inline-block; padding: 5px 15px; background: {$severityColor}; color: white; border-radius: 20px; font-size: 14px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ إشعار بتسجيل مخالفة</h1>
                </div>
                <div class='content'>
                    <p>عزيزي/عزيزتي <strong>{$guardianName}</strong>،</p>
                    <p>نود إحاطتكم علماً بأنه تم تسجيل مخالفة للطالب/ة <strong>{$studentName}</strong>.</p>
                    
                    <div class='info-box'>
                        <h3>تفاصيل المخالفة:</h3>
                        <p><strong>الطالب/ة:</strong> {$studentName}</p>
                        <p><strong>الصف:</strong> {$grade}</p>
                        <p><strong>نوع المخالفة:</strong> {$type}</p>
                        <p><strong>درجة الخطورة:</strong> <span class='severity'>{$severity}</span></p>
                        <p><strong>التاريخ:</strong> {$date}</p>
                        <p><strong>الوصف:</strong> {$description}</p>
                    </div>
                    
                    <div class='alert-box'>
                        <p><strong>الإجراءات المطلوبة:</strong></p>
                        <p>نرجو منكم التواصل مع إدارة المدرسة لمناقشة هذا الموضوع واتخاذ الإجراءات اللازمة.</p>
                    </div>
                    
                    <p>نحن نقدر تعاونكم المستمر في متابعة سلوك أبنائكم الطلبة.</p>
                    
                    <p style='margin-top: 30px;'>مع التقدير،<br>إدارة المدرسة</p>
                </div>
                <div class='footer'>
                    <p>نظام الإرشاد المدرسي © 2025</p>
                    <p>للاستفسار: يرجى التواصل مع إدارة المدرسة</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getParentMeetingTemplate($meetingData, $parentName = '') {
        $studentName = htmlspecialchars($meetingData['student_name'] ?? 'الطالب/ة');
        $date = htmlspecialchars($meetingData['date'] ?? date('Y-m-d'));
        $topic = htmlspecialchars($meetingData['topic'] ?? 'موضوع عام');
        $parentName = htmlspecialchars($parentName ?: 'ولي الأمر');
        
        return "
        <!DOCTYPE html>
        <html lang='ar' dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; direction: rtl; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .info-box { background: #d1ecf1; border-right: 4px solid #0dcaf0; padding: 15px; margin: 20px 0; border-radius: 6px; }
                .date-box { background: #e7f3ff; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; }
                .date-box h2 { color: #0d6efd; margin: 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📅 تذكير بموعد مقابلة ولي الأمر</h1>
                </div>
                <div class='content'>
                    <p>عزيزي/عزيزتي <strong>{$parentName}</strong>،</p>
                    <p>نذكركم بموعد المقابلة المقررة مع إدارة المدرسة بخصوص الطالب/ة <strong>{$studentName}</strong>.</p>
                    
                    <div class='date-box'>
                        <h2>📆 {$date}</h2>
                    </div>
                    
                    <div class='info-box'>
                        <h3>تفاصيل المقابلة:</h3>
                        <p><strong>الطالب/ة:</strong> {$studentName}</p>
                        <p><strong>الموضوع:</strong> {$topic}</p>
                    </div>
                    
                    <p>نرجو منكم الحضور في الموعد المحدد. في حال وجود أي طارئ يمنعكم من الحضور، يرجى إبلاغ إدارة المدرسة مسبقاً.</p>
                    
                    <p style='margin-top: 30px;'>نتطلع للقائكم،<br>إدارة المدرسة 🏫</p>
                </div>
                <div class='footer'>
                    <p>نظام الإرشاد المدرسي © 2025</p>
                    <p>للاستفسار أو إعادة الجدولة، يرجى التواصل مع إدارة المدرسة</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getReporterConfirmationTemplate($violationData) {
        $studentName = htmlspecialchars($violationData['student_name'] ?? 'الطالب/ة');
        $grade = htmlspecialchars($violationData['student_grade'] ?? 'غير محدد');
        $type = htmlspecialchars($violationData['type'] ?? 'غير محدد');
        $severity = htmlspecialchars($violationData['severity'] ?? 'متوسطة');
        $date = htmlspecialchars($violationData['date'] ?? date('Y-m-d'));
        $description = htmlspecialchars($violationData['description'] ?? 'لا يوجد وصف');
        
        $severityColor = $severity === 'خطيرة' ? '#dc3545' : ($severity === 'متوسطة' ? '#ffc107' : '#28a745');
        
        return "
        <!DOCTYPE html>
        <html lang='ar' dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; direction: rtl; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .success-box { background: #d4edda; border-right: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 6px; }
                .info-box { background: #f8f9fa; border-right: 4px solid {$severityColor}; padding: 15px; margin: 20px 0; border-radius: 6px; }
                .severity { display: inline-block; padding: 5px 15px; background: {$severityColor}; color: white; border-radius: 20px; font-size: 14px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ تأكيد تسجيل المخالفة</h1>
                </div>
                <div class='content'>
                    <div class='success-box'>
                        <p><strong>✓ تم تسجيل المخالفة بنجاح</strong></p>
                        <p>شكراً لك على إبلاغنا بهذه المخالفة. تم حفظ التقرير في نظام الإرشاد المدرسي.</p>
                    </div>
                    
                    <div class='info-box'>
                        <h3>تفاصيل المخالفة المسجلة:</h3>
                        <p><strong>الطالب/ة:</strong> {$studentName}</p>
                        <p><strong>الصف:</strong> {$grade}</p>
                        <p><strong>نوع المخالفة:</strong> {$type}</p>
                        <p><strong>درجة الخطورة:</strong> <span class='severity'>{$severity}</span></p>
                        <p><strong>التاريخ:</strong> {$date}</p>
                        <p><strong>الوصف:</strong> {$description}</p>
                        <p><strong>وقت التسجيل:</strong> " . date('Y-m-d H:i:s') . "</p>
                    </div>
                    
                    <p>سيتم اتخاذ الإجراءات اللازمة وفقاً لسياسة المدرسة. كما سيتم إشعار ولي أمر الطالب/ة بهذه المخالفة.</p>
                    
                    <p style='margin-top: 30px;'>مع الشكر والتقدير،<br>نظام الإرشاد المدرسي 📋</p>
                </div>
                <div class='footer'>
                    <p>نظام الإرشاد المدرسي © 2025</p>
                    <p>هذا البريد تلقائي للتأكيد، يرجى عدم الرد عليه مباشرة</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

