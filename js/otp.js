document.addEventListener("DOMContentLoaded", () => {
    const inputs = document.querySelectorAll('.otp-input');

    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const value = e.target.value;
            if (value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });

    if (inputs.length > 0) inputs[0].focus();
});

function handleOtpVerification(e) {
    e.preventDefault();
    const inputs = document.querySelectorAll('.otp-input');
    let otpCode = '';
    inputs.forEach(input => otpCode += input.value.trim());

    if (otpCode.length !== 6) {
        showOtpAlert('Please enter all 6 digits of the verification code.', 'error');
        return;
    }

    // Verification check (Demo code: 123456)
    if (otpCode === '123456') {
        showOtpAlert('Verification successful! Redirecting to your dashboard...', 'success');
        
        const targetRole = localStorage.getItem('pendingUserRole') || 'student';
        
        setTimeout(() => {
            if (targetRole === 'professor') {
                window.location.href = 'professor_dashboard.php';
            } else {
                window.location.href = 'student_dashboard.php';
            }
        }, 1200);
    } else {
        showOtpAlert('Invalid verification code. Please use code 123456.', 'error');
    }
}

function resendOtpCode() {
    showOtpAlert('A new verification code has been sent to your email.', 'success');
}

function showOtpAlert(msg, type = 'error') {
    const alertBox = document.getElementById('otpAlert');
    const alertIcon = document.getElementById('otpAlertIcon');
    const alertMessage = document.getElementById('otpAlertMessage');

    if (!alertBox) return;

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