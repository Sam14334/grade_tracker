<?php
// login.php
session_start();
include 'db.php';

if (isset($_POST['btnLogin'])) {
    $email = strtolower(trim($_POST['loginEmail'] ?? $_POST['loginUsername'] ?? ''));
    $password = $_POST['loginPassword'] ?? '';
    $selectedRole = strtolower(trim($_POST['userRole'] ?? 'student'));

    if (empty($email) || empty($password)) {
        header("Location: ../index.html?error=" . urlencode("Please enter both email and password."));
        exit();
    }

    // 1. Strict domain check based on selected role tab
    $emailParts = explode('@', $email);
    $domain = end($emailParts);

    if ($selectedRole === 'professor') {
        if ($domain !== 'pup.edu.ph') {
            header("Location: ../index.html?error=" . urlencode("Professor sign-in requires an official @pup.edu.ph email address."));
            exit();
        }
    } else {
        if ($domain !== 'iskolarngbayan.pup.edu.ph') {
            header("Location: ../index.html?error=" . urlencode("Student sign-in requires an official @iskolarngbayan.pup.edu.ph email address."));
            exit();
        }
    }

    // 2. Find user by email and join roles
    $stmt = mysqli_prepare($conn, "SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($res)) {
        // 3. Check password
        if (password_verify($password, $user['password_hash'])) {
            $roleName = $user['role_name']; // 'Professor' or 'Student'
            $roleId = (int)$user['role_id'];

            $isProfessor = ($roleId === 2 || strcasecmp($roleName, 'Professor') === 0 || strcasecmp($roleName, 'Teacher') === 0);

            // Double check role alignment
            if ($selectedRole === 'professor' && !$isProfessor) {
                header("Location: ../index.html?error=" . urlencode("This account is registered as a Student. Please switch to the Student tab to sign in."));
                exit();
            }
            if ($selectedRole === 'student' && $isProfessor) {
                header("Location: ../index.html?error=" . urlencode("This account is registered as a Professor. Please switch to the Professor tab to sign in."));
                exit();
            }

            // 4. Store session data
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $roleName;
            $_SESSION['full_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['email'];
            $_SESSION['role_id'] = $roleId;

            // 5. Role-based redirect
            if ($isProfessor) {
                header("Location: ../professor_dashboard.php");
            } else {
                header("Location: ../student_dashboard.php");
            }
            exit();
        } else {
            header("Location: ../index.html?error=" . urlencode("Invalid password!"));
            exit();
        }
    } else {
        header("Location: ../index.html?error=" . urlencode("No account found with this email address."));
        exit();
    }
}
?>