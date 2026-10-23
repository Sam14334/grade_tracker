// Tab Switcher (Login / Register)
function switchTab(tab) {
    const loginSection = document.getElementById('loginSection');
    const registerForm = document.getElementById('registerForm');
    const forgotSection = document.getElementById('forgotPasswordSection');
    const mainNavTabs = document.getElementById('mainNavTabs');
    
    hideAlert();
    forgotSection.classList.add('hidden');
    mainNavTabs.classList.remove('hidden');

    if (tab === 'login') {
        loginSection.classList.remove('hidden');
        registerForm.classList.add('hidden');
        document.getElementById('loginTabBtn').className = "flex-1 py-2.5 text-xs sm:text-sm font-bold rounded-2xl transition-all text-maroon-800 bg-white shadow-sm flex items-center justify-center gap-2 cursor-pointer";
        document.getElementById('registerTabBtn').className = "flex-1 py-2.5 text-xs sm:text-sm font-semibold rounded-2xl transition-all text-slate-500 hover:text-slate-800 flex items-center justify-center gap-2 cursor-pointer";
    } else {
        registerForm.classList.remove('hidden');
        loginSection.classList.add('hidden');
        document.getElementById('registerTabBtn').className = "flex-1 py-2.5 text-xs sm:text-sm font-bold rounded-2xl transition-all text-maroon-800 bg-white shadow-sm flex items-center justify-center gap-2 cursor-pointer";
        document.getElementById('loginTabBtn').className = "flex-1 py-2.5 text-xs sm:text-sm font-semibold rounded-2xl transition-all text-slate-500 hover:text-slate-800 flex items-center justify-center gap-2 cursor-pointer";
    }
}

// Forgot Password Section Toggles
function showForgotPassword() {
    hideAlert();
    document.getElementById('loginSection').classList.add('hidden');
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById('mainNavTabs').classList.add('hidden');
    document.getElementById('forgotPasswordSection').classList.remove('hidden');
}

function hideForgotPassword() {
    hideAlert();
    document.getElementById('forgotPasswordSection').classList.add('hidden');
    document.getElementById('mainNavTabs').classList.remove('hidden');
    switchTab('login');
}

function handleForgotPassword(e) {
    e.preventDefault();
    const identifier = document.getElementById('resetIdentifier').value.trim();
    showAlert(`Password reset link sent to ${identifier} if registered.`, 'success');
    document.getElementById('forgotPasswordForm').reset();
}

// Student/Professor Role Switcher (Login)
function selectRole(role) {
    const studentBtn = document.getElementById('studentRoleBtn');
    const professorBtn = document.getElementById('professorRoleBtn');
    const roleInput = document.getElementById('userRoleInput');
    const emailLabel = document.getElementById('loginEmailLabel');
    const emailIcon = document.getElementById('loginEmailIcon');
    const emailInput = document.getElementById('loginEmail');
    const signInBtnText = document.getElementById('signInBtnText');

    roleInput.value = role;

    if (role === 'student') {
        studentBtn.className = "flex-1 py-2 text-xs font-bold rounded-lg transition-all text-maroon-900 bg-white shadow-sm flex items-center justify-center gap-1.5 cursor-pointer";
        professorBtn.className = "flex-1 py-2 text-xs font-semibold rounded-lg transition-all text-slate-500 hover:text-slate-800 flex items-center justify-center gap-1.5 cursor-pointer";
        emailLabel.textContent = "Student Email (@iskolarngbayan.pup.edu.ph)";
        emailIcon.className = "fa-solid fa-user-graduate absolute left-4 top-3.5 text-slate-400 text-sm";
        emailInput.placeholder = "e.g. name@iskolarngbayan.pup.edu.ph";
        signInBtnText.textContent = "Sign In as Student";
    } else {
        professorBtn.className = "flex-1 py-2 text-xs font-bold rounded-lg transition-all text-maroon-900 bg-white shadow-sm flex items-center justify-center gap-1.5 cursor-pointer";
        studentBtn.className = "flex-1 py-2 text-xs font-semibold rounded-lg transition-all text-slate-500 hover:text-slate-800 flex items-center justify-center gap-1.5 cursor-pointer";
        emailLabel.textContent = "Faculty Email (@pup.edu.ph)";
        emailIcon.className = "fa-solid fa-chalkboard-user absolute left-4 top-3.5 text-slate-400 text-sm";
        emailInput.placeholder = "e.g. name@pup.edu.ph";
        signInBtnText.textContent = "Sign In as Professor";
    }
}

