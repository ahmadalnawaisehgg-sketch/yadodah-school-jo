// ===== إعدادات عامة =====
const API_BASE = '/api/';
let currentTheme = localStorage.getItem('theme') || 'light';
let currentLang = localStorage.getItem('lang') || 'ar';

// ===== تطبيق الثيم عند تحميل الصفحة =====
document.addEventListener('DOMContentLoaded', () => {
  applyTheme();
  applyLanguage();
  animateCounters();
  setupScrollAnimation();
});

// ===== تبديل الثيم =====
function toggleTheme() {
  currentTheme = currentTheme === 'light' ? 'dark' : 'light';
  localStorage.setItem('theme', currentTheme);
  applyTheme();
}

function applyTheme() {
  if (currentTheme === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    document.getElementById('themeIcon').className = 'fas fa-sun';
  } else {
    document.documentElement.removeAttribute('data-theme');
    document.getElementById('themeIcon').className = 'fas fa-moon';
  }
}

// ===== تبديل اللغة =====
function toggleLanguage() {
  currentLang = currentLang === 'ar' ? 'en' : 'ar';
  localStorage.setItem('lang', currentLang);
  applyLanguage();
}

function applyLanguage() {
  if (currentLang === 'en') {
    document.documentElement.setAttribute('lang', 'en');
    document.documentElement.setAttribute('dir', 'ltr');
    document.getElementById('langText').textContent = 'العربية';
  } else {
    document.documentElement.setAttribute('lang', 'ar');
    document.documentElement.setAttribute('dir', 'rtl');
    document.getElementById('langText').textContent = 'English';
  }
}

// ===== عرض نافذة تسجيل دخول أولياء الأمور =====
function showParentLogin() {
  document.getElementById('parentLoginModal').classList.add('active');
}

function hideParentLogin() {
  document.getElementById('parentLoginModal').classList.remove('active');
}

// إغلاق النافذة عند النقر خارجها
document.getElementById('parentLoginModal')?.addEventListener('click', (e) => {
  if (e.target.id === 'parentLoginModal') {
    hideParentLogin();
  }
});

// ===== تسجيل دخول ولي الأمر =====
async function parentLogin() {
  const email = document.getElementById('parentEmail').value.trim();
  const messageDiv = document.getElementById('loginMessage');
  
  if (!email) {
    showMessage('يرجى إدخال البريد الإلكتروني', 'danger');
    return;
  }

  if (!isValidEmail(email)) {
    showMessage('البريد الإلكتروني غير صحيح', 'danger');
    return;
  }

  try {
    const response = await fetch(`${API_BASE}parent_auth.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'parent_login',
        email: email
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('Received non-JSON response:', text.substring(0, 200));
      throw new Error('السيرفر أرجع استجابة غير صحيحة');
    }

    const data = await response.json();

    if (data.success) {
      showMessage('تم تسجيل الدخول بنجاح! جاري التحويل...', 'success');
      setTimeout(() => {
        window.location.href = 'parent_portal.html';
      }, 1500);
    } else {
      showMessage(data.error || 'فشل تسجيل الدخول', 'danger');
    }
  } catch (error) {
    console.error('Login error:', error);
    showMessage('حدث خطأ في الاتصال بالخادم', 'danger');
  }
}

function showMessage(text, type) {
  const messageDiv = document.getElementById('loginMessage');
  messageDiv.textContent = text;
  messageDiv.className = `alert alert-${type}`;
  messageDiv.style.display = 'block';
  
  setTimeout(() => {
    messageDiv.style.display = 'none';
  }, 5000);
}

function isValidEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

// ===== التمرير السلس =====
function scrollToFeatures() {
  document.getElementById('features').scrollIntoView({ 
    behavior: 'smooth',
    block: 'start'
  });
}

// ===== تحريك العدادات =====
function animateCounters() {
  const counters = document.querySelectorAll('.counter');
  const speed = 200;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const counter = entry.target;
        const target = +counter.getAttribute('data-target');
        
        const updateCount = () => {
          const count = +counter.innerText;
          const increment = target / speed;

          if (count < target) {
            counter.innerText = Math.ceil(count + increment);
            setTimeout(updateCount, 10);
          } else {
            counter.innerText = target;
          }
        };

        updateCount();
        observer.unobserve(counter);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(counter => observer.observe(counter));
}

// ===== تحريك العناصر عند التمرير =====
function setupScrollAnimation() {
  const animateElements = document.querySelectorAll('.feature-card');
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }, index * 100);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  animateElements.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'all 0.6s ease';
    observer.observe(el);
  });
}

// ===== مفاتيح الاختصار =====
document.addEventListener('keydown', (e) => {
  // ESC لإغلاق النافذة المنبثقة
  if (e.key === 'Escape') {
    hideParentLogin();
  }
  
  // Enter في حقل البريد الإلكتروني
  if (e.key === 'Enter' && document.activeElement.id === 'parentEmail') {
    parentLogin();
  }
});
