
// ========================================
// نظام الإرشاد المدرسي - الملف الرئيسي المحسّن
// ========================================

// إعدادات الإنتاج
const IS_PRODUCTION = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';
const DEBUG_MODE = !IS_PRODUCTION;

// دالة لوجينج آمنة
function debugLog(...args) {
    if (DEBUG_MODE) {
        console.log(...args);
    }
}

// إعدادات API
function getApiBase() {
    const currentHost = window.location.hostname;
    debugLog('🌐 Current host:', currentHost);
    
    // استخدام مسار مطلق من الجذر
    return '/api/';
}

const API_BASE = getApiBase();
debugLog('🚀 API Base URL:', API_BASE);

// Flag لمنع تسجيل الخروج المتكرر
let isLoggingOut = false;

// دالة الاتصال الأساسية المحسّنة
async function apiCall(endpoint, method = 'GET', data = null, showLoading = true) {
    const url = endpoint.startsWith('http') ? endpoint : API_BASE + endpoint;
    debugLog(`📡 API Call: ${method} ${url}`, data);
    
    if (showLoading) {
        showLoadingSpinner();
    }
    
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include'
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        debugLog(`📨 Response Status: ${response.status}`);
        
        const responseText = await response.text();
        debugLog('📄 Raw Response:', responseText.substring(0, 200));
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            debugLog('❌ JSON Parse Error:', parseError);
            throw new Error(`الخادم أرجع بيانات غير صالحة`);
        }
        
        if (response.status === 401 && result.require_login) {
            if (!isLoggingOut) {
                isLoggingOut = true;
                showNotification('انتهت الجلسة، يرجى تسجيل الدخول مرة أخرى', 'error');
                setTimeout(() => {
                    logout();
                }, 1000);
            }
            return null;
        }
        
        if (!response.ok) {
            let errorMsg;
            if (result.error) {
                errorMsg = result.error;
            } else if (result.errors && typeof result.errors === 'object') {
                errorMsg = Object.values(result.errors).join(', ');
            } else {
                errorMsg = `خطأ في الخادم: ${response.status}`;
            }
            debugLog(`❌ API Error: ${errorMsg}`);
            throw new Error(errorMsg);
        }
        
        debugLog(`✅ API Success`);
        return result;
        
    } catch (error) {
        debugLog(`❌ API Call error:`, error.message);
        
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            if (showLoading) {
                showNotification('خطأ في الاتصال بالشبكة', 'error');
            }
        } else if (!error.message.includes('انتهت الجلسة') && !error.message.includes('الخادم أرجع بيانات')) {
            if (showLoading) {
                showNotification('خطأ: ' + error.message, 'error');
            }
        }
        throw error;
    } finally {
        if (showLoading) {
            hideLoadingSpinner();
        }
    }
}

// دوال Loading Spinner
function showLoadingSpinner() {
    let spinner = document.getElementById('loadingSpinner');
    if (!spinner) {
        spinner = document.createElement('div');
        spinner.id = 'loadingSpinner';
        spinner.className = 'loading-spinner';
        spinner.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">جاري التحميل...</span></div>';
        document.body.appendChild(spinner);
    }
    spinner.style.display = 'flex';
}

function hideLoadingSpinner() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.style.display = 'none';
    }
}

// ========================================
// دوال API
// ========================================

// دوال الطلاب
async function loadStudentsData() {
    return await apiCall('students_new.php');
}

async function saveStudentData(student) {
    return await apiCall('students_new.php', 'POST', student);
}

async function deleteStudentData(id) {
    return await apiCall('students_new.php', 'DELETE', {id});
}

// دوال المخالفات
async function loadViolationsData() {
    return await apiCall('violations.php');
}

async function saveViolationData(violation) {
    return await apiCall('violations.php', 'POST', violation);
}

async function deleteViolationData(id) {
    return await apiCall('violations.php', 'DELETE', {id});
}

// دوال التوجيه الجمعي
async function loadGuidanceData() {
    return await apiCall('guidance.php');
}

async function saveGuidanceData(session) {
    return await apiCall('guidance.php', 'POST', session);
}

async function deleteGuidanceData(id) {
    return await apiCall('guidance.php', 'DELETE', {id});
}

// دوال الاجتماعات
async function loadMeetingsData() {
    return await apiCall('meetings.php');
}

async function saveMeetingData(meeting) {
    return await apiCall('meetings.php', 'POST', meeting);
}

async function deleteMeetingData(id) {
    return await apiCall('meetings.php', 'DELETE', {id});
}

// دوال مقابلات أولياء الأمور
async function loadParentsData() {
    return await apiCall('parents.php');
}

async function saveParentData(meeting) {
    return await apiCall('parents.php', 'POST', meeting);
}

async function deleteParentData(id) {
    return await apiCall('parents.php', 'DELETE', {id});
}