// Registration Role Change Helper
function handleRegRoleChange(role) {
    const emailInput = document.getElementById('regEmail');
    const domainSpan = document.getElementById('regEmailDomainRequired');
    if (role === 'professor') {
        emailInput.placeholder = "e.g. name@pup.edu.ph";
        domainSpan.textContent = "@pup.edu.ph";
    } else {
        emailInput.placeholder = "e.g. name@iskolarngbayan.pup.edu.ph";
        domainSpan.textContent = "@iskolarngbayan.pup.edu.ph";
    }
}

// Login Form Validation
function handleLoginValidation(e) {
    const email = document.getElementById('loginEmail').value.trim().toLowerCase();
    const role = document.getElementById('userRoleInput').value;

    const parts = email.split('@');
    if (parts.length !== 2 || !parts[0] || !parts[1]) {
        e.preventDefault();
        showAlert('Please enter a valid email address.', 'error');
        return false;
    }

    const domain = parts[1];
    if (role === 'student' && domain !== 'iskolarngbayan.pup.edu.ph') {
        e.preventDefault();
        showAlert('Student sign-in requires an official @iskolarngbayan.pup.edu.ph email address. Other domains (e.g. Gmail) are not accepted.', 'error');
        return false;
    }

    if (role === 'professor' && domain !== 'pup.edu.ph') {
        e.preventDefault();
        showAlert('Professor sign-in requires an official @pup.edu.ph email address. Other domains (e.g. Gmail) are not accepted.', 'error');
        return false;
    }

    hideAlert();
    return true;
}

// Registration Form Validation
function handleRegister(e) {
    const email = document.getElementById('regEmail').value.trim().toLowerCase();
    const role = document.getElementById('regRole').value;
    const pwd = document.getElementById('regPassword').value;
    const confirmPwd = document.getElementById('regConfirmPassword').value;

    const parts = email.split('@');
    if (parts.length !== 2 || !parts[0] || !parts[1]) {
        e.preventDefault();
        showAlert('Please enter a valid email address.', 'error');
        return false;
    }

    const domain = parts[1];
    if (role === 'student' && domain !== 'iskolarngbayan.pup.edu.ph') {
        e.preventDefault();
        showAlert('Student accounts must use an official @iskolarngbayan.pup.edu.ph email address. (Gmail and other providers are not accepted)', 'error');
        return false;
    }

    if (role === 'professor' && domain !== 'pup.edu.ph') {
        e.preventDefault();
        showAlert('Professor accounts must use an official @pup.edu.ph email address. (Gmail and other providers are not accepted)', 'error');
        return false;
    }

    if (pwd !== confirmPwd) {
        e.preventDefault();
        showAlert('Passwords do not match. Please try again.', 'error');
        return false;
    }

    if (pwd.length < 6) {
        e.preventDefault();
        showAlert('Password must be at least 6 characters long.', 'error');
        return false;
    }

    hideAlert();
    return true;
}

// Toggle Password Show/Hide
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-solid fa-eye-slash text-sm';
    } else {
        input.type = 'password';
        icon.className = 'fa-solid fa-eye text-sm';
    }
}

// Alert Helper Functions
function showAlert(msg, type = 'error') {
    const alertBox = document.getElementById('authAlert');
    const alertIcon = document.getElementById('alertIcon');
    const alertMessage = document.getElementById('alertMessage');

    alertMessage.textContent = msg;
    alertBox.classList.remove('hidden', 'bg-rose-50', 'text-rose-800', 'bg-emerald-50', 'text-emerald-800', 'border-rose-200', 'border-emerald-200');

    if (type === 'error') {
        alertBox.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
        alertIcon.className = 'fa-solid fa-circle-exclamation mt-0.5 text-sm text-rose-600';
    } else {
        alertBox.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
        alertIcon.className = 'fa-solid fa-circle-check mt-0.5 text-sm text-emerald-600';
    }
}

function hideAlert() {
    document.getElementById('authAlert').classList.add('hidden');
}

document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('registered') === 'success') {
        showAlert('Account registered successfully! You can now log in.', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (params.get('error')) {
        showAlert(decodeURIComponent(params.get('error')), 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});