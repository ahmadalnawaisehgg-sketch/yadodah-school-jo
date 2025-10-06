// ========================================
// بوابة أولياء الأمور - JavaScript
// ========================================

// إعدادات API
const API_BASE = '/api/';

// بيانات ولي الأمر
let parentData = null;
let studentsData = [];
let conversationsData = [];
let currentConversation = null;
let teachersData = [];

// ========================================
// تسجيل الدخول
// ========================================
async function parentLogin() {
    const email = document.getElementById('parentEmail').value.trim();
    const loginMsg = document.getElementById('parentLoginMsg');
    
    if (!email) {
        showMessage(loginMsg, 'يرجى إدخال البريد الإلكتروني', 'error');
        return;
    }
    
    // التحقق من صيغة الإيميل
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showMessage(loginMsg, 'صيغة البريد الإلكتروني غير صحيحة', 'error');
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'parent_auth.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'include',
            body: JSON.stringify({
                action: 'parent_login',
                email: email
            })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            parentData = data.parent;
            studentsData = data.students;
            
            // إخفاء شاشة تسجيل الدخول وعرض البوابة
            document.getElementById('parent-login-screen').style.display = 'none';
            document.getElementById('parentNavbar').style.display = 'block';
            document.getElementById('parentContainer').style.display = 'flex';
            
            // تحديث المعلومات في الشريط العلوي
            document.getElementById('parentUserName').textContent = parentData.full_name;
            document.getElementById('parentStudentsCount').textContent = `${parentData.students_count} طالب`;
            
            // تحميل البيانات
            loadDashboard();
            checkUnreadMessages();
            
            showNotification('مرحباً بك في بوابة أولياء الأمور', 'success');
        } else {
            showMessage(loginMsg, data.error || 'حدث خطأ في تسجيل الدخول', 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showMessage(loginMsg, 'حدث خطأ في الاتصال بالخادم', 'error');
    }
}

// تسجيل الخروج
async function parentLogout() {
    try {
        await fetch(API_BASE + 'parent_auth.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'include',
            body: JSON.stringify({action: 'parent_logout'})
        });
    } catch (error) {
        console.error('Logout error:', error);
    }
    
    document.getElementById('parent-login-screen').style.display = 'block';
    document.getElementById('parentNavbar').style.display = 'none';
    document.getElementById('parentContainer').style.display = 'none';
    
    setTimeout(() => {
        window.location.href = 'index.html';
    }, 500);
}

