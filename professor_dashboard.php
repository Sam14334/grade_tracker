<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Enforce professor role access
if (!((isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 2) ||
    (isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'Professor') === 0))) {
    header("Location: student_dashboard.php");
    exit();
}
include 'php/db.php';
require_once 'php/grade_calculator.php';
require_once 'php/course_components.php';
require_once 'php/template_form.php';
if (!isset($_SESSION['score_csrf_token'])) $_SESSION['score_csrf_token'] = bin2hex(random_bytes(32));
$userId = (int)$_SESSION['user_id'];
$userName = htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['email'], ENT_QUOTES, 'UTF-8');
$userInitial = strtoupper(substr($userName, 0, 1));

$templatesStmt = $conn->prepare("
    SELECT template_id, course_code, course_title, course_unit, class_code
    FROM grade_templates 
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$templatesStmt->execute([$userId]);
$publishedTemplates = [];
$standingCourses = [];

while ($row = $templatesStmt->fetch()) {
    $templateId = (int)$row['template_id'];
    $components = loadTemplateComponents($conn, $templateId);
    $publishedTemplates[] = [
        'id' => $templateId, 'code' => $row['course_code'], 'title' => $row['course_title'],
        'classCode' => $row['class_code'], 'units' => (float)$row['course_unit'], 'components' => $components,
    ];
    $standingCourses[$templateId] = [
        'id' => $templateId, 'code' => (string)$row['course_code'], 'title' => (string)$row['course_title'],
        'components' => $components, 'students' => [],
    ];
}

$enrollmentStmt = $conn->prepare('SELECT ec.enrollment_id, ec.template_id, u.full_name, u.email
    FROM enrolled_courses ec JOIN grade_templates gt ON gt.template_id = ec.template_id
    JOIN users u ON u.user_id = ec.user_id
    WHERE gt.user_id = ? AND ec.user_id <> ? AND u.role_id <> 2
    ORDER BY ec.template_id, u.full_name, u.email');
$enrollmentStmt->execute([$userId, $userId]);
while ($enrollment = $enrollmentStmt->fetch()) {
    $templateId = (int)$enrollment['template_id'];
    if (!isset($standingCourses[$templateId])) continue;
    $components = $standingCourses[$templateId]['components'];
    $scores = loadEnrollmentScores($conn, (int)$enrollment['enrollment_id'], $templateId);
    $result = calculateCourseGrade($components, $scores);
    $totals = [];
    foreach ($scores as $score) {
        $key = $score['component_id'];
        if (!isset($totals[$key])) $totals[$key] = ['score' => 0.0, 'max' => 0, 'assessments' => []];
        $totals[$key]['score'] += (float)$score['raw_score'];
        $totals[$key]['max'] += (int)$score['total_items'];
        $totals[$key]['assessments'][] = ['title' => $score['assessment_title'], 'score' => (float)$score['raw_score'], 'max' => (int)$score['total_items']];
    }
    $componentResults = [];
    $gradedWeight = 0;
    foreach ($components as $component) {
        $total = $totals[$component['id']] ?? ['score' => 0.0, 'max' => 0, 'assessments' => []];
        if ($total['max'] > 0) $gradedWeight += $component['weight'] / 2;
        $componentResults[] = [
            'id' => $component['id'], 'period' => $component['period'], 'name' => $component['name'],
            'weight' => $component['weight'], 'percentage' => $total['max'] > 0 ? $total['score'] / $total['max'] * 100 : null,
            'score' => $total['score'], 'max' => $total['max'], 'assessments' => $total['assessments'],
        ];
    }
    $standingCourses[$templateId]['students'][] = [
        'enrollmentId' => (int)$enrollment['enrollment_id'], 'student' => $enrollment['full_name'] ?: $enrollment['email'],
        'email' => $enrollment['email'], 'percentage' => $result['percentage'], 'grade' => $result['grade'],
        'status' => !$scores ? 'Not Started' : $result['status'], 'periods' => $result['periods'],
        'gradedWeight' => $gradedWeight, 'components' => $componentResults,
    ];
}
$editingTemplateId = isset($_GET['edit'])
    ? filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : 0;
$editingTemplate = null;
if ($editingTemplateId !== 0) {
    foreach ($publishedTemplates as $template) {
        if ($template['id'] === $editingTemplateId) $editingTemplate = $template;
    }
    if (!$editingTemplate) {
        http_response_code(404);
        exit('Template not found in your published courses.');
    }
}
$templateForm = templateFormInput([]);
$lockedComponents = [];
if ($editingTemplate) {
    $templateForm = [
        'courseCode' => $editingTemplate['code'], 'courseTitle' => $editingTemplate['title'],
        'courseUnit' => (string)$editingTemplate['units'], 'components' => [],
    ];
    foreach (componentTables() as $period => $table) {
        $templateForm['components'][$period] = [];
        foreach ($editingTemplate['components'] as $component) {
            if ($component['period'] !== $period) continue;
            $templateForm['components'][$period][] = [
                'id' => (string)$component['database_id'], 'name' => $component['name'],
                'weight' => (string)$component['weight'], 'is_exam' => $component['is_exam'],
            ];
        }
        $lockedComponents[$period] = array_fill_keys(loadUsedTemplateComponentIds($conn, $editingTemplateId, $period), true);
    }
}
$savedForm = $_SESSION['template_form_input'] ?? null;
unset($_SESSION['template_form_input']);
if (is_array($savedForm) && $savedForm['id'] === $editingTemplateId) $templateForm = $savedForm['form'];
foreach (gradingPeriods() as $period => $label) {
    if (!$templateForm['components'][$period]) {
        $templateForm['components'][$period][] = ['id' => '', 'name' => '', 'weight' => '', 'is_exam' => false];
    }
}
function templateText($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal - Class Standing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        maroon: {
                            50: '#fdf2f4', 100: '#fbe5e8', 200: '#f7ced5', 300: '#f0a7b5',
                            400: '#e5758c', 500: '#d54865', 600: '#be2b4b', 700: '#9f1f3a',
                            800: '#6b0820', 900: '#4a0415', 950: '#30010c',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#f8f6f2] text-slate-800 font-sans min-h-screen flex flex-col antialiased">

    <!-- Header Navigation -->
    <header class="bg-maroon-900 text-white h-14 sticky top-0 z-40 shadow-md flex items-center justify-between px-6">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center font-bold text-xs border border-white/20">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <span class="font-extrabold text-base tracking-wide">Faculty Portal</span>
        </div>

        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-6 text-sm">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-xs text-white">
                        <?php echo $userInitial; ?>
                    </div>
                    <span class="font-semibold text-xs sm:text-sm hidden lg:inline"><?php echo $userName; ?></span>
                </div>

                <a href="php/logout.php" class="text-xs font-semibold text-maroon-200 hover:text-white transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1 w-full">
        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-[#f1ede6] border-r border-stone-300 p-4 shrink-0 flex flex-col justify-between hidden md:flex">
            <div class="space-y-1">
                <button type="button" onclick="switchProfessorTab('standing')" id="nav-standing" class="w-full text-left px-4 py-3 rounded-xl font-bold text-xs flex items-center space-x-3 bg-maroon-100 text-maroon-900 border border-maroon-200">
                    <i class="fa-solid fa-ranking-star text-sm"></i>
                    <span>Class Standing</span>
                </button>
                <button type="button" onclick="switchProfessorTab('templates')" id="nav-templates" class="w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3">
                    <i class="fa-solid fa-sliders text-sm"></i>
                    <span>Course Templates</span>
                </button>
            </div>

            <div class="pt-4 border-t border-stone-300/80 text-[11px] text-stone-500 font-medium">
                <p>Professor Account</p>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="flex-1 p-6 sm:p-8 space-y-6 max-w-7xl">
            <div class="flex gap-2 md:hidden">
                <button type="button" id="mobile-standing" onclick="switchProfessorTab('standing')" class="flex-1 rounded-xl bg-maroon-900 px-3 py-2 text-xs font-bold text-white">Class Standing</button>
                <button type="button" id="mobile-templates" onclick="switchProfessorTab('templates')" class="flex-1 rounded-xl bg-stone-200 px-3 py-2 text-xs font-bold text-stone-700">Course Templates</button>
            </div>

            <section id="standingSection" class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-stone-300/60">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Class Standing Overview</h2>
                        <p class="text-xs font-semibold text-stone-500 mt-0.5">Live standings calculated from the scores students have entered.</p>
                    </div>
                    <span class="text-xs sm:text-sm font-semibold text-stone-500"><?php echo date('M j, Y'); ?></span>
                </div>

                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-stone-200 shadow-sm flex flex-col sm:flex-row sm:items-center gap-3">
                    <label for="courseFilter" class="text-xs font-bold text-stone-600">Course</label>
                    <select id="courseFilter" class="w-full sm:w-auto min-w-64 px-3 py-2 rounded-xl border border-stone-300 text-xs font-bold text-slate-800">
                        <option value="all">All Courses (Overall Standing)</option>
                    </select>
                    <p id="courseScopeLabel" class="sm:ml-auto text-xs text-stone-500">Across all your active classes</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <article class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Top Performer</p>
                        <p id="topPerformerValue" class="mt-2 text-xl font-black text-slate-900">—</p>
                        <p id="topPerformerName" class="text-xs text-stone-500 mt-1">No graded students</p>
                    </article>
                    <article class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Average Current Standing</p>
                        <p id="classAverageValue" class="mt-2 text-xl font-black text-slate-900">—</p>
                        <p class="text-xs text-stone-500 mt-1">Weighted average of scored components</p>
                    </article>
                    <article class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Total Enrollments</p>
                        <p id="enrolledCountValue" class="mt-2 text-xl font-black text-slate-900">0</p>
                        <p id="courseCountNote" class="text-xs text-stone-500 mt-1">Across 0 courses</p>
                    </article>
                    <article class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">At Risk (&lt;75%)</p>
                        <p id="atRiskValue" class="mt-2 text-xl font-black text-rose-700">0 Students</p>
                        <p class="text-xs text-rose-700 mt-1">Current standing below 75%</p>
                    </article>
                </div>

                <section class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-slate-800">Course Standing Summary</h3>
                            <p class="text-xs text-stone-500 mt-1">Overall standing uses 50% Midterms and 50% Finals. Each period has its own 100% grading weights.</p>
                        </div>
                        <span class="text-[11px] text-stone-400">Incomplete courses are labeled in progress.</span>
                    </div>
                    <div id="courseSummaryList" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3"></div>
                </section>

                <section class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm space-y-4">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-award text-maroon-800 mr-2"></i>Class Standing Leaderboard</h3>
                            <label class="text-xs text-stone-500">Sort
                                <select id="rankSort" class="ml-1 px-2 py-1.5 rounded-lg border border-stone-300 text-xs font-semibold text-slate-700">
                                    <option value="desc">Highest to lowest</option>
                                    <option value="asc">Lowest to highest</option>
                                    <option value="name">Student name</option>
                                </select>
                            </label>
                        </div>
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" data-standing-filter="all" class="standing-filter px-3 py-2 rounded-full bg-maroon-900 text-white text-[11px] font-bold">All Students</button>
                                <button type="button" data-standing-filter="top" class="standing-filter px-3 py-2 rounded-full bg-stone-100 text-stone-700 text-[11px] font-bold">Top Performers (&ge;90%)</button>
                                <button type="button" data-standing-filter="risk" class="standing-filter px-3 py-2 rounded-full bg-stone-100 text-stone-700 text-[11px] font-bold">At Risk (&lt;75%)</button>
                            </div>
                            <input id="studentSearch" type="search" placeholder="Search students..." class="w-full md:w-64 px-3 py-2 rounded-xl border border-stone-300 text-xs outline-none focus:border-maroon-800">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[900px]">
                            <thead id="standingTableHead"></thead>
                            <tbody id="standingTableBody" class="divide-y divide-stone-100"></tbody>
                        </table>
                    </div>
                    <p id="standingEmptyState" class="hidden text-center text-xs text-stone-500 py-8">No students match these filters.</p>
                </section>
            </section>

            <section id="templatesSection" class="hidden space-y-6">
                <?php if (isset($_GET['error']) && is_string($_GET['error'])): ?>
                    <p role="alert" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl text-sm"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif (($_GET['template'] ?? '') === 'updated'): ?>
                    <p role="status" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl text-sm">Template updated. Enrolled students will see the changes when they reload their dashboard.</p>
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-stone-300/60">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Professor Syllabus Templates</h2>
                        <p class="text-xs font-semibold text-stone-500 mt-0.5">Design course evaluation components and generate enrollment sync codes for students.</p>
                    </div>
                    <span class="text-xs sm:text-sm font-semibold text-stone-500"><?php echo date('M j, Y'); ?></span>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Syllabus Builder Form -->
                <div id="templateEditor" class="lg:col-span-7 bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-5">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <h3 class="font-bold text-slate-800 text-base flex items-center space-x-2">
                            <i class="fa-solid fa-sliders text-maroon-800"></i>
                            <span><?php echo $editingTemplate ? 'Edit Course Template' : 'Create Course Template'; ?></span>
                        </h3>
                        <span class="text-xs text-stone-500">Each period: 100%</span>
                    </div>

                    <?php if ($editingTemplate): ?>
                        <p class="text-xs text-stone-600">Editing <strong><?php echo templateText($editingTemplate['code']); ?></strong>. Changes apply to all enrolled students. Existing scores stay attached; components with saved scores cannot be removed.</p>
                    <?php endif; ?>

                    <form id="publishTemplateForm" action="php/publish_template.php" method="POST" onsubmit="return validatePublishForm(event)" class="space-y-4 text-xs">
                        <input type="hidden" name="template_id" value="<?php echo $editingTemplateId; ?>">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold uppercase text-stone-600 mb-1">Course Code</label>
                                <input type="text" name="courseCode" id="courseCodeInput" value="<?php echo templateText($templateForm['courseCode']); ?>" placeholder="Enter course code" maxlength="20" required class="w-full px-3 py-2 rounded-xl border border-stone-300 font-bold text-slate-800 focus:border-maroon-800 outline-none">
                            </div>
                            <div>
                                <label class="block font-bold uppercase text-stone-600 mb-1">Course Title</label>
                                <input type="text" name="courseTitle" id="courseTitleInput" value="<?php echo templateText($templateForm['courseTitle']); ?>" placeholder="Enter course title" maxlength="100" required class="w-full px-3 py-2 rounded-xl border border-stone-300 font-bold text-slate-800 focus:border-maroon-800 outline-none">
                            </div>
                            <div>
                                <label for="courseUnitInput" class="block font-bold uppercase text-stone-600 mb-1">Unit</label>
                                <input type="number" name="courseUnit" id="courseUnitInput" value="<?php echo templateText($templateForm['courseUnit']); ?>" placeholder="Enter units" min="0.1" max="99.9" step="0.1" required class="w-full px-3 py-2 rounded-xl border border-stone-300 font-bold text-slate-800 focus:border-maroon-800 outline-none">
                            </div>
                        </div>

                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['score_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php foreach (gradingPeriods() as $period => $label): ?>
                        <section class="period-components space-y-3 p-4 bg-stone-50 rounded-2xl border border-stone-200" data-period="<?php echo $period; ?>">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="font-bold text-maroon-900"><?php echo $label; ?></h4>
                                <span id="weightTotalBadge-<?php echo $period; ?>" class="text-xs font-bold text-rose-700">Total Weight: 0%</span>
                            </div>
                            <p class="text-[11px] text-stone-500">Weights must total 100%. You may mark up to one component as an exam.</p>
                            <div class="space-y-2" id="componentContainer-<?php echo $period; ?>">
                                <?php foreach ($templateForm['components'][$period] as $index => $component): ?>
                                <div class="flex flex-wrap items-center gap-2 component-row">
                                    <input type="hidden" name="components[<?php echo $period; ?>][<?php echo $index; ?>][id]" value="<?php echo templateText($component['id']); ?>">
                                    <input type="text" name="components[<?php echo $period; ?>][<?php echo $index; ?>][name]" value="<?php echo templateText($component['name']); ?>" placeholder="Assessment name" maxlength="100" required class="comp-name min-w-0 w-full sm:w-auto sm:flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
                                    <label class="flex items-center gap-1.5 font-semibold text-stone-600 cursor-pointer">
                                        <input type="checkbox" name="components[<?php echo $period; ?>][<?php echo $index; ?>][is_exam]" value="1" <?php if ($component['is_exam']) echo 'checked'; ?> onchange="limitExam(this)" class="comp-exam w-4 h-4 rounded border-stone-300 accent-maroon-900"> Exam
                                    </label>
                                    <input type="number" name="components[<?php echo $period; ?>][<?php echo $index; ?>][weight]" value="<?php echo templateText($component['weight']); ?>" placeholder="Weight" min="1" max="100" step="1" oninput="calculateTotalWeight()" required class="comp-weight w-20 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
                                    <span class="font-bold text-stone-400">%</span>
                                    <button type="button" onclick="removeRow(this)" aria-label="Remove component" <?php if (isset($lockedComponents[$period][$component['id']])) echo 'disabled title="This component has saved scores."'; ?> class="text-rose-500 hover:text-rose-700 disabled:text-stone-300 disabled:cursor-not-allowed p-2"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" onclick="addComponentRow('<?php echo $period; ?>')" class="text-xs font-bold text-maroon-800 hover:underline">+ Add <?php echo $label; ?> Component</button>
                        </section>
                        <?php endforeach; ?>
                        <input type="hidden" name="components_complete" value="1">

                        <div class="pt-4 border-t border-stone-200">
                            <button type="submit" name="btnPublishTemplate" class="w-full py-3 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl shadow-md transition text-center">
                                <?php echo $editingTemplate ? 'Save Changes' : 'Publish Syllabus & Generate Class Code'; ?>
                            </button>
                            <?php if ($editingTemplate): ?>
                                <a href="professor_dashboard.php#templates" class="block mt-3 text-center font-bold text-stone-600 hover:underline">Cancel Editing</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Template List Display -->
                <div class="lg:col-span-5 space-y-4">
                    <h3 class="font-bold text-slate-800 text-base">Active Course Templates</h3>
                    <div class="space-y-3" id="publishedTemplatesList">
                    </div>
                </div>
            </div>
            </section>

        </main>
    </div>

    <dialog id="standingDialog" class="w-[min(42rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/40">
        <div class="p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4 border-b border-stone-200 pb-4">
                <div>
                    <p id="standingDialogCourse" class="text-[11px] font-bold uppercase text-stone-500"></p>
                    <h2 id="standingDialogStudent" class="mt-1 text-xl font-black text-slate-900"></h2>
                    <p id="standingDialogEmail" class="mt-1 text-xs text-stone-500"></p>
                    <p id="standingDialogSummary" class="mt-2 text-xs font-semibold text-slate-700"></p>
                </div>
                <button type="button" onclick="document.getElementById('standingDialog').close()" aria-label="Close breakdown" class="text-stone-400 hover:text-stone-700"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div id="standingDialogBreakdown" class="mt-4 space-y-3"></div>
        </div>
    </dialog>

    <script>
        let enrolledTemplates = <?php echo json_encode($publishedTemplates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const standingCourses = <?php echo json_encode(array_values($standingCourses), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const periodLabels = <?php echo json_encode(gradingPeriods()); ?>;
        const nextComponentIndex = Object.fromEntries(Object.keys(periodLabels).map(period => [
            period, document.querySelectorAll(`#componentContainer-${period} .component-row`).length
        ]));
        let activeStandingFilter = 'all';

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            })[character]);
        }

        document.addEventListener("DOMContentLoaded", () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('template') === 'success') {
                const code = urlParams.get('code') ? `\nClass Sync Code: ${urlParams.get('code')}` : '';
                alert(`Syllabus template published and saved to database!${code}\nShare this code with your students.`);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            renderTemplatesList();
            calculateTotalWeight();
            initializeClassStanding();
            if (window.location.hash === '#templates' || urlParams.has('template') || urlParams.has('error') || urlParams.has('edit')) switchProfessorTab('templates');
        });

        function switchProfessorTab(tab) {
            const showStanding = tab === 'standing';
            document.getElementById('standingSection').classList.toggle('hidden', !showStanding);
            document.getElementById('templatesSection').classList.toggle('hidden', showStanding);
            ['standing', 'templates'].forEach(name => {
                document.getElementById(`mobile-${name}`).className = `flex-1 rounded-xl px-3 py-2 text-xs font-bold ${name === tab ? 'bg-maroon-900 text-white' : 'bg-stone-200 text-stone-700'}`;
            });
            document.getElementById('nav-standing').className = showStanding
                ? 'w-full text-left px-4 py-3 rounded-xl font-bold text-xs flex items-center space-x-3 bg-maroon-100 text-maroon-900 border border-maroon-200'
                : 'w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3';
            document.getElementById('nav-templates').className = showStanding
                ? 'w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3'
                : 'w-full text-left px-4 py-3 rounded-xl font-bold text-xs flex items-center space-x-3 bg-maroon-100 text-maroon-900 border border-maroon-200';
        }

        function initializeClassStanding() {
            const courseFilter = document.getElementById('courseFilter');
            standingCourses.forEach(course => {
                const option = document.createElement('option');
                option.value = String(course.id);
                option.textContent = `${course.code} — ${course.title}`;
                courseFilter.appendChild(option);
            });
            courseFilter.addEventListener('change', renderClassStanding);
            document.getElementById('rankSort').addEventListener('change', renderClassStanding);
            document.getElementById('studentSearch').addEventListener('input', renderClassStanding);
            document.querySelectorAll('.standing-filter').forEach(button => {
                button.addEventListener('click', () => {
                    activeStandingFilter = button.dataset.standingFilter;
                    document.querySelectorAll('.standing-filter').forEach(filterButton => {
                        filterButton.className = filterButton === button
                            ? 'standing-filter px-3 py-2 rounded-full bg-maroon-900 text-white text-[11px] font-bold'
                            : 'standing-filter px-3 py-2 rounded-full bg-stone-100 text-stone-700 text-[11px] font-bold';
                    });
                    renderClassStanding();
                });
            });
            renderCourseSummaries();
            renderClassStanding();
        }

        function selectedCourses() {
            const selectedId = document.getElementById('courseFilter').value;
            return selectedId === 'all' ? standingCourses : standingCourses.filter(course => String(course.id) === selectedId);
        }

        function getVisibleStandings() {
            const courses = selectedCourses();
            const search = document.getElementById('studentSearch').value.trim().toLocaleLowerCase();
            const rows = courses.flatMap(course => course.students.map(student => ({...student, course})));
            return rows.filter(row => {
                const matchesFilter = activeStandingFilter === 'all' ||
                    (activeStandingFilter === 'top' && row.percentage !== null && row.percentage >= 90) ||
                    (activeStandingFilter === 'risk' && row.percentage !== null && row.percentage < 75);
                const matchesSearch = !search ||
                    row.student.toLocaleLowerCase().includes(search) ||
                    row.email.toLocaleLowerCase().includes(search) ||
                    row.course.code.toLocaleLowerCase().includes(search) ||
                    row.course.title.toLocaleLowerCase().includes(search);
                return matchesFilter && matchesSearch;
            });
        }

        function formatPercentage(value) {
            return value === null || value === undefined ? '—' : `${Number(value).toFixed(1)}%`;
        }

        function standingStatus(row) {
            if (row.percentage === null) return {label: row.status, style: 'bg-stone-100 text-stone-600'};
            if (row.percentage < 75) return {label: 'At Risk', style: 'bg-rose-100 text-rose-700'};
            if (row.status !== 'Complete') return {label: 'In Progress', style: 'bg-amber-100 text-amber-800'};
            if (row.percentage >= 90) return {label: 'Outstanding', style: 'bg-emerald-100 text-emerald-700'};
            return {label: 'Good Standing', style: 'bg-blue-100 text-blue-700'};
        }

        function renderCourseSummaries() {
            const container = document.getElementById('courseSummaryList');
            if (standingCourses.length === 0) {
                container.innerHTML = '<p class="text-xs text-stone-500 italic">No course templates yet. Create a template to start collecting class standings.</p>';
                return;
            }
            container.innerHTML = standingCourses.map(course => {
                const graded = course.students.filter(student => student.percentage !== null);
                const average = graded.length
                    ? graded.reduce((total, student) => total + student.percentage, 0) / graded.length
                    : null;
                const riskCount = graded.filter(student => student.percentage < 75).length;
                return `<article class="rounded-xl border border-stone-200 bg-stone-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="font-extrabold text-slate-800">${escapeHtml(course.code)}</p><p class="text-xs text-stone-500 mt-0.5">${escapeHtml(course.title)}</p></div>
                        <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold text-stone-600 border border-stone-200">${course.students.length} enrolled</span>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-xs"><span class="text-stone-500">Average current standing</span><strong class="text-slate-800">${formatPercentage(average)}</strong></div>
                    <div class="mt-1 flex items-center justify-between text-xs"><span class="text-stone-500">At risk</span><strong class="${riskCount ? 'text-rose-700' : 'text-emerald-700'}">${riskCount} students</strong></div>
                </article>`;
            }).join('');
        }

        function renderClassStanding() {
            const courses = selectedCourses();
            const rows = getVisibleStandings();
            const allRows = courses.flatMap(course => course.students.map(student => ({...student, course})));
            const gradedRows = allRows.filter(row => row.percentage !== null);
            const rankedRows = [...gradedRows].sort((a, b) => b.percentage - a.percentage);
            const top = rankedRows[0];
            const average = gradedRows.length
                ? gradedRows.reduce((total, row) => total + row.percentage, 0) / gradedRows.length
                : null;
            const atRisk = gradedRows.filter(row => row.percentage < 75).length;
            document.getElementById('topPerformerValue').textContent = top ? formatPercentage(top.percentage) : '—';
            document.getElementById('topPerformerName').textContent = top
                ? `${top.student} · ${top.course.code}`
                : 'No graded students';
            document.getElementById('classAverageValue').textContent = formatPercentage(average);
            document.getElementById('enrolledCountValue').textContent = String(allRows.length);
            document.getElementById('courseCountNote').textContent = `Across ${courses.length} ${courses.length === 1 ? 'course' : 'courses'}`;
            document.getElementById('atRiskValue').textContent = `${atRisk} ${atRisk === 1 ? 'Student' : 'Students'}`;
            document.getElementById('courseScopeLabel').textContent = courses.length === 1
                ? `${courses[0].code} · ${courses[0].title}`
                : 'Across all your active classes';

            renderStandingTable(courses, rows, allRows);
        }

        function renderStandingTable(courses, rows, allRows) {
            const tableHead = document.getElementById('standingTableHead');
            const tableBody = document.getElementById('standingTableBody');
            const showCourse = document.getElementById('courseFilter').value === 'all';
            const components = courses.length === 1 ? courses[0].components : [];
            tableHead.innerHTML = `<tr class="bg-stone-50 text-[10px] uppercase tracking-wide text-stone-500 border-b border-stone-200">
                <th class="p-3">Rank</th><th class="p-3">Student</th>${showCourse ? '<th class="p-3">Course</th>' : ''}
                <th class="p-3">Current Standing</th>${components.map(component => `<th class="p-3">${periodLabels[component.period]} · ${escapeHtml(component.name)} (${component.weight}%)</th>`).join('')}
                <th class="p-3">Progress / Status</th><th class="p-3 text-right">Action</th></tr>`;
            const sort = document.getElementById('rankSort').value;
            const scoreRankings = [...allRows].sort((a, b) => {
                if (a.percentage === null) return b.percentage === null ? a.student.localeCompare(b.student) : 1;
                if (b.percentage === null) return -1;
                return b.percentage - a.percentage;
            });
            const rankByEnrollment = new Map();
            let rank = 0;
            let previousScore = null;
            scoreRankings.forEach((row, index) => {
                if (row.percentage !== null && row.percentage !== previousScore) rank = index + 1;
                rankByEnrollment.set(row.enrollmentId, row.percentage === null ? '—' : rank);
                previousScore = row.percentage;
            });
            rows.sort((a, b) => {
                if (sort === 'name') return a.student.localeCompare(b.student);
                if (a.percentage === null) return b.percentage === null ? a.student.localeCompare(b.student) : 1;
                if (b.percentage === null) return -1;
                return sort === 'asc' ? a.percentage - b.percentage : b.percentage - a.percentage;
            });
            tableBody.innerHTML = rows.map(row => {
                const status = standingStatus(row);
                const componentCells = components.map(component => {
                    const item = row.components.find(result => result.id === component.id);
                    return `<td class="p-3 text-stone-600">${formatPercentage(item?.percentage ?? null)}</td>`;
                }).join('');
                const current = formatPercentage(row.percentage);
                const progressText = row.percentage === null ? 'No scores' : `${row.gradedWeight}% weight graded`;
                return `<tr class="hover:bg-stone-50/80">
                    <td class="p-3 font-bold text-stone-500">#${rankByEnrollment.get(row.enrollmentId)}</td>
                    <td class="p-3"><p class="font-bold text-slate-800">${escapeHtml(row.student)}</p><p class="text-[10px] text-stone-400 mt-0.5">${escapeHtml(row.email)}</p></td>
                    ${showCourse ? `<td class="p-3 font-semibold text-stone-600">${escapeHtml(row.course.code)}</td>` : ''}
                    <td class="p-3"><span class="font-black text-slate-900">${current}</span>${row.percentage !== null ? `<div class="mt-1 h-1.5 w-20 rounded-full bg-stone-100"><div class="h-1.5 rounded-full ${row.percentage < 75 ? 'bg-rose-500' : 'bg-maroon-900'}" style="width:${Math.max(0, Math.min(100, row.percentage))}%"></div></div>` : ''}</td>
                    ${componentCells}
                    <td class="p-3"><span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold ${status.style}">${status.label}</span><p class="text-[10px] text-stone-400 mt-1">${progressText}</p></td>
                    <td class="p-3 text-right"><button type="button" data-enrollment-id="${row.enrollmentId}" class="standing-detail-button rounded-lg border border-stone-300 px-3 py-1.5 text-[10px] font-bold text-slate-700 hover:bg-stone-100">View Breakdown</button></td>
                </tr>`;
            }).join('');
            tableBody.querySelectorAll('.standing-detail-button').forEach(button => {
                button.addEventListener('click', () => showStandingDetails(Number(button.dataset.enrollmentId)));
            });
            document.getElementById('standingEmptyState').classList.toggle('hidden', rows.length > 0);
        }

        function showStandingDetails(enrollmentId) {
            const course = standingCourses.find(item => item.students.some(student => student.enrollmentId === enrollmentId));
            const student = course?.students.find(item => item.enrollmentId === enrollmentId);
            if (!course || !student) return;
            document.getElementById('standingDialogCourse').textContent = `${course.code} · ${course.title}`;
            document.getElementById('standingDialogStudent').textContent = student.student;
            document.getElementById('standingDialogEmail').textContent = student.email;
            const standingSummary = student.percentage === null
                ? 'No scores have been entered.'
                : `Current standing: ${formatPercentage(student.percentage)} across ${student.gradedWeight}% of course weight graded${student.grade === null ? '' : ` · Final PUP grade: ${Number(student.grade).toFixed(2)}`}`;
            document.getElementById('standingDialogSummary').textContent = standingSummary;
            document.getElementById('standingDialogBreakdown').innerHTML = student.components.map(component => {
                const assessments = component.assessments.length
                    ? component.assessments.map(assessment => `<li class="flex justify-between gap-3 py-1 text-[11px] text-stone-500"><span>${escapeHtml(assessment.title)}</span><span>${Number(assessment.score)} / ${Number(assessment.max)}</span></li>`).join('')
                    : '<li class="py-1 text-[11px] italic text-stone-400">No scores entered</li>';
                return `<section class="rounded-xl border border-stone-200 p-3">
                    <div class="flex items-center justify-between gap-3"><h3 class="text-xs font-bold text-slate-800">${periodLabels[component.period]} · ${escapeHtml(component.name)} <span class="font-medium text-stone-400">(${component.weight}%)</span></h3><span class="text-xs font-black text-slate-800">${formatPercentage(component.percentage)}</span></div>
                    <ul class="mt-2 border-t border-stone-100 pt-2">${assessments}</ul>
                </section>`;
            }).join('') || '<p class="text-xs text-stone-500">This course has no grading components.</p>';
            document.getElementById('standingDialog').showModal();
        }

        function renderTemplatesList() {
            const container = document.getElementById('publishedTemplatesList');
            container.innerHTML = '';

            if (enrolledTemplates.length === 0) {
                container.innerHTML = `<p class="text-xs text-stone-400 italic">No published class templates found.</p>`;
                return;
            }

            enrolledTemplates.forEach(tpl => {
                const card = document.createElement('div');
                card.className = "bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-3";

                const componentsHtml = Object.entries(periodLabels).map(([period, label]) => `
                    <div class="pt-2"><p class="font-bold text-maroon-900 mb-1">${label} · 100%</p>
                    ${tpl.components.filter(c => c.period === period).map(c => `
                        <div class="flex justify-between text-stone-600"><span>${escapeHtml(c.name)}${c.is_exam ? ' · Exam' : ''}</span><span class="font-bold">${c.weight}%</span></div>`).join('')}</div>`).join('');

                card.innerHTML = `
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">PUBLISHED SYLLABUS</span>
                            <h4 class="font-extrabold text-slate-900 text-base mt-1">${escapeHtml(tpl.code)}</h4>
                            <p class="text-xs text-stone-500 font-medium">${escapeHtml(tpl.title)}</p>
                            <p class="text-xs text-stone-500 font-medium">${Number(tpl.units).toFixed(1)} units</p>
                        </div>
                        <div class="text-right flex flex-wrap items-center gap-2">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-stone-400 block">Class Sync Code</span>
                                <span class="font-mono font-black text-maroon-900 text-sm bg-stone-100 px-2.5 py-1 rounded-lg border border-stone-200 inline-block mt-0.5">${escapeHtml(tpl.classCode)}</span>
                            </div>
                            <a href="professor_dashboard.php?edit=${tpl.id}#templates" class="inline-flex items-center gap-1 rounded-lg bg-maroon-50 px-2.5 py-1.5 text-xs font-bold text-maroon-900 hover:bg-maroon-100"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                            <button type="button" onclick="removeTemplate(${tpl.id})" aria-label="Delete template" class="text-stone-400 hover:text-rose-600 transition text-sm">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>

                    <div class="bg-stone-50 p-3 rounded-2xl border border-stone-200 text-xs space-y-1.5">
                        ${componentsHtml}
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function addComponentRow(period) {
            const container = document.getElementById(`componentContainer-${period}`);
            const row = document.createElement('div');
            const index = nextComponentIndex[period]++;
            row.className = 'flex flex-wrap items-center gap-2 component-row';
            row.innerHTML = `
                <input type="text" name="components[${period}][${index}][name]" placeholder="Assessment Name" maxlength="100" required class="comp-name min-w-0 w-full sm:w-auto sm:flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
                <label class="flex items-center gap-1.5 font-semibold text-stone-600 cursor-pointer">
                    <input type="checkbox" name="components[${period}][${index}][is_exam]" value="1" onchange="limitExam(this)" class="comp-exam w-4 h-4 rounded border-stone-300 accent-maroon-900"> Exam
                </label>
                <input type="number" name="components[${period}][${index}][weight]" placeholder="Weight" min="1" max="100" step="1" oninput="calculateTotalWeight()" required class="comp-weight w-20 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
                <span class="font-bold text-stone-400">%</span>
                <button type="button" onclick="removeRow(this)" aria-label="Remove component" class="text-rose-500 hover:text-rose-700 p-2"><i class="fa-solid fa-trash-can"></i></button>`;
            container.appendChild(row);
            calculateTotalWeight();
        }

        function limitExam(input) {
            if (!input.checked) return;
            input.closest('.period-components').querySelectorAll('.comp-exam').forEach(other => {
                if (other !== input) other.checked = false;
            });
        }

        function removeRow(button) {
            button.closest('.component-row').remove();
            calculateTotalWeight();
        }

        function calculateTotalWeight() {
            let valid = true;
            Object.keys(periodLabels).forEach(period => {
                const inputs = document.querySelectorAll(`#componentContainer-${period} .comp-weight`);
                const total = [...inputs].reduce((sum, input) => sum + (Number(input.value) || 0), 0);
                const badge = document.getElementById(`weightTotalBadge-${period}`);
                badge.textContent = `Total Weight: ${total}%`;
                badge.className = `text-xs font-bold ${total === 100 ? 'text-emerald-700' : 'text-rose-700'}`;
                if (total !== 100) valid = false;
            });
            return valid;
        }

        function validatePublishForm(event) {
            if (!calculateTotalWeight()) {
                alert('Midterms and Finals must each total 100%.');
                return false;
            }
            for (const section of document.querySelectorAll('.period-components')) {
                if (section.querySelectorAll('.comp-exam:checked').length > 1) {
                    alert('Each period can have at most one checked exam.');
                    return false;
                }
            }
            const button = document.querySelector('button[name="btnPublishTemplate"]');
            button.disabled = true;
            button.textContent = document.querySelector('input[name="template_id"]').value !== '0' ? 'Saving...' : 'Publishing...';
            return true;
        }

        function removeTemplate(id) {
            if (confirm("Remove this published syllabus from database?")) {
                window.location.href = `php/delete_template.php?id=${id}`;
            }
        }

        function logout() { window.location.href = 'php/logout.php'; }
    </script>
</body>
</html>
