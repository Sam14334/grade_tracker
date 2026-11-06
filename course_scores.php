<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
if ((isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 2) ||
    (isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'Professor') === 0)) {
    header('Location: professor_dashboard.php');
    exit();
}
require __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/grade_calculator.php';
require_once __DIR__ . '/php/course_components.php';

$userId = (int)$_SESSION['user_id'];
$enrollmentId = filter_var($_GET['enrollment_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$courseStmt = $conn->prepare('SELECT ec.enrollment_id, ec.target_grade, gt.template_id, gt.course_code, gt.course_title, gt.course_unit
    FROM enrolled_courses ec JOIN grade_templates gt ON gt.template_id = ec.template_id
    WHERE ec.enrollment_id = ? AND ec.user_id = ?');
$courseStmt->execute([$enrollmentId ?: 0, $userId]);
$course = $courseStmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('Course not found in your enrollments.');
}

$components = loadTemplateComponents($conn, (int)$course['template_id']);
$componentsById = array_column($components, null, 'id');
$examComponents = array_filter($components, fn($component) => $component['is_exam']);
$scores = loadEnrollmentScores($conn, (int)$enrollmentId, (int)$course['template_id']);
$entriesByComponent = [];
foreach ($scores as $score) $entriesByComponent[$score['component_id']][] = $score;

if (!isset($_SESSION['score_csrf_token'])) $_SESSION['score_csrf_token'] = bin2hex(random_bytes(32));
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['score_csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Your form expired. Reload this page and try again.';
    } elseif (!isset($_POST['scores_complete']) || !is_array($_POST['entries'] ?? null) || !$components) {
        $errors[] = 'The form was incomplete. Please reload and try again.';
    } else {
        $postedEntries = $_POST['entries'];
        $componentIds = array_column($components, 'id');
        foreach (array_keys($postedEntries) as $id) {
            if (!in_array((string)$id, $componentIds, true)) {
                $errors[] = 'An entry does not belong to this course.';
            }
        }
        $newScores = [];
        foreach ($components as $component) {
            $componentId = $component['id'];
            $rows = $postedEntries[$componentId] ?? null;
            if (!is_array($rows) || count($rows) === 0) {
                $errors[] = 'Each component must have at least one entry row.';
                continue;
            }
            $entriesByComponent[$componentId] = [];
            foreach (array_values($rows) as $index => $row) {
                if (!is_array($row) || !is_string($row['raw_score'] ?? null) ||
                    !is_string($row['total_items'] ?? null) || !is_string($row['assessment_title'] ?? null)) {
                    $errors[] = 'An entry has invalid fields.';
                    continue;
                }
                $title = trim($row['assessment_title']);
                if ($title === '') $title = 'Entry ' . ($index + 1);
                $raw = trim($row['raw_score']);
                $max = trim($row['total_items']);
                $entry = ['component_id' => $componentId, 'assessment_title' => $title, 'raw_score' => $raw, 'total_items' => $max];
                $entriesByComponent[$componentId][] = $entry;
                // A completely blank score row is a placeholder, not a zero.
                if ($raw === '' && $max === '') continue;
                $validMax = filter_var($max, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
                $titleLength = preg_match_all('/./us', $title);
                if ($titleLength === false || $titleLength > 100 || !preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $raw) ||
                    $validMax === false || (float)$raw > $validMax) {
                    $errors[] = $component['name'] . ': use a score from 0 to the maximum (up to two decimal places), a positive whole-number maximum, and an entry name of at most 100 characters.';
                    continue;
                }
                $newScores[] = $entry;
            }
        }
        if (!$errors) {
            try {
                $conn->beginTransaction();
                $deleteStmt = $conn->prepare('DELETE FROM student_component_scores WHERE enrollment_id = ?
                    AND enrollment_id IN (SELECT enrollment_id FROM enrolled_courses WHERE user_id = ?)');
                $deleteStmt->execute([$enrollmentId, $userId]);
                $insertStatements = [];
                $columns = ['midterms' => 'midterm_component_id', 'finals' => 'final_component_id'];
                foreach (componentTables() as $period => $table) {
                    $column = $columns[$period];
                    $insertStatements[$period] = $conn->prepare("INSERT INTO student_component_scores
                        (enrollment_id, $column, assessment_title, raw_score, total_items)
                        SELECT ec.enrollment_id, tc.id, ?, ?, ? FROM enrolled_courses ec
                        JOIN $table tc ON tc.template_id = ec.template_id
                        WHERE ec.enrollment_id = ? AND ec.user_id = ? AND tc.id = ?");
                }
                foreach ($newScores as $entry) {
                    $component = $componentsById[$entry['component_id']];
                    $insertStmt = $insertStatements[$component['period']];
                    $insertStmt->execute([$entry['assessment_title'], $entry['raw_score'], $entry['total_items'], $enrollmentId, $userId, $component['database_id']]);
                    if ($insertStmt->rowCount() !== 1) throw new RuntimeException('Enrollment or component changed while saving.');
                }
                $conn->commit();
                header('Location: course_scores.php?enrollment_id=' . $enrollmentId . '&saved=1');
                exit();
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                error_log('Course scores could not be saved: ' . $e->getMessage());
                $errors[] = 'Could not save your scores. Your previous scores are unchanged. Please try again.';
            }
        }
    }
}

$result = calculateCourseGrade($components, $scores);
function scoreText($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo scoreText($course['course_code']); ?> Scores - Grade Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {theme: {extend: {fontFamily: {sans: ['Inter', 'sans-serif']}, colors: {
            maroon: {50: '#fdf2f4', 100: '#fbe5e8', 200: '#f7ced5', 800: '#6b0820', 900: '#4a0415'}
        }}}};
    </script>
</head>
<body class="bg-[#f8f6f2] text-slate-800 font-sans min-h-screen antialiased">
    <header class="bg-maroon-900 text-white h-14 flex items-center justify-between px-4 sm:px-6 shadow-sm">
        <a href="student_dashboard.php" class="font-extrabold text-sm sm:text-base tracking-wide">Academic Grade Tracker</a>
        <a href="php/logout.php" class="text-xs font-semibold text-maroon-200 hover:text-white">Logout</a>
    </header>
    <main class="max-w-4xl mx-auto px-4 py-6 sm:p-8 space-y-6">
        <a href="student_dashboard.php#grades" class="inline-flex text-xs font-bold text-maroon-800 hover:underline">&larr; Back to Calculator</a>
        <div>
            <p class="text-xs font-semibold text-stone-500 mb-1">Grade Entry & Calculator</p>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900"><?php echo scoreText($course['course_code']); ?></h1>
            <p class="text-sm text-stone-600 mt-1"><?php echo scoreText($course['course_title']); ?> &middot; <?php echo number_format((float)$course['course_unit'], 1); ?> units</p>
        </div>

        <?php if ($errors): ?>
            <div role="alert" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl text-sm space-y-1">
                <?php foreach (array_unique($errors) as $error): ?><p><?php echo scoreText($error); ?></p><?php endforeach; ?>
            </div>
        <?php elseif (isset($_GET['saved'])): ?>
            <p role="status" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl text-sm">Scores saved. Your calculator now reflects these scores.</p>
        <?php endif; ?>

        <form id="courseScoresForm" method="POST" action="course_scores.php?enrollment_id=<?php echo $enrollmentId; ?>" data-unsaved="<?php echo $errors ? 'true' : 'false'; ?>" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo scoreText($_SESSION['score_csrf_token']); ?>">
            <?php foreach (gradingPeriods() as $period => $label): ?>
            <h2 class="text-lg font-extrabold text-maroon-900 pt-3"><?php echo $label; ?> <span class="text-xs font-medium text-stone-500">100% period weight · 50% of overall grade</span></h2>
            <?php foreach (array_filter($components, fn($component) => $component['period'] === $period) as $component): ?>
                <?php
                $componentId = $component['id'];
                $rows = $entriesByComponent[$componentId] ?? [];
                if (!$rows) $rows = [['assessment_title' => 'Entry 1', 'raw_score' => '', 'total_items' => '']];
                ?>
                <section data-component-id="<?php echo scoreText($componentId); ?>" data-period="<?php echo $component['period']; ?>" data-weight="<?php echo (int)$component['weight']; ?>" data-is-exam="<?php echo $component['is_exam'] ? '1' : '0'; ?>" data-next-index="<?php echo count($rows); ?>"
                    class="score-component bg-white p-5 sm:p-6 rounded-3xl border border-stone-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-bold text-slate-900"><?php echo scoreText($component['name']); ?></h2>
                        <span class="text-xs font-bold text-maroon-800 bg-maroon-50 border border-maroon-200 px-3 py-1 rounded-full"><?php echo (int)$component['weight']; ?>%</span>
                    </div>
                    <div class="score-entries space-y-3">
                        <?php foreach (array_values($rows) as $index => $entry): ?>
                            <div class="score-entry grid grid-cols-2 sm:grid-cols-[minmax(0,1fr)_7rem_7rem_4rem] gap-3 items-end">
                                <label class="col-span-2 sm:col-span-1 text-xs font-semibold text-stone-600">Entry name
                                    <input type="text" name="entries[<?php echo $componentId; ?>][<?php echo $index; ?>][assessment_title]" maxlength="100" value="<?php echo scoreText($entry['assessment_title']); ?>" placeholder="Entry <?php echo $index + 1; ?>"
                                        class="entry-title mt-1 block w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs text-slate-800 outline-none focus:border-maroon-800">
                                </label>
                                <label class="text-xs font-semibold text-stone-600">Score
                                    <input type="number" name="entries[<?php echo $componentId; ?>][<?php echo $index; ?>][raw_score]" min="0" max="9999999.99" step="0.01" value="<?php echo scoreText($entry['raw_score']); ?>" placeholder="0"
                                        class="entry-score mt-1 block w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs text-slate-800 outline-none focus:border-maroon-800">
                                </label>
                                <label class="text-xs font-semibold text-stone-600">Max score
                                    <input type="number" name="entries[<?php echo $componentId; ?>][<?php echo $index; ?>][total_items]" min="1" max="2147483647" step="1" value="<?php echo scoreText($entry['total_items']); ?>" placeholder="100"
                                        class="entry-max mt-1 block w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs text-slate-800 outline-none focus:border-maroon-800">
                                </label>
                                <button type="button" class="remove-entry text-xs font-semibold text-rose-600 hover:underline py-2.5 sm:text-right">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex flex-wrap justify-between items-center gap-3 pt-1">
                        <button type="button" class="add-entry text-xs font-bold text-maroon-800 hover:underline">+ Add Entry</button>
                        <p class="component-summary text-xs text-stone-500"></p>
                    </div>
                </section>
            <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (!$components): ?><p class="text-sm text-stone-600">This template has no components. Ask your professor to complete it.</p><?php endif; ?>
            <input type="hidden" name="scores_complete" value="1">
        </form>

        <section id="examPredictor" class="bg-white p-5 sm:p-6 rounded-3xl border border-stone-200 shadow-sm space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-bold text-slate-900">Exam predictor</h2>
                    <p class="text-xs text-stone-500 mt-1">Midterm predictions use Midterm scores only. Final predictions use Final scores only.</p>
                </div>
                <a href="student_dashboard.php#grades" class="text-xs font-bold text-maroon-800 hover:underline">Target for each period: <?php echo number_format((float)$course['target_grade'], 2); ?></a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="text-xs font-semibold text-stone-600" for="predictorExam">Exam
                    <select id="predictorExam" <?php if (!$examComponents) echo 'disabled'; ?> class="mt-1 block w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs text-slate-800 outline-none focus:border-maroon-800">
                        <?php if (!$examComponents): ?>
                            <option value="">No exams marked</option>
                        <?php else: ?>
                            <?php foreach ($examComponents as $examComponent): ?>
                                <option value="<?php echo scoreText($examComponent['id']); ?>"><?php echo gradingPeriods()[$examComponent['period']]; ?> · <?php echo scoreText($examComponent['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </label>
                <label class="text-xs font-semibold text-stone-600" for="predictorMax">Exam max score
                    <input type="number" id="predictorMax" min="1" max="2147483647" step="1" placeholder="e.g. 100" <?php if (!$examComponents) echo 'disabled'; ?>
                        class="mt-1 block w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs text-slate-800 outline-none focus:border-maroon-800">
                </label>
            </div>
            <div class="bg-stone-50 border border-stone-200 p-4 rounded-2xl space-y-1">
                <p id="examPredictionResult" class="font-bold text-maroon-900 text-sm" aria-live="polite">Select the examination and enter its maximum score.</p>
                <p id="examPredictionNote" class="text-xs text-stone-500">Uses the selected period’s 100% weights and the saved target grade. Predictions are not saved as scores.</p>
            </div>
        </section>

        <div class="bg-white p-5 sm:p-6 rounded-3xl border border-stone-200 shadow-sm space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-stone-100">
                <?php foreach (gradingPeriods() as $period => $label): ?>
                <div>
                    <p class="text-xs font-semibold text-stone-500"><?php echo $label; ?> · 50% of overall grade</p>
                    <p id="periodGrade-<?php echo $period; ?>" class="font-bold text-maroon-900 mt-1"><?php echo $result['periods'][$period]['grade'] === null ? scoreText($result['periods'][$period]['status']) : number_format($result['periods'][$period]['grade'], 2); ?></p>
                    <p id="periodPercentage-<?php echo $period; ?>" class="text-xs text-stone-500 mt-1"></p>
                </div>
                <?php endforeach; ?>
            </div>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-stone-500">Overall course grade preview</p>
                        <p id="courseGradePreview" class="text-2xl font-extrabold text-maroon-900 mt-1" aria-live="polite"><?php echo $result['grade'] === null ? scoreText($result['status']) : number_format($result['grade'], 2); ?></p>
                        <p id="coursePercentagePreview" class="text-sm text-stone-500 mt-1"><?php if ($result['percentage'] !== null) echo number_format($result['percentage'], 2) . ($result['status'] === 'In Progress' ? '% earned so far' : '% overall'); ?></p>
                    </div>
                    <span id="scoreSaveStatus" class="text-xs text-stone-500"><?php echo $errors ? 'Unsaved changes' : 'Saved scores'; ?></span>
                </div>
                <p id="courseCalculationNote" class="text-xs text-stone-500">Each component needs at least one scored entry. Completely blank rows are ignored.</p>
                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-stone-100">
                    <a href="student_dashboard.php#grades" class="text-xs font-bold text-stone-600 hover:text-maroon-800">Back to Calculator</a>
                    <button type="submit" form="courseScoresForm" <?php if (!$components) echo 'disabled'; ?> class="px-5 py-2.5 bg-maroon-900 hover:bg-maroon-800 text-white text-xs font-bold rounded-xl disabled:opacity-50">Save Scores</button>
                </div>
            </div>
    </main>
    <template id="scoreEntryTemplate">
        <div class="score-entry grid grid-cols-2 sm:grid-cols-[minmax(0,1fr)_7rem_7rem_4rem] gap-3 items-end">
            <label class="col-span-2 sm:col-span-1 text-xs font-semibold text-stone-600">Entry name
                <input type="text" data-field="assessment_title" maxlength="100" class="entry-title mt-1 block w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs outline-none focus:border-maroon-800">
            </label>
            <label class="text-xs font-semibold text-stone-600">Score
                <input type="number" data-field="raw_score" min="0" max="9999999.99" step="0.01" placeholder="0" class="entry-score mt-1 block w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs outline-none focus:border-maroon-800">
            </label>
            <label class="text-xs font-semibold text-stone-600">Max score
                <input type="number" data-field="total_items" min="1" max="2147483647" step="1" placeholder="100" class="entry-max mt-1 block w-full px-3 py-2.5 rounded-xl border border-stone-300 text-xs outline-none focus:border-maroon-800">
            </label>
            <button type="button" class="remove-entry text-xs font-semibold text-rose-600 hover:underline py-2.5 sm:text-right">Remove</button>
        </div>
    </template>
    <script>
        window.courseGradeScale = <?php echo json_encode(pupGradeScale()); ?>;
        window.courseTargetGrade = <?php echo json_encode((float)$course['target_grade']); ?>;
    </script>
    <script src="js/course-scores.js?v=<?php echo filemtime(__DIR__ . '/js/course-scores.js'); ?>"></script>
</body>
</html>