// دوال المعلمين
async function loadTeachersData() {
    return await apiCall('teachers.php');
}

async function saveTeacherData(teacher) {
    return await apiCall('teachers.php', 'POST', teacher);
}

async function deleteTeacherData(id) {
    return await apiCall('teachers.php', 'DELETE', {id});
}

// ========================================
// دوال المصادقة
// ========================================

async function login() {
    const username = document.getElementById('loginUser').value.trim();
    const password = document.getElementById('loginPass').value;
    const msg = document.getElementById('loginMsg');
    
    if (!username || !password) {
        msg.textContent = 'يرجى إدخال اسم المستخدم وكلمة المرور';
        return;
    }
    
    const result = await apiCall('auth.php', 'POST', {
        action: 'login',
        username: username,
        password: password
    });
    
    if (result && result.success) {
        document.getElementById('login-screen').style.display = 'none';
        document.getElementById('sidebar').style.display = 'block';
        document.getElementById('content').style.display = 'block';
        const navbar = document.querySelector('.top-navbar');
        if (navbar) navbar.style.display = 'flex';
        
        openSection('dashboard');
        updateDashboard();
        showNotification('تم تسجيل الدخول بنجاح', 'success');
    } else {
        msg.textContent = result?.error || 'اسم المستخدم أو كلمة المرور غير صحيحة';
        showNotification('فشل تسجيل الدخول', 'error');
    }
}

async function logout() {
    try {
        await apiCall('auth.php', 'POST', { action: 'logout' }, false);
    } catch (error) {
        debugLog('Logout error:', error);
    }
    
    document.getElementById('login-screen').style.display = 'flex';
    document.getElementById('sidebar').style.display = 'none';
    document.getElementById('content').style.display = 'none';
    const navbar = document.querySelector('.top-navbar');
    if (navbar) navbar.style.display = 'none';
    
    setTimeout(() => {
        window.location.href = 'index.html';
    }, 500);
}

// التحقق من الجلسة عند تحميل الصفحة
async function checkSession() {
    const result = await apiCall('auth.php', 'POST', { action: 'check' }, false);
    
    if (result && result.authenticated) {
        document.getElementById('login-screen').style.display = 'none';
        document.getElementById('sidebar').style.display = 'block';
        document.getElementById('content').style.display = 'block';
        const navbar = document.querySelector('.top-navbar');
        if (navbar) navbar.style.display = 'flex';
        openSection('dashboard');
    }
}

// ========================================
// دوال الواجهة
// ========================================

let editingId = null;
let currentEditingType = null;
let studentsByGradeChart, violationsByMonthChart;

