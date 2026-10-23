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

    const urlParams = new URLSearchParams(window.location.search);
    const emailParam = urlParams.get('email');
    const displayEmail = document.getElementById('displayEmail');
    if (emailParam && displayEmail) {
        displayEmail.textContent = emailParam;
    }

    if (inputs.length > 0) inputs[0].focus();
});

// submit otp code to backend
async function handleOtpVerification(e) {
    e.preventDefault();
    const inputs = document.querySelectorAll('.otp-input');
    let otpCode = '';
    inputs.forEach(input => otpCode += input.value.trim());

    if (otpCode.length !== 6) {
        showOtpAlert('Please enter all 6 digits of the verification code.', 'error');
        return;
    }

    const email = new URLSearchParams(window.location.search).get('email') || '';

    try {
        const formData = new FormData();
        formData.append('email', email);
        formData.append('otp', otpCode);

        const res = await fetch('php/verify_otp.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            showOtpAlert('Account verified successfully! Redirecting to login...', 'success');
            setTimeout(() => {
                window.location.href = data.redirect || 'index.html?registered=success';
            }, 1200);
        } else {
            showOtpAlert(data.error || 'Invalid verification code.', 'error');
        }
    } catch (err) {
        showOtpAlert('Failed to connect to verification server.', 'error');
    }
}

// request new otp via resend
async function resendOtpCode() {
    const email = new URLSearchParams(window.location.search).get('email') || '';

    if (!email) {
        showOtpAlert('Email not found. Please register again.', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('email', email);

        const res = await fetch('php/resend_otp.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            showOtpAlert(data.message || 'A new verification code has been sent to your email.', 'success');
        } else {
            showOtpAlert(data.error || 'Failed to resend code.', 'error');
        }
    } catch (err) {
        showOtpAlert('Failed to request new code. Try again later.', 'error');
    }
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