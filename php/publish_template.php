<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
if (!((isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 2) ||
    (isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'Professor') === 0))) {
    header('Location: ../student_dashboard.php');
    exit();
}
require_once __DIR__ . '/course_components.php';
require_once __DIR__ . '/grade_calculator.php';
require_once __DIR__ . '/template_form.php';

$templateId = filter_var($_POST['template_id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$returnUrl = '../professor_dashboard.php' . ($templateId > 0 ? '?edit=' . $templateId . '&' : '?');
$form = templateFormInput($_POST);
$formVerified = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['components_complete']) &&
    isset($_SESSION['score_csrf_token']) && is_string($_POST['csrf_token'] ?? null) &&
    hash_equals($_SESSION['score_csrf_token'], $_POST['csrf_token']);
$error = !$formVerified ? 'Your form expired or was incomplete. Reload and try again.'
    : ($templateId === false ? 'Invalid template.' : templateFormError($form));
if ($error !== '') {
    if ($formVerified) $_SESSION['template_form_input'] = ['id' => $templateId, 'form' => $form];
    header('Location: ' . $returnUrl . 'error=' . urlencode($error) . '#templates');
    exit();
}

require __DIR__ . '/db.php';
$userId = (int)$_SESSION['user_id'];
$isEditing = $templateId > 0;
try {
    $conn->beginTransaction();
    if ($isEditing) {
        $owner = $conn->prepare('SELECT template_id FROM grade_templates WHERE template_id = ? AND user_id = ? FOR UPDATE');
        $owner->execute([$templateId, $userId]);
        if (!$owner->fetch()) throw new DomainException('This template is no longer available to edit.');
        $stmt = $conn->prepare('UPDATE grade_templates SET course_code = ?, course_title = ?, course_unit = ? WHERE template_id = ? AND user_id = ?');
        $stmt->execute([$form['courseCode'], $form['courseTitle'], $form['courseUnit'], $templateId, $userId]);
    } else {
        $codePrefix = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $form['courseCode'])) . '-SEC';
        $stmt = $conn->prepare('INSERT INTO grade_templates (user_id, course_code, course_title, class_code, course_unit)
            VALUES (?, ?, ?, ?, ?) ON CONFLICT (class_code) DO NOTHING RETURNING template_id');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $classCode = $codePrefix . random_int(100000, 999999);
            $stmt->execute([$userId, $form['courseCode'], $form['courseTitle'], $classCode, $form['courseUnit']]);
            $newId = $stmt->fetchColumn();
            if ($newId !== false) {
                $templateId = (int)$newId;
                break;
            }
        }
        if ($templateId === 0) throw new DomainException('Could not generate an available enrollment code. Please try publishing again.');
    }

    foreach (componentTables() as $period => $table) {
        $existing = [];
        if ($isEditing) {
            // Keep component IDs stable so their saved scores stay attached.
            $stmt = $conn->prepare("SELECT id FROM $table WHERE template_id = ? ORDER BY id FOR UPDATE");
            $stmt->execute([$templateId]);
            $existing = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
        }
        $retained = [];
        foreach ($form['components'][$period] as $component) {
            if ($component['id'] === '') continue;
            $id = (int)$component['id'];
            if (!isset($existing[$id])) throw new DomainException('A component does not belong to this template or grading period. Reload and try again.');
            $retained[$id] = true;
        }
        $removed = array_diff_key($existing, $retained);
        $usedIds = $removed ? array_fill_keys(loadUsedTemplateComponentIds($conn, $templateId, $period), true) : [];
        foreach ($removed as $id => $_) {
            // Do not let the foreign-key cascade erase saved scores.
            if (isset($usedIds[$id])) {
                throw new DomainException('A removed component has saved student scores. Keep that component; you can still edit its name, weight, and exam setting.');
            }
            $stmt = $conn->prepare("DELETE FROM $table WHERE id = ? AND template_id = ?");
            $stmt->execute([$id, $templateId]);
        }
        // Clear the old exam first so switching it does not violate the one-exam index.
        if ($isEditing) {
            $stmt = $conn->prepare("UPDATE $table SET is_exam = FALSE WHERE template_id = ?");
            $stmt->execute([$templateId]);
        }
        $insert = $conn->prepare("INSERT INTO $table (template_id, component_name, weight, is_exam) VALUES (?, ?, ?, ?)");
        $update = $conn->prepare("UPDATE $table SET component_name = ?, weight = ?, is_exam = ? WHERE id = ? AND template_id = ?");
        foreach ($form['components'][$period] as $component) {
            $values = [$component['name'], (int)$component['weight'], $component['is_exam'] ? 'true' : 'false'];
            if ($component['id'] !== '') $update->execute([...$values, (int)$component['id'], $templateId]);
            else $insert->execute([$templateId, ...$values]);
        }
    }
    if (!$isEditing) {
        $stmt = $conn->prepare('INSERT INTO enrolled_courses (user_id, template_id) VALUES (?, ?) ON CONFLICT DO NOTHING');
        $stmt->execute([$userId, $templateId]);
    }
    $conn->commit();
    unset($_SESSION['template_form_input']);
    header('Location: ../professor_dashboard.php?template=' . ($isEditing ? 'updated' : 'success&code=' . urlencode($classCode)) . '#templates');
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Template save failed: ' . $e->getMessage());
    $_SESSION['template_form_input'] = ['id' => $isEditing ? $templateId : 0, 'form' => $form];
    $error = $e instanceof DomainException ? $e->getMessage() : 'Could not save the template. Your changes have not been applied. Please try again.';
    header('Location: ' . $returnUrl . 'error=' . urlencode($error) . '#templates');
}
exit();