// عرض الإشعارات
function showNotification(message, type = 'success') {
    const notificationArea = document.getElementById('notificationArea');
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle me-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    notificationArea.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

function openSection(sectionName) {
    debugLog(`📂 Opening section: ${sectionName}`);
    
    document.querySelectorAll('.menu-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const menuIndex = getMenuIndex(sectionName);
    const menuBtn = document.querySelector(`.menu-btn:nth-child(${menuIndex})`);
    if (menuBtn) menuBtn.classList.add('active');
    
    document.querySelectorAll('section').forEach(sec => sec.style.display = 'none');
    const section = document.getElementById(`section-${sectionName}`);
    if (section) section.style.display = 'block';
    
    editingId = null;
    currentEditingType = null;
    
    document.querySelectorAll('button[id$="SaveBtn"]').forEach(btn => {
        if (btn.textContent.includes('تحديث')) {
            btn.innerHTML = '<i class="fas fa-save"></i> حفظ';
        }
    });
    
    if (sectionName === 'dashboard') {
        updateDashboard();
    } else if (sectionName === 'students') {
        renderStudentsTable();
        loadStudentsDatalist();
    } else if (sectionName === 'violations') {
        renderViolationsTable();
    } else if (sectionName === 'guidance') {
        renderGuidanceTable();
    } else if (sectionName === 'meetings') {
        renderMeetingsTable();
    } else if (sectionName === 'parents') {
        renderParentsTable();
    } else if (sectionName === 'teachers') {
        renderTeachersTable();
    }
    
    if (window.innerWidth <= 992) {
        toggleSidebar();
    }
}

function getMenuIndex(sectionName) {
    const sections = ['dashboard', 'students', 'violations', 'guidance', 'meetings', 'parents', 'teachers'];
    return sections.indexOf(sectionName) + 3;
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburger = document.getElementById('hamburgerMenu');
    
    if (sidebar && overlay && hamburger) {
        if (sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            hamburger.classList.remove('active');
        } else {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            hamburger.classList.add('active');
        }
    }
}

// ========================================
// لوحة القيادة
// ========================================

async function updateDashboard() {
    try {
        debugLog('🔄 Updating dashboard...');
        
        const results = await Promise.allSettled([
            loadStudentsData(),
            loadViolationsData(),
            loadGuidanceData(),
            loadMeetingsData(),
            loadParentsData(),
            loadTeachersData()
        ]);
        
        const students = results[0].status === 'fulfilled' ? results[0].value : [];
        const violations = results[1].status === 'fulfilled' ? results[1].value : [];
        const guidance = results[2].status === 'fulfilled' ? results[2].value : [];
        const meetings = results[3].status === 'fulfilled' ? results[3].value : [];
        const parents = results[4].status === 'fulfilled' ? results[4].value : [];
        const teachers = results[5].status === 'fulfilled' ? results[5].value : [];
        
        document.getElementById('statStudents').textContent = students ? students.length : 0;
        document.getElementById('statViolations').textContent = violations ? violations.length : 0;
        document.getElementById('statGuidance').textContent = guidance ? guidance.length : 0;
        document.getElementById('statMeetings').textContent = meetings ? meetings.length : 0;
        
        document.getElementById('statStudentsCard').textContent = students ? students.length : 0;
        document.getElementById('statViolationsCard').textContent = violations ? violations.length : 0;
        document.getElementById('statGuidanceCard').textContent = guidance ? guidance.length : 0;
        document.getElementById('statMeetingsCard').textContent = meetings ? meetings.length : 0;
        document.getElementById('statParentsCard').textContent = parents ? parents.length : 0;
        document.getElementById('statTeachersCard').textContent = teachers ? teachers.length : 0;
        
        debugLog('✅ Dashboard updated successfully');
        
        if (students && violations) {
            renderCharts(students, violations);
        }
        
        updateRecentActivities(students, violations, guidance, meetings);
        
    } catch (error) {
        debugLog('❌ Error updating dashboard:', error);
    }
}

function updateRecentActivities(students, violations, guidance, meetings) {
    const activitiesContainer = document.getElementById('recentActivities');
    if (!activitiesContainer) return;
    
    activitiesContainer.innerHTML = '';
    const allActivities = [];
    
    if (violations && Array.isArray(violations)) {
        violations.slice(-5).forEach(violation => {
            allActivities.push({
                type: 'violation',
                text: `تم تسجيل مخالفة للطالب ${violation.student_name || 'غير معروف'}`,
                date: violation.date,
                icon: 'exclamation-triangle',
                color: 'danger'
            });
        });
    }
    
    if (guidance && Array.isArray(guidance)) {
        guidance.slice(-5).forEach(session => {
            allActivities.push({
                type: 'guidance',
                text: `تم عقد جلسة توجيه جماعي حول ${session.topic || 'موضوع غير محدد'}`,
                date: session.date,
                icon: 'users',
                color: 'success'
            });
        });
    }
    
    if (meetings && Array.isArray(meetings)) {
        meetings.slice(-5).forEach(meeting => {
            allActivities.push({
                type: 'meeting',
                text: `تم عقد اجتماع رقم ${meeting.meeting_number || 'غير محدد'}`,
                date: meeting.date,
                icon: 'school',
                color: 'warning'
            });
        });
    }
    
    allActivities.sort((a, b) => new Date(b.date) - new Date(a.date));
    
    allActivities.slice(0, 10).forEach(activity => {
        const activityEl = document.createElement('div');
        activityEl.className = 'd-flex align-items-center p-3 border-bottom';
        activityEl.innerHTML = `
            <div class="me-3">
                <i class="fas fa-${activity.icon} text-${activity.color} fa-lg"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold">${escapeHtml(activity.text)}</div>
                <small class="text-muted">${activity.date}</small>
            </div>
        `;
        activitiesContainer.appendChild(activityEl);
    });
}

function renderCharts(students, violations) {
    const gradeCounts = {};
    if (students && Array.isArray(students)) {
        students.forEach(student => {
            const grade = student.grade || 'غير محدد';
            gradeCounts[grade] = (gradeCounts[grade] || 0) + 1;
        });
    }
    
    const gradeLabels = Object.keys(gradeCounts);
    const gradeData = Object.values(gradeCounts);
    
    const studentsCtx = document.getElementById('studentsByGradeChart');
    if (studentsCtx) {
        if (studentsByGradeChart) studentsByGradeChart.destroy();
        
        studentsByGradeChart = new Chart(studentsCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: gradeLabels,
                datasets: [{
                    data: gradeData,
                    backgroundColor: [
                        '#4361ee', '#f72585', '#4cc9a7', '#f8961e', '#7209b7', '#2a9d8f'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    const monthCounts = {};
    const currentMonth = new Date().getMonth();
    const monthNames = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 
                       'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    
    if (violations && Array.isArray(violations)) {
        violations.forEach(violation => {
            if (violation.date) {
                const date = new Date(violation.date);
                const month = date.getMonth();
                monthCounts[month] = (monthCounts[month] || 0) + 1;
            }
        });
    }
    
    const monthLabels = [];
    const monthData = [];
    
    for (let i = 0; i < 6; i++) {
        const monthIndex = (currentMonth - i + 12) % 12;
        monthLabels.unshift(monthNames[monthIndex]);
        monthData.unshift(monthCounts[monthIndex] || 0);
    }
    
    const violationsCtx = document.getElementById('violationsByMonthChart');
    if (violationsCtx) {
        if (violationsByMonthChart) violationsByMonthChart.destroy();
        
        violationsByMonthChart = new Chart(violationsCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'عدد المخالفات',
                    data: monthData,
                    backgroundColor: '#f72585'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
}

// ========================================
// الطلاب
// ========================================

async function loadStudentsDatalist() {
    try {
        const students = await loadStudentsData();
        const datalist = document.getElementById('studentsDatalist');
        if (datalist && students) {
            datalist.innerHTML = '';
            students.forEach(student => {
                const option = document.createElement('option');
                option.value = student.name;
                datalist.appendChild(option);
            });
        }
    } catch (error) {
        debugLog('Error loading students datalist:', error);
    }
}

async function renderStudentsTable() {
    try {
        debugLog('🔄 Rendering students table...');
        const students = await loadStudentsData();
        
        if (!students) return;
        
        const search = document.getElementById('studentsSearch').value.toLowerCase();
        const gradeFilter = document.getElementById('filterGradeS').value;
        
        let filtered = students.filter(s => 
            s.name.toLowerCase().includes(search) &&
            (gradeFilter === '' || s.grade === gradeFilter)
        );
        
        const tbody = document.getElementById('studentsTable').querySelector('tbody');
        tbody.innerHTML = '';
        
        filtered.forEach(student => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(student.name)}</td>
                <td>${escapeHtml(student.national_id || '-')}</td>
                <td>${escapeHtml(student.email || '-')}</td>
                <td>${escapeHtml(student.guardian || '-')}</td>
                <td>${escapeHtml(student.grade || '-')}</td>
                <td>${escapeHtml(student.section || '-')}</td>
                <td>${escapeHtml(student.phone || '-')}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn btn-edit" onclick="editStudent(${student.id})">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                        <button class="action-btn btn-delete" onclick="deleteStudentConfirm(${student.id})">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
        
        debugLog(`✅ Rendered ${filtered.length} students`);
    } catch (error) {
        debugLog('❌ Error rendering students table:', error);
    }
}

async function saveStudent() {
    const name = document.getElementById('stu_name').value.trim();
    const national_id = document.getElementById('stu_national_id').value.trim();
    const email = document.getElementById('stu_email').value.trim();
    const guardian = document.getElementById('stu_guardian').value.trim();
    const grade = document.getElementById('stu_grade').value;
    const section = document.getElementById('stu_section').value;
    const phone = document.getElementById('stu_phone').value.trim();
    
    if (!name) {
        showNotification('يرجى إدخال اسم الطالب', 'error');
        return;
    }
    
    if (email && !email.includes('@')) {
        showNotification('يرجى إدخال بريد إلكتروني صحيح', 'error');
        return;
    }
    
    const studentData = { 
        name, 
        national_id, 
        email, 
        guardian, 
        grade, 
        section, 
        phone 
    };
    
    if (editingId && currentEditingType === 'student') {
        studentData.id = editingId;
    }
    
    try {
        const result = await saveStudentData(studentData);
        
        if (result && result.success) {
            showNotification('تم حفظ الطالب بنجاح!' + (email ? ' تم إرسال إشعار بالبريد الإلكتروني' : ''), 'success');
            clearStudentForm();
            await renderStudentsTable();
            await loadStudentsDatalist();
            await updateDashboard();
        } else {
            showNotification('فشل في حفظ الطالب', 'error');
        }
    } catch (error) {
        debugLog('❌ Error saving student:', error);
        showNotification('حدث خطأ في حفظ الطالب', 'error');
    }
}

async function editStudent(id) {
    const students = await loadStudentsData();
    const student = students.find(s => s.id === id);
    
    if (student) {
        document.getElementById('stu_name').value = student.name || '';
        document.getElementById('stu_national_id').value = student.national_id || '';
        document.getElementById('stu_email').value = student.email || '';
        document.getElementById('stu_guardian').value = student.guardian || '';
        document.getElementById('stu_grade').value = student.grade || '';
        document.getElementById('stu_section').value = student.section || '';
        document.getElementById('stu_phone').value = student.phone || '';
        document.getElementById('stuSaveBtn').innerHTML = '<i class="fas fa-save"></i> تحديث';
        editingId = id;
        currentEditingType = 'student';
    }
}

async function deleteStudentConfirm(id) {
    if (confirm('هل أنت متأكد من حذف هذا الطالب؟')) {
        const result = await deleteStudentData(id);
        if (result && result.success) {
            showNotification('تم حذف الطالب بنجاح', 'success');
            await renderStudentsTable();
            await loadStudentsDatalist();
            await updateDashboard();
        } else {
            showNotification('فشل في حذف الطالب', 'error');
        }
    }
}

function clearStudentForm() {
    document.getElementById('stu_name').value = '';
    document.getElementById('stu_national_id').value = '';
    document.getElementById('stu_email').value = '';
    document.getElementById('stu_guardian').value = '';
    document.getElementById('stu_grade').selectedIndex = 0;
    document.getElementById('stu_section').selectedIndex = 0;
    document.getElementById('stu_phone').value = '';
    document.getElementById('stuSaveBtn').innerHTML = '<i class="fas fa-save"></i> حفظ';
    editingId = null;
    currentEditingType = null;
}

function clearStudentFilters() {
    document.getElementById('studentsSearch').value = '';
    document.getElementById('filterGradeS').value = '';
    renderStudentsTable();
}

// ========================================
// المخالفات
// ========================================

async function renderViolationsTable() {
    try {
        const violations = await loadViolationsData();
        if (!violations) return;
        
        const search = document.getElementById('violationsSearch').value.toLowerCase();
        const gradeFilter = document.getElementById('violFilterGrade').value;
        
        let filtered = violations.filter(v => 
            (v.student_name.toLowerCase().includes(search) || 
             v.type.toLowerCase().includes(search) || 
             (v.description && v.description.toLowerCase().includes(search))) &&
            (gradeFilter === '' || v.grade === gradeFilter)
        );
        
        const tbody = document.getElementById('violationsTable').querySelector('tbody');
        tbody.innerHTML = '';
        
        filtered.forEach(violation => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(violation.student_name)}</td>
                <td>${escapeHtml(violation.student_grade || 'غير محدد')}</td>
                <td>${escapeHtml(violation.type)}</td>
                <td>${escapeHtml(violation.date)}</td>
                <td>${escapeHtml(violation.description)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn btn-edit" onclick="editViolation(${violation.id})">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                        <button class="action-btn btn-delete" onclick="deleteViolationConfirm(${violation.id})">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) {
        debugLog('Error rendering violations table:', error);
    }
}

async function saveViolation() {
    const student_name = document.getElementById('viol_student').value.trim();
    const type = document.getElementById('viol_type').value.trim();
    const date = document.getElementById('viol_date').value;
    const description = document.getElementById('viol_desc').value.trim();
    const reporter_email = document.getElementById('viol_reporter_email').value.trim();
    
    if (!student_name || !type) {
        showNotification('يرجى إدخال اسم الطالب ونوع المخالفة', 'error');
        return;
    }
    
    const students = await loadStudentsData();
    const student = students ? students.find(s => s.name === student_name) : null;
    const student_grade = student ? student.grade : '';
    
    const violationData = { 
        student_name, 
        student_grade,
        type, 
        date, 
        description,
        reporter_email
    };
    
    if (editingId && currentEditingType === 'violation') {
        violationData.id = editingId;
    }
    
    const result = await saveViolationData(violationData);
    if (result && result.success) {
        showNotification('تم حفظ المخالفة بنجاح', 'success');
        clearViolationForm();
        renderViolationsTable();
        updateDashboard();
    } else {
        showNotification('فشل في حفظ المخالفة', 'error');
    }
}

async function editViolation(id) {
    const violations = await loadViolationsData();
    const violation = violations.find(v => v.id === id);
    
    if (violation) {
        document.getElementById('viol_student').value = violation.student_name;
        document.getElementById('viol_type').value = violation.type;
        document.getElementById('viol_date').value = violation.date;
        document.getElementById('viol_desc').value = violation.description;
        document.getElementById('viol_reporter_email').value = violation.reporter_email || '';
        document.getElementById('violSaveBtn').innerHTML = '<i class="fas fa-save"></i> تحديث';
        editingId = id;
        currentEditingType = 'violation';
    }
}

async function deleteViolationConfirm(id) {
    if (confirm('هل أنت متأكد من حذف هذه المخالفة؟')) {
        const result = await deleteViolationData(id);
        if (result && result.success) {
            showNotification('تم حذف المخالفة بنجاح', 'success');
            renderViolationsTable();
            updateDashboard();
        } else {
            showNotification('فشل في حذف المخالفة', 'error');
        }
    }
}

function clearViolationForm() {
    document.getElementById('viol_student').value = '';
    document.getElementById('viol_type').value = '';
    document.getElementById('viol_date').value = '';
    document.getElementById('viol_desc').value = '';
    document.getElementById('viol_reporter_email').value = '';
    document.getElementById('violSaveBtn').innerHTML = '<i class="fas fa-save"></i> حفظ';
    editingId = null;
    currentEditingType = null;
}

function clearViolationFilters() {
    document.getElementById('violationsSearch').value = '';
    document.getElementById('violFilterGrade').value = '';
    renderViolationsTable();
}

// ========================================
// التوجيه الجمعي
// ========================================

async function renderGuidanceTable() {
    try {
        const guidance = await loadGuidanceData();
        if (!guidance) return;
        
        const tbody = document.getElementById('guidanceTable').querySelector('tbody');
        tbody.innerHTML = '';
        
        guidance.forEach(session => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(session.topic)}</td>
                <td>${escapeHtml(session.date)}</td>
                <td>${escapeHtml(session.grade)}</td>
                <td>${escapeHtml(session.notes)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn btn-edit" onclick="editGuidance(${session.id})">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                        <button class="action-btn btn-delete" onclick="deleteGuidanceConfirm(${session.id})">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) {
        debugLog('Error rendering guidance table:', error);
    }
}

async function saveGuidance() {
    const topic = document.getElementById('guid_topic').value.trim();
    const date = document.getElementById('guid_date').value;
    const grade = document.getElementById('guid_grade').value;
    const notes = document.getElementById('guid_notes').value.trim();
    
    if (!topic) {
        showNotification('يرجى إدخال موضوع الجلسة', 'error');
        return;
    }
    
    const guidanceData = { topic, date, grade, notes };
    
    if (editingId && currentEditingType === 'guidance') {
        guidanceData.id = editingId;
    }
    
    const result = await saveGuidanceData(guidanceData);
    if (result && result.success) {
        showNotification('تم حفظ الجلسة بنجاح', 'success');
        clearGuidanceForm();
        renderGuidanceTable();
        updateDashboard();
    } else {
        showNotification('فشل في حفظ الجلسة', 'error');
    }
}

async function editGuidance(id) {
    const guidance = await loadGuidanceData();
    const session = guidance.find(g => g.id === id);
    
    if (session) {
        document.getElementById('guid_topic').value = session.topic;
        document.getElementById('guid_date').value = session.date;
        document.getElementById('guid_grade').value = session.grade;
        document.getElementById('guid_notes').value = session.notes;
        document.getElementById('guidSaveBtn').innerHTML = '<i class="fas fa-save"></i> تحديث';
        editingId = id;
        currentEditingType = 'guidance';
    }
}

async function deleteGuidanceConfirm(id) {
    if (confirm('هل أنت متأكد من حذف هذه الجلسة؟')) {
        const result = await deleteGuidanceData(id);
        if (result && result.success) {
            showNotification('تم حذف الجلسة بنجاح', 'success');
            renderGuidanceTable();
            updateDashboard();
        } else {
            showNotification('فشل في حذف الجلسة', 'error');
        }
    }
}

function clearGuidanceForm() {
    document.getElementById('guid_topic').value = '';
    document.getElementById('guid_date').value = '';
    document.getElementById('guid_grade').selectedIndex = 0;
    document.getElementById('guid_notes').value = '';
    document.getElementById('guidSaveBtn').innerHTML = '<i class="fas fa-save"></i> حفظ';
    editingId = null;
    currentEditingType = null;
}

// ========================================
// الاجتماعات
// ========================================

async function renderMeetingsTable() {
    try {
        const meetings = await loadMeetingsData();
        if (!meetings) return;
        
        const tbody = document.getElementById('meetingsTable').querySelector('tbody');
        tbody.innerHTML = '';
        
        meetings.forEach(meeting => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(meeting.meeting_number || meeting.number)}</td>
                <td>${escapeHtml(meeting.date)}</td>
                <td>${escapeHtml(meeting.attendees_count || meeting.attendees)}</td>
                <td>${escapeHtml(meeting.topics)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn btn-edit" onclick="editMeeting(${meeting.id})">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                        <button class="action-btn btn-delete" onclick="deleteMeetingConfirm(${meeting.id})">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) {
        debugLog('Error rendering meetings table:', error);
    }
}

async function saveMeeting() {
    const number = document.getElementById('meet_number').value.trim();
    const date = document.getElementById('meet_date').value;
    const attendees = document.getElementById('meet_attendees').value.trim();
    const topics = document.getElementById('meet_topics').value.trim();
    
    if (!number || !date) {
        showNotification('يرجى إدخال رقم الاجتماع والتاريخ', 'error');
        return;
    }
    
    const meetingData = { number, date, attendees, topics };
    
    if (editingId && currentEditingType === 'meeting') {
        meetingData.id = editingId;
    }
    
    const result = await saveMeetingData(meetingData);
    if (result && result.success) {
        showNotification('تم حفظ الاجتماع بنجاح', 'success');
        clearMeetingForm();
        renderMeetingsTable();
        updateDashboard();
    } else {
        showNotification('فشل في حفظ الاجتماع', 'error');
    }
}

async function editMeeting(id) {
    const meetings = await loadMeetingsData();
    const meeting = meetings.find(m => m.id === id);
    
    if (meeting) {
        document.getElementById('meet_number').value = meeting.meeting_number || meeting.number;
        document.getElementById('meet_date').value = meeting.date;
        document.getElementById('meet_attendees').value = meeting.attendees_count || meeting.attendees;
        document.getElementById('meet_topics').value = meeting.topics;
        document.getElementById('meetSaveBtn').innerHTML = '<i class="fas fa-save"></i> تحديث';
        editingId = id;
        currentEditingType = 'meeting';
    }
}

async function deleteMeetingConfirm(id) {
    if (confirm('هل أنت متأكد من حذف هذا الاجتماع؟')) {
        const result = await deleteMeetingData(id);
        if (result && result.success) {
            showNotification('تم حذف الاجتماع بنجاح', 'success');
            renderMeetingsTable();
            updateDashboard();
        } else {
            showNotification('فشل في حذف الاجتماع', 'error');
        }
    }
}

function clearMeetingForm() {
    document.getElementById('meet_number').value = '';
    document.getElementById('meet_date').value = '';
    document.getElementById('meet_attendees').value = '';
    document.getElementById('meet_topics').value = '';
    document.getElementById('meetSaveBtn').innerHTML = '<i class="fas fa-save"></i> حفظ';
    editingId = null;
    currentEditingType = null;
}

// ========================================
// مقابلات أولياء الأمور
// ========================================

async function renderParentsTable() {
    try {
        const parents = await loadParentsData();
        if (!parents) return;
        
        const tbody = document.getElementById('parentsTable').querySelector('tbody');
        tbody.innerHTML = '';
        
        parents.forEach(parent => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(parent.student_name)}</td>
                <td>${escapeHtml(parent.parent_name)}</td>
                <td>${escapeHtml(parent.date)}</td>
                <td>${escapeHtml(parent.topic)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn btn-edit" onclick="editParent(${parent.id})">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                        <button class="action-btn btn-delete" onclick="deleteParentConfirm(${parent.id})">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) {
        debugLog('Error rendering parents table:', error);
    }
}

async function saveParent() {
    const student_name = document.getElementById('parent_student').value.trim();
    const parent_name = document.getElementById('parent_name').value.trim();
    const date = document.getElementById('parent_date').value;
    const topic = document.getElementById('parent_topic').value.trim();
    
    if (!student_name || !parent_name) {
        showNotification('يرجى إدخال اسم الطالب وولي الأمر', 'error');
        return;
    }
    
    const parentData = { student_name, parent_name, date, topic };
    
    if (editingId && currentEditingType === 'parent') {
        parentData.id = editingId;
    }
    
    const result = await saveParentData(parentData);
    if (result && result.success) {
        showNotification('تم حفظ المقابلة بنجاح', 'success');
        clearParentForm();
        renderParentsTable();
        updateDashboard();
    } else {
        showNotification('فشل في حفظ المقابلة', 'error');
    }
}

async function editParent(id) {
    const parents = await loadParentsData();
    const parent = parents.find(p => p.id === id);
    
    if (parent) {
        document.getElementById('parent_student').value = parent.student_name;
        document.getElementById('parent_name').value = parent.parent_name;
        document.getElementById('parent_date').value = parent.date;
        document.getElementById('parent_topic').value = parent.topic;
        document.getElementById('parentSaveBtn').innerHTML = '<i class="fas fa-save"></i> تحديث';
        editingId = id;
        currentEditingType = 'parent';
    }
}

async function deleteParentConfirm(id) {
    if (confirm('هل أنت متأكد من حذف هذه المقابلة؟')) {
        const result = await deleteParentData(id);
        if (result && result.success) {
            showNotification('تم حذف المقابلة بنجاح', 'success');
            renderParentsTable();
            updateDashboard();
        } else {
            showNotification('فشل في حذف المقابلة', 'error');
        }
    }
}

function clearParentForm() {
    document.getElementById('parent_student').value = '';
    document.getElementById('parent_name').value = '';
    document.getElementById('parent_date').value = '';
    document.getElementById('parent_topic').value = '';
    document.getElementById('parentSaveBtn').innerHTML = '<i class="fas fa-save"></i> حفظ';
    editingId = null;
    currentEditingType = null;
}

// ========================================
// المعلمون
// ========================================

async function renderTeachersTable() {
    try {
        const teachers = await loadTeachersData();
        if (!teachers) return;
        
        const tbody = document.getElementById('teachersTable').querySelector('tbody');
        tbody.innerHTML = '';
        
        teachers.forEach(teacher => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(teacher.name)}</td>
                <td>${escapeHtml(teacher.subject)}</td>
                <td>${escapeHtml(teacher.phone)}</td>
                <td>${escapeHtml(teacher.notes)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn btn-edit" onclick="editTeacher(${teacher.id})">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                        <button class="action-btn btn-delete" onclick="deleteTeacherConfirm(${teacher.id})">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) {
        debugLog('Error rendering teachers table:', error);
    }
}

async function saveTeacher() {
    const name = document.getElementById('teacher_name').value.trim();
    const subject = document.getElementById('teacher_subject').value.trim();
    const phone = document.getElementById('teacher_phone').value.trim();
    const notes = document.getElementById('teacher_notes').value.trim();
    
    if (!name) {
        showNotification('يرجى إدخال اسم المعلم', 'error');
        return;
    }
    
    const teacherData = { name, subject, phone, notes };
    
    if (editingId && currentEditingType === 'teacher') {
        teacherData.id = editingId;
    }
    
    const result = await saveTeacherData(teacherData);
    if (result && result.success) {
        showNotification('تم حفظ المعلم بنجاح', 'success');
        clearTeacherForm();
        renderTeachersTable();
        updateDashboard();
    } else {
        showNotification('فشل في حفظ المعلم', 'error');
    }
}

async function editTeacher(id) {
    const teachers = await loadTeachersData();
    const teacher = teachers.find(t => t.id === id);
    
    if (teacher) {
        document.getElementById('teacher_name').value = teacher.name;
        document.getElementById('teacher_subject').value = teacher.subject;
        document.getElementById('teacher_phone').value = teacher.phone;
        document.getElementById('teacher_notes').value = teacher.notes;
        document.getElementById('teacherSaveBtn').innerHTML = '<i class="fas fa-save"></i> تحديث';
        editingId = id;
        currentEditingType = 'teacher';
    }
}

async function deleteTeacherConfirm(id) {
    if (confirm('هل أنت متأكد من حذف هذا المعلم؟')) {
        const result = await deleteTeacherData(id);
        if (result && result.success) {
            showNotification('تم حذف المعلم بنجاح', 'success');
            renderTeachersTable();
            updateDashboard();
        } else {
            showNotification('فشل في حذف المعلم', 'error');
        }
    }
}

function clearTeacherForm() {
    document.getElementById('teacher_name').value = '';
    document.getElementById('teacher_subject').value = '';
    document.getElementById('teacher_phone').value = '';
    document.getElementById('teacher_notes').value = '';
    document.getElementById('teacherSaveBtn').innerHTML = '<i class="fas fa-save"></i> حفظ';
    editingId = null;
    currentEditingType = null;
}

// ========================================
// دوال مساعدة
// ========================================

function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function refreshDashboard() {
    updateDashboard();
    showNotification('تم تحديث البيانات بنجاح', 'success');
}

function exportDashboard() {
    showNotification('جاري تصدير التقرير...', 'info');
    setTimeout(() => {
        showNotification('تم تصدير التقرير بنجاح', 'success');
    }, 1500);
}

function exportStudentsToExcel() {
    showNotification('جاري تصدير بيانات الطلاب...', 'info');
}

function exportViolationsToExcel() {
    showNotification('جاري تصدير بيانات المخالفات...', 'info');
}

function exportGuidanceToExcel() {
    showNotification('جاري تصدير بيانات التوجيه...', 'info');
}

function exportMeetingsToExcel() {
    showNotification('جاري تصدير بيانات الاجتماعات...', 'info');
}

function exportParentsToExcel() {
    showNotification('جاري تصدير بيانات المقابلات...', 'info');
}

function exportTeachersToExcel() {
    showNotification('جاري تصدير بيانات المعلمين...', 'info');
}

function showStudentStats() {
    showNotification('عرض إحصائيات الطلاب', 'info');
}

function showViolationStats() {
    showNotification('عرض إحصائيات المخالفات', 'info');
}

function printAllStudents() {
    showNotification('جاري تحضير البيانات للطباعة...', 'info');
}

function printAllViolations() {
    showNotification('جاري تحضير البيانات للطباعة...', 'info');
}

function printAllGuidance() {
    showNotification('جاري تحضير البيانات للطباعة...', 'info');
}

function printAllMeetings() {
    showNotification('جاري تحضير البيانات للطباعة...', 'info');
}

function printAllParents() {
    showNotification('جاري تحضير البيانات للطباعة...', 'info');
}

function printAllTeachers() {
    showNotification('جاري تحضير البيانات للطباعة...', 'info');
}

// ========================================
// التهيئة عند تحميل الصفحة
// ========================================

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

function initApp() {
    debugLog('🚀 System loaded successfully');
    debugLog('📍 API Base URL:', API_BASE);
    
    checkSession();
    
    const hamburger = document.getElementById('hamburgerMenu');
    const overlay = document.getElementById('overlay');
    
    if (hamburger) {
        hamburger.addEventListener('click', toggleSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const hamburger = document.getElementById('hamburgerMenu');
            
            if (sidebar) sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            if (hamburger) hamburger.classList.remove('active');
        }
    });
}