// ========================================
// عرض الأقسام
// ========================================
function openParentSection(sectionName) {
    // إخفاء جميع الأقسام
    document.querySelectorAll('.parent-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // إزالة الفئة النشطة من جميع الأزرار
    document.querySelectorAll('.menu-item').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // عرض القسم المطلوب
    const section = document.getElementById(`parent-${sectionName}`);
    if (section) {
        section.style.display = 'block';
    }
    
    // تفعيل الزر
    event.target.classList.add('active');
    
    // تحميل البيانات حسب القسم
    switch(sectionName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'students':
            loadStudentsDetails();
            break;
        case 'messages':
            loadConversations();
            break;
        case 'meetings':
            loadMeetings();
            break;
        case 'violations':
            loadViolations();
            break;
        case 'profile':
            loadProfile();
            break;
    }
}

// ========================================
// تحميل لوحة التحكم
// ========================================
async function loadDashboard() {
    const container = document.getElementById('studentsCards');
    container.innerHTML = '';
    
    for (const student of studentsData) {
        try {
            const response = await fetch(API_BASE + `parent_portal.php?action=student_dashboard&student_id=${student.id}`, {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                const card = createStudentCard(data.student, data.stats);
                container.innerHTML += card;
            }
        } catch (error) {
            console.error('Error loading student dashboard:', error);
        }
    }
    
    // تحميل المواعيد القادمة
    loadUpcomingMeetings();
}

function createStudentCard(student, stats) {
    const initials = student.name.split(' ').map(n => n[0]).join('').substring(0, 2);
    
    return `
        <div class="student-card" onclick="viewStudentDetails(${student.id})">
            <div class="student-card-header">
                <div class="student-avatar">${initials}</div>
                <div class="student-info">
                    <h5>${student.name}</h5>
                    <p><i class="fas fa-graduation-cap"></i> ${student.grade} - ${student.section}</p>
                </div>
            </div>
            <div class="student-stats">
                <div class="stat-item">
                    <span class="stat-value">${stats.total_violations}</span>
                    <span class="stat-label">المخالفات</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">${stats.total_meetings}</span>
                    <span class="stat-label">المقابلات</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">${stats.total_guidance}</span>
                    <span class="stat-label">التوجيه</span>
                </div>
            </div>
            ${stats.violations_this_month > 0 ? `
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${stats.violations_this_month} مخالفات هذا الشهر
                </div>
            ` : ''}
        </div>
    `;
}

// ========================================
// تحميل تفاصيل الطلاب
// ========================================
async function loadStudentsDetails() {
    const container = document.getElementById('studentsDetailsList');
    container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    
    let html = '';
    
    for (const student of studentsData) {
        try {
            const response = await fetch(API_BASE + `parent_portal.php?action=student_dashboard&student_id=${student.id}`, {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                html += `
                    <div class="card-panel">
                        <div class="student-card-header">
                            <div class="student-avatar">${student.name.split(' ').map(n => n[0]).join('').substring(0, 2)}</div>
                            <div class="student-info">
                                <h5>${student.name}</h5>
                                <p><i class="fas fa-id-card"></i> ${student.national_id || 'غير محدد'}</p>
                                <p><i class="fas fa-graduation-cap"></i> ${student.grade} - ${student.section}</p>
                                <p><i class="fas fa-envelope"></i> ${student.email || 'غير محدد'}</p>
                                <p><i class="fas fa-phone"></i> ${student.phone || 'غير محدد'}</p>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <span class="stat-value text-danger">${data.stats.total_violations}</span>
                                    <span class="stat-label">إجمالي المخالفات</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <span class="stat-value text-warning">${data.stats.violations_this_month}</span>
                                    <span class="stat-label">مخالفات هذا الشهر</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <span class="stat-value text-info">${data.stats.total_meetings}</span>
                                    <span class="stat-label">المقابلات</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <span class="stat-value text-success">${data.stats.total_guidance}</span>
                                    <span class="stat-label">جلسات التوجيه</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-primary btn-sm" onclick="viewStudentViolations(${student.id})">
                                <i class="fas fa-exclamation-triangle"></i> عرض المخالفات
                            </button>
                            <button class="btn btn-info btn-sm" onclick="viewStudentMeetings(${student.id})">
                                <i class="fas fa-calendar-alt"></i> عرض المواعيد
                            </button>
                        </div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading student details:', error);
        }
    }
    
    container.innerHTML = html;
}

// ========================================
// المحادثات والرسائل
// ========================================
async function loadConversations() {
    try {
        const response = await fetch(API_BASE + 'conversations.php?action=my_conversations', {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            conversationsData = data.conversations;
            renderConversations();
        }
    } catch (error) {
        console.error('Error loading conversations:', error);
    }
}

function renderConversations() {
    const container = document.getElementById('conversationsList');
    
    if (conversationsData.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-comments fa-3x mb-3"></i>
                <p>لا توجد محادثات</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    conversationsData.forEach(conv => {
        html += `
            <div class="conversation-item ${currentConversation && currentConversation.id === conv.id ? 'active' : ''}" 
                 onclick="openConversation(${conv.id})">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${conv.title || 'محادثة'}</strong>
                        <p class="text-muted small mb-0">${conv.last_message || ''}</p>
                    </div>
                    ${conv.unread_count > 0 ? `<span class="badge bg-danger">${conv.unread_count}</span>` : ''}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

async function openConversation(conversationId) {
    try {
        const response = await fetch(API_BASE + `conversations.php?action=conversation_messages&conversation_id=${conversationId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentConversation = {id: conversationId, messages: data.messages};
            renderMessages();
            renderConversations(); // تحديث عدد الرسائل غير المقروءة
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

function renderMessages() {
    const container = document.getElementById('messagesChat');
    
    if (!currentConversation || currentConversation.messages.length === 0) {
        container.innerHTML = `
            <div class="empty-chat">
                <i class="fas fa-comment-dots fa-4x mb-3"></i>
                <h5>لا توجد رسائل</h5>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="chat-header">
            <h5><i class="fas fa-comments"></i> المحادثة</h5>
        </div>
        <div class="chat-messages" id="chatMessages">
    `;
    
    currentConversation.messages.forEach(msg => {
        const isSent = msg.sender_id == parentData.id && msg.sender_type === 'parent';
        html += `
            <div class="message-bubble ${isSent ? 'sent' : 'received'}">
                <div><strong>${msg.sender_name || msg.sender_full_name}</strong></div>
                <div>${msg.message}</div>
                <small class="text-muted">${formatDateTime(msg.created_at)}</small>
            </div>
        `;
    });
    
    html += `
        </div>
        <div class="chat-input">
            <textarea id="newMessageText" class="form-control" rows="2" placeholder="اكتب رسالتك..."></textarea>
            <button class="btn btn-primary" onclick="sendMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    `;
    
    container.innerHTML = html;
    
    // التمرير للأسفل
    setTimeout(() => {
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }, 100);
}

async function sendMessage() {
    const messageText = document.getElementById('newMessageText').value.trim();
    
    if (!messageText || !currentConversation) {
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'conversations.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'include',
            body: JSON.stringify({
                action: 'send_message',
                conversation_id: currentConversation.id,
                message: messageText
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('newMessageText').value = '';
            openConversation(currentConversation.id); // تحديث الرسائل
        }
    } catch (error) {
        console.error('Error sending message:', error);
    }
}

function startNewConversation() {
    const modal = new bootstrap.Modal(document.getElementById('newMessageModal'));
    modal.show();
}

async function sendNewMessage() {
    const subject = document.getElementById('messageSubject').value.trim();
    const content = document.getElementById('messageContent').value.trim();
    
    if (!content) {
        showNotification('يرجى كتابة رسالة', 'error');
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'conversations.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'include',
            body: JSON.stringify({
                action: 'start_conversation',
                title: subject || 'محادثة جديدة',
                recipient_id: 1,
                recipient_type: 'user',
                message: content
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('تم إرسال الرسالة بنجاح', 'success');
            document.getElementById('messageSubject').value = '';
            document.getElementById('messageContent').value = '';
            bootstrap.Modal.getInstance(document.getElementById('newMessageModal')).hide();
            loadConversations();
        }
    } catch (error) {
        console.error('Error sending message:', error);
        showNotification('حدث خطأ في إرسال الرسالة', 'error');
    }
}

// التحقق من الرسائل غير المقروءة
async function checkUnreadMessages() {
    try {
        const response = await fetch(API_BASE + 'parent_portal.php?action=unread_messages_count', {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success && data.unread_count > 0) {
            document.getElementById('unreadBadge').textContent = data.unread_count;
            document.getElementById('unreadBadge').style.display = 'block';
            document.getElementById('messagesBadge').textContent = data.unread_count;
            document.getElementById('messagesBadge').style.display = 'block';
        }
    } catch (error) {
        console.error('Error checking unread messages:', error);
    }
}

// ========================================
// المواعيد
// ========================================
async function loadMeetings() {
    // سيتم التنفيذ
    showNotification('جاري تحميل المواعيد...', 'info');
}

function showMeetingTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    document.getElementById('upcomingMeetings').style.display = tab === 'upcoming' ? 'block' : 'none';
    document.getElementById('pastMeetings').style.display = tab === 'past' ? 'block' : 'none';
    document.getElementById('meetingRequests').style.display = tab === 'requests' ? 'block' : 'none';
}

function requestNewMeeting() {
    // ملء قائمة الطلاب
    const select = document.getElementById('meetingStudentSelect');
    select.innerHTML = studentsData.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    
    const modal = new bootstrap.Modal(document.getElementById('requestMeetingModal'));
    modal.show();
}

async function submitMeetingRequest() {
    const studentId = document.getElementById('meetingStudentSelect').value;
    const date = document.getElementById('meetingDate').value;
    const time = document.getElementById('meetingTime').value;
    const topic = document.getElementById('meetingTopic').value.trim();
    const reason = document.getElementById('meetingReason').value.trim();
    
    if (!topic) {
        showNotification('يرجى إدخال موضوع المقابلة', 'error');
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'parent_portal.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'include',
            body: JSON.stringify({
                action: 'request_meeting',
                student_id: studentId,
                requested_date: date,
                requested_time: time,
                topic: topic,
                reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('requestMeetingModal')).hide();
            document.getElementById('meetingTopic').value = '';
            document.getElementById('meetingReason').value = '';
        } else {
            showNotification(data.error, 'error');
        }
    } catch (error) {
        console.error('Error submitting meeting request:', error);
        showNotification('حدث خطأ في إرسال الطلب', 'error');
    }
}

async function loadUpcomingMeetings() {
    // سيتم التنفيذ
}

// ========================================
// المخالفات
// ========================================
async function loadViolations() {
    const container = document.getElementById('violationsList');
    container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    
    // ملء فلتر الطلاب
    const studentFilter = document.getElementById('violationStudentFilter');
    studentFilter.innerHTML = '<option value="">جميع الأبناء</option>' +
        studentsData.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    
    let allViolations = [];
    
    for (const student of studentsData) {
        try {
            const response = await fetch(API_BASE + `parent_portal.php?action=student_violations&student_id=${student.id}`, {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                allViolations = allViolations.concat(data.violations.map(v => ({...v, student_name: student.name})));
            }
        } catch (error) {
            console.error('Error loading violations:', error);
        }
    }
    
    renderViolations(allViolations);
}

function renderViolations(violations) {
    const container = document.getElementById('violationsList');
    
    if (violations.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
                <h5>لا توجد مخالفات</h5>
                <p>ممتاز! لا توجد مخالفات مسجلة</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    violations.forEach(v => {
        html += `
            <div class="violation-card severity-${v.severity}">
                <div class="violation-header">
                    <div>
                        <span class="violation-type">${v.type}</span>
                        <br><small class="text-muted"><i class="fas fa-user"></i> ${v.student_name}</small>
                    </div>
                    <span class="violation-severity ${v.severity}">${v.severity}</span>
                </div>
                <p><i class="fas fa-calendar"></i> ${formatDate(v.date)}</p>
                <p><strong>الوصف:</strong> ${v.description || 'لا يوجد'}</p>
                <p><strong>الإجراء المتخذ:</strong> ${v.action_taken || 'لا يوجد'}</p>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function filterViolations() {
    // سيتم التنفيذ - فلترة المخالفات
}

// ========================================
// الملف الشخصي
// ========================================
function loadProfile() {
    const container = document.getElementById('profileInfo');
    
    container.innerHTML = `
        <div class="mb-3">
            <label class="text-muted">الاسم</label>
            <p class="h6">${parentData.full_name}</p>
        </div>
        <div class="mb-3">
            <label class="text-muted">البريد الإلكتروني</label>
            <p class="h6">${parentData.email}</p>
        </div>
        <div class="mb-3">
            <label class="text-muted">رقم الهاتف</label>
            <p class="h6">${parentData.phone || 'غير محدد'}</p>
        </div>
        <div class="mb-3">
            <label class="text-muted">عدد الأبناء</label>
            <p class="h6">${parentData.students_count}</p>
        </div>
    `;
}

// ========================================
// دوال مساعدة
// ========================================
function showMessage(element, message, type) {
    element.textContent = message;
    element.className = `alert alert-${type === 'error' ? 'danger' : 'success'}`;
    element.style.display = 'block';
    
    setTimeout(() => {
        element.style.display = 'none';
    }, 5000);
}

function showNotification(message, type = 'info') {
    const notificationArea = document.getElementById('notificationArea');
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <strong>${type === 'success' ? 'نجاح' : type === 'error' ? 'خطأ' : 'معلومة'}</strong>
        <p class="mb-0">${message}</p>
    `;
    
    notificationArea.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA');
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('ar-SA', {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function refreshData() {
    const currentSection = document.querySelector('.parent-section:not([style*="display: none"])');
    if (currentSection) {
        const sectionId = currentSection.id.replace('parent-', '');
        openParentSection(sectionId);
    }
    showNotification('تم تحديث البيانات', 'success');
}

// ========================================
// الوضع الليلي
// ========================================
document.getElementById('parentThemeToggle')?.addEventListener('click', function() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    const icon = this.querySelector('i');
    icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
});

// تحميل الثيم المحفوظ
const savedTheme = localStorage.getItem('theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);

// ========================================
// عند تحميل الصفحة
// ========================================
window.addEventListener('DOMContentLoaded', function() {
    // التحقق من الجلسة
    checkParentSession();
    
    // تفعيل Enter للدخول
    document.getElementById('parentEmail')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            parentLogin();
        }
    });
});

async function checkParentSession() {
    try {
        const response = await fetch(API_BASE + 'parent_auth.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'include',
            body: JSON.stringify({action: 'check_parent_session'})
        });
        
        const data = await response.json();
        
        if (data.success && data.authenticated) {
            parentData = data.parent;
            studentsData = data.students;
            
            document.getElementById('parent-login-screen').style.display = 'none';
            document.getElementById('parentNavbar').style.display = 'block';
            document.getElementById('parentContainer').style.display = 'flex';
            
            document.getElementById('parentUserName').textContent = parentData.full_name;
            document.getElementById('parentStudentsCount').textContent = `${parentData.students_count} طالب`;
            
            loadDashboard();
            checkUnreadMessages();
        }
    } catch (error) {
        console.error('Session check error:', error);
    }
}

// ========================================
// نظام المحادثات الجديد
// ========================================
async function startNewConversation() {
    await loadTeachersList();
    const modal = new bootstrap.Modal(document.getElementById('teachersListModal'));
    modal.show();
}

async function loadTeachersList() {
    const container = document.getElementById('teachersListContainer');
    
    try {
        const response = await fetch(API_BASE + 'chat.php?action=teachers', {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success && data.teachers) {
            teachersData = data.teachers;
            renderTeachersList(teachersData);
        } else {
            container.innerHTML = '<div class="text-center text-muted py-4"><p>لا يوجد معلمين متاحين</p></div>';
        }
    } catch (error) {
        console.error('Error loading teachers:', error);
        container.innerHTML = '<div class="text-center text-danger py-4"><p>حدث خطأ في تحميل المعلمين</p></div>';
    }
}

function renderTeachersList(teachers) {
    const container = document.getElementById('teachersListContainer');
    
    if (teachers.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4"><p>لا يوجد معلمين متاحين</p></div>';
        return;
    }
    
    let html = '';
    teachers.forEach(teacher => {
        const initials = teacher.name.split(' ').map(n => n[0]).join('').substring(0, 2);
        html += `
            <div class="teacher-card" onclick="selectTeacher(${teacher.id}, '${teacher.name}')">
                <div class="teacher-card-header">
                    <div class="teacher-avatar">${initials}</div>
                    <div class="teacher-info">
                        <h6>${teacher.name}</h6>
                        <p><i class="fas fa-book"></i> ${teacher.subject || 'غير محدد'}</p>
                    </div>
                </div>
                ${teacher.specialization ? `<p class="text-muted mb-0"><small><i class="fas fa-graduation-cap"></i> ${teacher.specialization}</small></p>` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function filterTeachers() {
    const searchTerm = document.getElementById('searchTeacher').value.toLowerCase();
    const filtered = teachersData.filter(t => 
        t.name.toLowerCase().includes(searchTerm) || 
        (t.subject && t.subject.toLowerCase().includes(searchTerm))
    );
    renderTeachersList(filtered);
}

function selectTeacher(teacherId, teacherName) {
    document.getElementById('selectedTeacherId').value = teacherId;
    document.getElementById('selectedTeacherName').value = teacherName;
    
    // ملء قائمة الطلاب
    const studentSelect = document.getElementById('messageStudentSelect');
    studentSelect.innerHTML = '<option value="">محادثة عامة - بدون طالب محدد</option>' +
        studentsData.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    
    // إخفاء modal المعلمين وإظهار modal الرسالة
    bootstrap.Modal.getInstance(document.getElementById('teachersListModal')).hide();
    const messageModal = new bootstrap.Modal(document.getElementById('newMessageModal'));
    messageModal.show();
}

async function sendFirstMessage() {
    const teacherId = document.getElementById('selectedTeacherId').value;
    const studentId = document.getElementById('messageStudentSelect').value || null;
    const subject = document.getElementById('messageSubject').value.trim() || 'محادثة عامة';
    const message = document.getElementById('messageContent').value.trim();
    
    if (!message) {
        showNotification('يرجى كتابة رسالة', 'error');
        return;
    }
    
    try {
        // بدء المحادثة
        const convResponse = await fetch(API_BASE + 'chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'include',
            body: JSON.stringify({
                action: 'start_conversation',
                teacher_id: teacherId,
                student_id: studentId,
                subject: subject
            })
        });
        
        const convData = await convResponse.json();
        
        if (convData.success && convData.conversation_id) {
            // إرسال الرسالة الأولى
            const msgResponse = await fetch(API_BASE + 'chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'include',
                body: JSON.stringify({
                    action: 'send_message',
                    conversation_id: convData.conversation_id,
                    message: message
                })
            });
            
            const msgData = await msgResponse.json();
            
            if (msgData.success) {
                showNotification('تم بدء المحادثة بنجاح', 'success');
                bootstrap.Modal.getInstance(document.getElementById('newMessageModal')).hide();
                
                // تنظيف النموذج
                document.getElementById('messageSubject').value = 'محادثة عامة';
                document.getElementById('messageContent').value = '';
                
                // تحديث قائمة المحادثات
                loadMessages();
            } else {
                showNotification(msgData.error || 'حدث خطأ في إرسال الرسالة', 'error');
            }
        } else {
            showNotification(convData.error || 'حدث خطأ في بدء المحادثة', 'error');
        }
    } catch (error) {
        console.error('Error sending message:', error);
        showNotification('حدث خطأ في الاتصال بالخادم', 'error');
    }
}
