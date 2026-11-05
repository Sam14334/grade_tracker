<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}
include 'php/db.php';
$userId = (int)$_SESSION['user_id'];
$userName = htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['email']);
$userInitial = strtoupper(substr($userName, 0, 1));

// Fetch only courses enrolled by the signed-in student.
$enrolledStmt = $conn->prepare("
    SELECT gt.template_id, gt.course_code, gt.course_title, gt.course_unit, gt.class_code, ec.enrollment_id, gt.created_at
    FROM grade_templates gt
    JOIN enrolled_courses ec ON gt.template_id = ec.template_id
    WHERE ec.user_id = ?
    ORDER BY gt.created_at DESC
");
$enrolledStmt->execute([$userId]);
$dbEnrolled = [];

$compStmt = $conn->prepare("SELECT component_name, weight FROM template_components WHERE template_id = ?");

while ($row = $enrolledStmt->fetch()) {
    $tId = (int)$row['template_id'];
    $compStmt->execute([$tId]);
    $comps = [];
    while ($c = $compStmt->fetch()) {
        $comps[] = ['name' => $c['component_name'], 'weight' => (int)$c['weight']];
    }
    $dbEnrolled[] = [
        'id' => $tId,
        'templateId' => $tId,
        'enrollmentId' => (int)($row['enrollment_id'] ?? 0),
        'code' => $row['course_code'],
        'title' => $row['course_title'],
        'units' => (float)$row['course_unit'],
        'classCode' => $row['class_code'],
        'components' => $comps
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Grade Tracker - Interactive Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

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
    <style>
        @media print {
            aside, header, .no-print {
                display: none !important;
            }

            main {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }

            .print-card {
                border: 1px solid #ccc !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="bg-[#f8f6f2] text-slate-800 font-sans min-h-screen flex flex-col antialiased">

    <header class="bg-maroon-900 text-white h-14 sticky top-0 z-40 shadow-md flex items-center justify-between px-6 no-print">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center font-bold text-xs border border-white/20">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <span class="font-extrabold text-base tracking-wide">Academic Grade Tracker</span>
        </div>

        <div class="flex items-center space-x-6 text-sm">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-xs text-white">
                    <?php echo $userInitial; ?>
                </div>
                <span class="font-semibold text-xs sm:text-sm"><?php echo $userName; ?></span>
            </div>

            <a href="php/logout.php" class="text-xs font-semibold text-maroon-200 hover:text-white transition flex items-center space-x-1.5">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <div class="flex flex-1 w-full">
        <aside class="w-64 bg-[#f1ede6] border-r border-stone-300 p-4 shrink-0 flex flex-col justify-between hidden md:flex no-print">
            <div class="space-y-1">
                <button onclick="switchTab('dashboard')" id="nav-dashboard" class="w-full text-left px-4 py-3 rounded-xl font-bold text-xs flex items-center space-x-3 bg-maroon-100 text-maroon-900 border border-maroon-200 cursor-pointer">
                    <i class="fa-solid fa-table-cells-large text-sm"></i>
                    <span>Dashboard</span>
                </button>

                <button onclick="switchTab('grades')" id="nav-grades" class="w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3 cursor-pointer">
                    <i class="fa-solid fa-pen-to-square text-sm"></i>
                    <span>Grade Entry & Calculator</span>
                </button>

                <button onclick="switchTab('templates')" id="nav-templates" class="w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3 cursor-pointer">
                    <i class="fa-solid fa-sliders text-sm"></i>
                    <span>Professor Templates</span>
                </button>
<<<<<<< Updated upstream
=======

                <button onclick="switchTab('predictor')" id="nav-predictor" class="w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3 cursor-pointer">
                    <i class="fa-solid fa-calculator text-sm"></i>
                    <span>Grade Predictor</span>
                </button>
>>>>>>> Stashed changes
            </div>

            <div class="pt-4 border-t border-stone-300/80 text-[11px] text-stone-500 font-medium">
                <p>IT Elective - Software Eng.</p>
                <p class="text-[10px] text-stone-400">PUP Biñan Campus • Student</p>
            </div>
        </aside>

        <main class="flex-1 p-6 sm:p-8 space-y-6 max-w-7xl">

            <div class="flex items-center justify-between pb-2 border-b border-stone-300/60">
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Welcome, <?php echo $userName; ?>!</h2>
                <div class="text-right">
                    <span class="text-xs sm:text-sm font-semibold text-stone-500">Sep 25, 2026</span>
                    <button onclick="window.print()" class="block text-[11px] text-maroon-800 font-bold hover:underline no-print cursor-pointer">
                        <i class="fa-solid fa-print mr-1"></i>Export Target PDF
                    </button>
                </div>
            </div>

            <div id="tab-content-dashboard" class="space-y-6">

                <div id="ruleDiagnosticBox" class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-2xl flex items-start space-x-3 shadow-sm transition-all duration-300">
                    <i class="fa-solid fa-award text-amber-600 text-lg mt-0.5"></i>
                    <div class="text-xs text-amber-900 space-y-1 w-full">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm" id="bannerTargetTitle">Academic Honor Qualification Status</span>
                            <span class="font-black text-emerald-700 text-xs" id="bannerTargetBadge">Target Goal: GWA ≤ 1.45</span>
                        </div>
                        <p id="ruleDiagnosticText">Enter grades for your enrolled courses to calculate your GWA.</p>
                    </div>
                </div>

<<<<<<< Updated upstream
                <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-2 print-card">
                    <h4 class="font-bold text-slate-800 text-sm flex items-center justify-between">
                        <span><i class="fa-solid fa-chart-column text-maroon-800 mr-2"></i>Subject GWA Analytics</span>
                        <span class="text-[10px] text-stone-400">Live Statistics View</span>
                    </h4>
                    <div id="student_trend_chart" class="w-full h-52"></div>
=======
                <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm grid grid-cols-1 gap-6 print-card">
                    <div class="space-y-4 max-w-xl">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-slate-800 text-base">Upcoming Exams</h3>
                            <button type="button" onclick="addExamPrompt()" class="text-xs font-bold text-maroon-800 bg-maroon-50 hover:bg-maroon-100 px-2.5 py-1 rounded-full transition cursor-pointer">
                                + Add Exam
                            </button>
                        </div>

                        <div class="space-y-2.5" id="upcomingExamsContainer">
                        </div>

                        <div class="p-4 bg-maroon-50 rounded-2xl border border-maroon-200 space-y-2">
                            <p class="text-xs font-bold text-maroon-900 uppercase tracking-wider">Exam Target Predictor</p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-stone-600">Req. Exam Score for <strong class="targetGwaSpan text-maroon-900">1.45</strong> GWA Goal:</span>
                                <span class="text-base font-black text-maroon-800" id="reqExamScoreDisplay">84.5%</span>
                            </div>
                        </div>
                    </div>
>>>>>>> Stashed changes
                </div>
            </div>

            <div id="tab-content-grades" class="hidden space-y-6">
                <?php if (($_GET['unenrolled'] ?? '') === 'success'): ?>
                    <p role="status" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl text-sm">Course removed from your enrollments.</p>
                <?php elseif (($_GET['unenrolled'] ?? '') === 'error'): ?>
                    <p role="alert" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl text-sm">Could not remove the course. Please try again.</p>
                <?php endif; ?>
                <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Input & Calculate Subject Grades</h3>
                            <p class="text-xs text-stone-500">Your enrolled courses appear below. Enter grades (1.00 - 5.00) to calculate your GWA. Blank grades are excluded; entered grades last until you reload this page.</p>
                        </div>
                        <button type="button" onclick="recalculateGradesAndStats()" class="px-5 py-2.5 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl shadow-sm transition flex items-center space-x-2 cursor-pointer">
                            <i class="fa-solid fa-rotate"></i>
                            <span>Update Statistics</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-stone-100 text-stone-600 font-bold border-b border-stone-200">
                                    <th class="p-3">Course Code</th>
                                    <th class="p-3">Course Title</th>
                                    <th class="p-3">Units</th>
                                    <th class="p-3">Grade Scale (1.00 - 5.00)</th>
                                    <th class="p-3">Target Grade (1.00 - 5.00)</th>
                                    <th class="p-3">Status Diagnostic</th>
                                    <th class="p-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-200 text-slate-700" id="gradeInputTableBody">
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 bg-stone-50 rounded-2xl border border-stone-200 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex space-x-6 text-xs">
                            <div>
                                <span class="text-stone-500 font-medium">Total Units:</span>
                                <span class="font-bold text-slate-800 ml-1" id="totalUnitsDisplay">0.0</span>
                            </div>
                            <div>
                                <span class="text-stone-500 font-medium">Calculated GWA:</span>
                                <span class="font-black text-maroon-900 ml-1 text-sm" id="calcGwaVal">--</span>
                            </div>
                        </div>

                        <button type="button" onclick="addSubjectRow()" class="px-3 py-1.5 bg-stone-200 hover:bg-stone-300 text-stone-800 font-bold text-xs rounded-lg transition cursor-pointer">
                            + Add Course Row
                        </button>
                    </div>
                </div>
            </div>

            <div id="tab-content-templates" class="hidden space-y-6">
                <?php if (isset($_GET['error']) && is_string($_GET['error'])): ?>
                    <p role="alert" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl text-sm"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif (($_GET['enrolled'] ?? '') === 'success'): ?>
                    <p role="status" class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl text-sm">Class enrolled successfully. Your professor's grading template is shown below.</p>
                <?php endif; ?>
                <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 text-lg">Inherit & Sync Class Grading Code</h3>
                    <p class="text-xs text-stone-500 leading-relaxed">
                        Enter a unique Class Code provided by your instructor to automatically distribute custom grading weights into your personal grade tracker.
                    </p>

                    <form action="php/enroll_course.php" method="POST" class="flex gap-3 max-w-md">
                        <input type="text" name="templateCodeInput" id="templateCodeInput" placeholder="Enter Class Code (e.g. CS301-SEC1)" required class="w-full px-4 py-2.5 rounded-xl border border-stone-300 text-xs font-bold uppercase outline-none focus:border-maroon-800">
<<<<<<< Updated upstream
                        <button type="submit" name="btnEnrollCourse" class="px-5 py-2.5 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl transition shrink-0">
=======
                        <button type="submit" class="px-5 py-2.5 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl transition shrink-0 cursor-pointer">
>>>>>>> Stashed changes
                            Enroll & Sync
                        </button>
                    </form>
                </div>
<<<<<<< Updated upstream
                            
=======

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-7 bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-5">
                        <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-base flex items-center space-x-2">
                                <i class="fa-solid fa-sliders text-maroon-800"></i>
                                <span>Template Builder Preview</span>
                            </h3>
                            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200" id="weightTotalBadge">Total Weight: 100%</span>
                        </div>

                        <form id="publishTemplateForm" action="php/publish_template.php" method="POST" onsubmit="return validatePublishForm(event)" class="space-y-4 text-xs">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold uppercase text-stone-600 mb-1">Course Code</label>
                                    <input type="text" name="courseCode" id="courseCodeInput" value="CS-301" required class="w-full px-3 py-2 rounded-xl border border-stone-300 font-bold text-slate-800 focus:border-maroon-800 outline-none">
                                </div>
                                <div>
                                    <label class="block font-bold uppercase text-stone-600 mb-1">Course Title</label>
                                    <input type="text" name="courseTitle" id="courseTitleInput" value="Software Engineering" required class="w-full px-3 py-2 rounded-xl border border-stone-300 font-bold text-slate-800 focus:border-maroon-800 outline-none">
                                </div>
                            </div>

                            <div class="space-y-3 pt-2">
                                <label class="block font-bold uppercase text-stone-600">Assessment Components & Weights</label>

                                <div class="space-y-2" id="componentContainer">
                                    <div class="flex items-center space-x-2 component-row">
                                        <input type="text" name="component_name[]" value="Quizzes & Seatwork" required class="comp-name flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
                                        <input type="number" name="weight[]" value="30" min="1" max="100" onchange="calculateTotalWeight()" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
                                        <span class="font-bold text-stone-400">%</span>
                                        <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2 cursor-pointer"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>

                                    <div class="flex items-center space-x-2 component-row">
                                        <input type="text" name="component_name[]" value="Midterm Examination" required class="comp-name flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
                                        <input type="number" name="weight[]" value="30" min="1" max="100" onchange="calculateTotalWeight()" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
                                        <span class="font-bold text-stone-400">%</span>
                                        <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2 cursor-pointer"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>

                                    <div class="flex items-center space-x-2 component-row">
                                        <input type="text" name="component_name[]" value="Final Project & Exam" required class="comp-name flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
                                        <input type="number" name="weight[]" value="40" min="1" max="100" onchange="calculateTotalWeight()" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
                                        <span class="font-bold text-stone-400">%</span>
                                        <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2 cursor-pointer"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </div>

                                <button type="button" onclick="addComponentRow()" class="text-xs font-bold text-maroon-800 hover:text-maroon-900 transition flex items-center space-x-1 cursor-pointer">
                                    <i class="fa-solid fa-circle-plus"></i>
                                    <span>Add Assessment Component</span>
                                </button>
                            </div>

                            <div class="pt-4 border-t border-stone-200">
                                <button type="submit" name="btnPublishTemplate" class="w-full py-3 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl shadow-md transition text-center cursor-pointer">
                                    Publish Template & Generate Class Code
                                </button>
                            </div>
                        </form>
                    </div>

>>>>>>> Stashed changes
                    <div class="lg:col-span-5 space-y-4">
                        <h3 class="font-bold text-slate-800 text-base">Enrolled Professor Templates</h3>
                        <div class="space-y-3" id="publishedTemplatesList">
                            <?php if (empty($dbEnrolled)): ?>
                                <p class="text-xs text-stone-400 italic">No enrolled templates found.</p>
                            <?php else: ?>
                                <?php foreach ($dbEnrolled as $template): ?>
                                    <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-3">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <span class="text-[10px] font-bold text-maroon-800 bg-maroon-50 px-2 py-0.5 rounded-full border border-maroon-200">ACTIVE SYNC</span>
                                                <h4 class="font-extrabold text-slate-900 text-base mt-1"><?php echo htmlspecialchars($template['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <p class="text-xs text-stone-500 font-medium"><?php echo htmlspecialchars($template['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="text-xs text-stone-500 font-medium"><?php echo number_format($template['units'], 1); ?> units</p>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-[10px] uppercase font-bold text-stone-400 block">Class Code</span>
                                                <span class="font-mono font-black text-maroon-900 text-sm bg-stone-100 px-2.5 py-1 rounded-lg border border-stone-200 inline-block mt-0.5"><?php echo htmlspecialchars($template['classCode'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        </div>
                                        <div class="bg-stone-50 p-3 rounded-2xl border border-stone-200 text-xs space-y-1.5">
                                            <?php foreach ($template['components'] as $component): ?>
                                                <div class="flex justify-between text-stone-600">
                                                    <span><?php echo htmlspecialchars($component['name'], ENT_QUOTES, 'UTF-8'); ?>:</span>
                                                    <span class="font-bold text-slate-800"><?php echo (int)$component['weight']; ?>%</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
            </div>

<<<<<<< Updated upstream
            
=======
            <!-- Grade Predictor Tab -->
            <div id="tab-content-predictor" class="hidden space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-100 pb-4">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Grade Predictor & Scenario Simulator</h3>
                            <p class="text-xs text-stone-500">Select an enrolled course template to load its official instructor components and simulate target scores.</p>
                        </div>
                        <div class="w-full sm:w-72">
                            <label class="block text-[10px] font-bold uppercase text-stone-500 mb-1">Select Enrolled Course</label>
                            <select id="predictorCourseSelect" onchange="loadPredictorTemplate()" class="w-full px-3 py-2 rounded-xl border border-stone-300 text-xs font-bold text-slate-800 bg-white outline-none focus:border-maroon-800 cursor-pointer">
                                <option value="">-- Choose Course Template --</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 pt-2">
                        <div class="lg:col-span-7 space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-slate-800 text-sm">Instructor Grading Components</h4>
                                <span class="text-xs font-bold text-maroon-800 bg-maroon-50 px-2.5 py-1 rounded-full border border-maroon-100" id="predictorTotalWeightBadge">Total Weight: 100%</span>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-stone-100 text-stone-600 font-bold border-b border-stone-200">
                                            <th class="p-3">Component Name</th>
                                            <th class="p-3 text-center">Instructor Weight</th>
                                            <th class="p-3 text-center">Target Score (%)</th>
                                            <th class="p-3 text-right">Weighted Contribution</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-200 text-slate-700" id="predictorComponentsTableBody">
                                        <tr>
                                            <td colspan="4" class="p-4 text-center text-stone-400 italic">Please select an enrolled course template above to load components.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="lg:col-span-5 bg-stone-50 p-6 rounded-2xl border border-stone-200 flex flex-col justify-between space-y-6">
                            <div class="space-y-4">
                                <h4 class="font-bold text-slate-800 text-sm flex items-center space-x-2">
                                    <i class="fa-solid fa-calculator text-maroon-800"></i>
                                    <span>Simulated Final Standing</span>
                                </h4>
                                <div class="p-4 bg-white rounded-xl border border-stone-200 shadow-sm text-center space-y-1">
                                    <span class="text-[10px] uppercase font-bold text-stone-400 block">Projected Numerical Grade</span>
                                    <span class="text-3xl font-black text-maroon-900" id="projectedGradeDisplay">1.00</span>
                                    <span class="block text-[11px] font-semibold text-emerald-700 pt-1" id="projectedStatusText">Status: Excellent Standing</span>
                                </div>
                            </div>

                            <button type="button" onclick="alert('Simulation refreshed successfully!')" class="w-full py-3 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl shadow-md transition text-center cursor-pointer">
                                Simulate Scenario
                            </button>
                        </div>
                    </div>
                </div>
            </div>
>>>>>>> Stashed changes

        </main>
    </div>


    <!-- External Linked JavaScript File -->
    <script>
<<<<<<< Updated upstream
        window.studentDashboardData = {
            serverRenderedTemplates: true,
            enrolledCourses: <?php echo json_encode($dbEnrolled, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>
        };
=======
        google.charts.load('current', { 'packages': ['corechart'] });
        google.charts.setOnLoadCallback(() => renderStudentTrendChart());

        let targetGwaGoal = parseFloat(localStorage.getItem('targetGwaGoal')) || 1.45;

        let studentGradesData = [
            { id: 1, subject: 'CS-301', title: 'Software Engineering', grade: 1.25, target: 1.00, units: 3.0 },
            { id: 2, subject: 'BIO-105', title: 'General Biology', grade: 1.50, target: 1.25, units: 3.0 },
            { id: 3, subject: 'MATH-201', title: 'Discrete Mathematics', grade: 1.25, target: 1.25, units: 3.0 },
            { id: 4, subject: 'IT-ELECT', title: 'Web Systems Development', grade: 1.00, target: 1.00, units: 3.0 }
        ];

        let upcomingExams = [
            { id: 1, title: 'CS-301 Midterm', target: 'Target Weight: 30%', date: 'Sep 28', active: true },
            { id: 2, title: 'BIO-105 Quiz', target: 'Target Weight: 15%', date: 'Oct 3', active: false },
            { id: 3, title: 'MATH-201 Quiz', target: 'Target Weight: 15%', date: 'Oct 10', active: false }
        ];

        let enrolledTemplates = <?php echo json_encode($dbEnrolled); ?>;

        document.addEventListener("DOMContentLoaded", () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('template') === 'success') {
                const code = urlParams.get('code') ? `\nClass Sync Code: ${urlParams.get('code')}` : '';
                alert(`Template published and saved to database!${code}`);
                switchTab('templates');
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('enrolled') === 'success') {
                alert("Successfully enrolled and synced class template!");
                switchTab('templates');
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('error')) {
                alert(decodeURIComponent(urlParams.get('error')));
                switchTab('templates');
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            renderGradesTable();
            renderExamsList();
            renderTemplatesList();
            populatePredictorDropdown();
            recalculateGradesAndStats(false);
        });

        function switchTab(tabId) {
            ['dashboard', 'grades', 'templates', 'predictor'].forEach(id => {
                const el = document.getElementById(`tab-content-${id}`);
                if (el) el.classList.add('hidden');

                const btn = document.getElementById(`nav-${id}`);
                if (btn) {
                    btn.className = "w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3 cursor-pointer";
                }
            });

            const targetTab = document.getElementById(`tab-content-${tabId}`);
            if (targetTab) targetTab.classList.remove('hidden');

            const activeBtn = document.getElementById(`nav-${tabId}`);
            if (activeBtn) {
                activeBtn.className = "w-full text-left px-4 py-3 rounded-xl font-bold text-xs flex items-center space-x-3 bg-maroon-100 text-maroon-900 border border-maroon-200 cursor-pointer";
            }

            if (tabId === 'dashboard') {
                renderStudentTrendChart();
            }
        }

        function recalculateGradesAndStats(showAlert = true) {
            const inputs = document.querySelectorAll('.grade-input');
            let totalWeightedGrades = 0;
            let totalUnits = 0;
            let hasGradeBelow250 = false;

            if (inputs.length > 0) {
                inputs.forEach(input => {
                    const val = parseFloat(input.value) || 3.00;
                    const id = parseInt(input.getAttribute('data-id'));
                    const units = parseFloat(input.getAttribute('data-units')) || 3.0;

                    const item = studentGradesData.find(g => g.id === id);
                    if (item) item.grade = val;

                    if (val > 2.50) hasGradeBelow250 = true;

                    totalWeightedGrades += (val * units);
                    totalUnits += units;
                });
            } else {
                studentGradesData.forEach(item => {
                    if (item.grade > 2.50) hasGradeBelow250 = true;
                    totalWeightedGrades += (item.grade * item.units);
                    totalUnits += item.units;
                });
            }

            const calculatedGWA = totalUnits > 0 ? (totalWeightedGrades / totalUnits).toFixed(2) : "0.00";

            document.getElementById('calcGwaVal').innerText = calculatedGWA;
            document.getElementById('gwaValueDisplay').innerText = calculatedGWA;
            document.getElementById('totalUnitsDisplay').innerText = totalUnits.toFixed(1);

            document.querySelectorAll('.targetGwaSpan').forEach(span => {
                span.innerText = targetGwaGoal.toFixed(2);
            });

            const currentNum = parseFloat(calculatedGWA);
            const activeExamWeight = 0.30;
            const currentPercentage = 100 - ((currentNum - 1.0) * 20);
            const targetPercentage = 100 - ((targetGwaGoal - 1.0) * 20);

            let requiredPercentage = (targetPercentage - (currentPercentage * (1 - activeExamWeight))) / activeExamWeight;
            requiredPercentage = Math.max(50, Math.min(100, requiredPercentage));

            document.getElementById('reqExamScoreDisplay').innerText = `${requiredPercentage.toFixed(1)}%`;

            const diagBox = document.getElementById('ruleDiagnosticBox');
            const diagText = document.getElementById('ruleDiagnosticText');
            const badge = document.getElementById('bannerTargetBadge');

            badge.innerText = `Target Goal: GWA ≤ ${targetGwaGoal.toFixed(2)}`;

            if (parseFloat(calculatedGWA) <= targetGwaGoal && !hasGradeBelow250) {
                diagBox.className = "bg-amber-50 border-l-4 border-amber-500 p-4 rounded-2xl flex items-start space-x-3 shadow-sm transition-all duration-300";
                diagText.innerHTML = `Current Estimated GWA: <strong class="text-maroon-900">${calculatedGWA}</strong> (Qualifies for Target Goal GWA ≤ <strong>${targetGwaGoal.toFixed(2)}</strong>). All grades are 2.50 or higher.`;
            } else if (parseFloat(calculatedGWA) <= targetGwaGoal && hasGradeBelow250) {
                diagBox.className = "bg-orange-50 border-l-4 border-orange-500 p-4 rounded-2xl flex items-start space-x-3 shadow-sm transition-all duration-300";
                diagText.innerHTML = `Current Estimated GWA: <strong class="text-orange-900">${calculatedGWA}</strong> (Meets target GWA but has grades below 2.50).`;
            } else {
                diagBox.className = "bg-rose-50 border-l-4 border-rose-500 p-4 rounded-2xl flex items-start space-x-3 shadow-sm transition-all duration-300";
                diagText.innerHTML = `Current Estimated GWA: <strong class="text-rose-900">${calculatedGWA}</strong> (Needs improvement to achieve Target Goal GWA ≤ <strong>${targetGwaGoal.toFixed(2)}</strong>).`;
            }

            renderGradesTable();
            renderStudentTrendChart();

            if (showAlert) {
                alert(`Statistics successfully updated! Calculated GWA: ${calculatedGWA}`);
                switchTab('dashboard');
            }
        }

        function renderGradesTable() {
            const tbody = document.getElementById('gradeInputTableBody');
            tbody.innerHTML = '';

            studentGradesData.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td class="p-3 font-bold text-maroon-900">${item.subject}</td>
                <td class="p-3 font-medium">${item.title}</td>
                <td class="p-3 font-bold">${item.units.toFixed(1)}</td>
                <td class="p-3">
                    <input type="number" step="0.25" min="1.00" max="5.00" value="${item.grade.toFixed(2)}"
                           class="grade-input w-28 px-3 py-1.5 rounded-lg border border-stone-300 font-bold text-xs focus:border-maroon-800 outline-none"
                           data-id="${item.id}" data-subject="${item.subject}" data-units="${item.units}">
                </td>
                <td class="p-3">
                    <span class="${item.grade <= 2.50 ? 'text-emerald-700' : 'text-rose-700'} font-bold text-[11px]">
                        <i class="fa-solid ${item.grade <= 2.50 ? 'fa-check-circle' : 'fa-triangle-exclamation'} mr-1"></i>
                        ${item.grade <= 2.50 ? 'PL Valid (≤ 2.50)' : 'PL Ineligible (> 2.50)'}
                    </span>
                </td>
                <td class="p-3 text-center">
                    <button type="button" onclick="removeSubjectRow(${item.id})" class="text-rose-500 hover:text-rose-700 transition cursor-pointer">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
                tbody.appendChild(tr);
            });
        }

        function renderExamsList() {
            const container = document.getElementById('upcomingExamsContainer');
            container.innerHTML = '';

            upcomingExams.forEach(exam => {
                const div = document.createElement('div');
                div.className = "p-3 bg-stone-50 rounded-xl border border-stone-200 flex items-center justify-between";
                div.innerHTML = `
                <div>
                    <p class="font-bold text-xs text-slate-800">${exam.title}</p>
                    <p class="text-[10px] text-stone-500">${exam.target}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold ${exam.active ? 'text-maroon-800' : 'text-stone-700'} bg-white px-2.5 py-1 rounded-lg border border-stone-200">${exam.date}</span>
                    <button type="button" onclick="removeExam(${exam.id})" class="text-stone-400 hover:text-rose-600 text-xs px-1 cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
                </div>
            `;
                container.appendChild(div);
            });
        }

        function renderTemplatesList() {
            const container = document.getElementById('publishedTemplatesList');
            container.innerHTML = '';

            if (enrolledTemplates.length === 0) {
                container.innerHTML = `<p class="text-xs text-stone-400 italic">No enrolled templates found.</p>`;
                return;
            }

            enrolledTemplates.forEach(tpl => {
                const card = document.createElement('div');
                card.className = "bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-3";

                let componentsHtml = tpl.components.map(c => `
                <div class="flex justify-between text-stone-600">
                    <span>${c.name}:</span>
                    <span class="font-bold text-slate-800">${c.weight}%</span>
                </div>
            `).join('');

                card.innerHTML = `
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-maroon-800 bg-maroon-50 px-2 py-0.5 rounded-full border border-maroon-200">ACTIVE SYNC</span>
                        <h4 class="font-extrabold text-slate-900 text-base mt-1">${tpl.code}</h4>
                        <p class="text-xs text-stone-500 font-medium">${tpl.title}</p>
                    </div>
                    <div class="text-right flex items-center space-x-3">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-stone-400 block">Class Code</span>
                            <span class="font-mono font-black text-maroon-900 text-sm bg-stone-100 px-2.5 py-1 rounded-lg border border-stone-200 inline-block mt-0.5">${tpl.classCode}</span>
                        </div>
                        <button type="button" onclick="removeTemplate(${tpl.id})" class="text-stone-400 hover:text-rose-600 transition text-sm cursor-pointer">
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

        function populatePredictorDropdown() {
            const select = document.getElementById('predictorCourseSelect');
            if (!select) return;
            select.innerHTML = '<option value="">-- Choose Course Template --</option>';
            enrolledTemplates.forEach(tpl => {
                const opt = document.createElement('option');
                opt.value = tpl.id;
                opt.textContent = `${tpl.code} - ${tpl.title}`;
                select.appendChild(opt);
            });
        }

        function loadPredictorTemplate() {
            const selectId = document.getElementById('predictorCourseSelect').value;
            const tbody = document.getElementById('predictorComponentsTableBody');
            tbody.innerHTML = '';

            if (!selectId) {
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-stone-400 italic">Please select an enrolled course template above to load components.</td></tr>`;
                document.getElementById('projectedGradeDisplay').innerText = '1.00';
                return;
            }

            const tpl = enrolledTemplates.find(t => t.id == selectId);
            if (!tpl || !tpl.components || tpl.components.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-stone-400 italic">No grading components found for this template.</td></tr>`;
                return;
            }

            let totalWeight = 0;
            tpl.components.forEach((comp, idx) => {
                totalWeight += parseInt(comp.weight || 0);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="p-3 font-bold text-slate-800">${comp.name}</td>
                    <td class="p-3 text-center font-semibold">${comp.weight}%</td>
                    <td class="p-3 text-center">
                        <input type="number" min="0" max="100" value="90" oninput="calculatePrediction()" class="predictor-score w-20 px-2 py-1 rounded-lg border border-stone-300 text-center font-bold text-xs outline-none focus:border-maroon-800" data-weight="${comp.weight}">
                    </td>
                    <td class="p-3 text-right font-bold text-maroon-900 predictor-contrib">0.0%</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('predictorTotalWeightBadge').innerText = `Total Weight: ${totalWeight}%`;
            calculatePrediction();
        }

        function calculatePrediction() {
            const rows = document.querySelectorAll('#predictorComponentsTableBody tr');
            let totalWeightedScore = 0;

            rows.forEach(tr => {
                const scoreInput = tr.querySelector('.predictor-score');
                const contribTd = tr.querySelector('.predictor-contrib');
                if (scoreInput && contribTd) {
                    const weight = parseFloat(scoreInput.getAttribute('data-weight')) || 0;
                    const score = parseFloat(scoreInput.value) || 0;
                    const contrib = (score * (weight / 100));
                    contribTd.innerText = `${contrib.toFixed(1)}%`;
                    totalWeightedScore += contrib;
                }
            });

            let projectedGrade = 5.00;
            if (totalWeightedScore >= 96) projectedGrade = 1.00;
            else if (totalWeightedScore >= 91) projectedGrade = 1.25;
            else if (totalWeightedScore >= 86) projectedGrade = 1.50;
            else if (totalWeightedScore >= 81) projectedGrade = 1.75;
            else if (totalWeightedScore >= 76) projectedGrade = 2.00;
            else if (totalWeightedScore >= 71) projectedGrade = 2.25;
            else if (totalWeightedScore >= 66) projectedGrade = 2.50;
            else if (totalWeightedScore >= 61) projectedGrade = 2.75;
            else if (totalWeightedScore >= 60) projectedGrade = 3.00;
            else projectedGrade = 5.00;

            document.getElementById('projectedGradeDisplay').innerText = projectedGrade.toFixed(2);
            
            const statusText = document.getElementById('projectedStatusText');
            if (projectedGrade <= 1.45) {
                statusText.innerText = "Status: President's Lister Standing";
                statusText.className = "block text-[11px] font-semibold text-emerald-700 pt-1";
            } else if (projectedGrade <= 1.75) {
                statusText.innerText = "Status: Dean's Lister Standing";
                statusText.className = "block text-[11px] font-semibold text-amber-700 pt-1";
            } else if (projectedGrade <= 3.00) {
                statusText.innerText = "Status: Passing Grade";
                statusText.className = "block text-[11px] font-semibold text-blue-700 pt-1";
            } else {
                statusText.innerText = "Status: Failing Standing";
                statusText.className = "block text-[11px] font-semibold text-rose-700 pt-1";
            }
        }

        function renderStudentTrendChart() {
            if (!google.visualization) return;
            const data = new google.visualization.DataTable();
            data.addColumn('string', 'Subject');
            data.addColumn('number', 'Current Grade');
            data.addColumn('number', 'Target Grade');

            studentGradesData.forEach(item => {
                data.addRow([item.subject, item.grade, item.target]);
            });

            const options = {
                colors: ['#4a0415', '#3b82f6'],
                vAxis: {
                    title: 'PUP Grade Scale (1.00 - 3.00)',
                    direction: -1,
                    ticks: [1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00, 5.00]
                },
                legend: { position: 'bottom' },
                chartArea: { width: '80%', height: '70%' }
            };

            const chart = new google.visualization.ColumnChart(document.getElementById('student_trend_chart'));
            chart.draw(data, options);
        }

        function addSubjectRow() {
            const subjCode = prompt("Enter Course Code:", "PHYS-101");
            if (!subjCode) return;
            const title = prompt("Enter Course Title:", "General Physics") || "Core Subject";
            const units = parseFloat(prompt("Enter Unit Value:", "3.0")) || 3.0;

            studentGradesData.push({ id: Date.now(), subject: subjCode.toUpperCase(), title: title, grade: 1.50, target: 1.00, units: units });
            recalculateGradesAndStats(false);
        }

        function removeSubjectRow(id) {
            if (confirm("Are you sure you want to remove this course from your tracker?")) {
                studentGradesData = studentGradesData.filter(item => item.id !== id);
                recalculateGradesAndStats(false);
            }
        }

        function addExamPrompt() {
            const title = prompt("Enter Exam Title (e.g. CS-301 Final Exam):");
            if (!title) return;
            const date = prompt("Enter Scheduled Date (e.g. Oct 15):", "Oct 15") || "TBD";
            const weight = prompt("Enter Exam Weight:", "Target Weight: 25%") || "Target Weight: 20%";

            upcomingExams.push({ id: Date.now(), title, target: weight, date, active: true });
            renderExamsList();
        }

        function removeExam(id) {
            upcomingExams = upcomingExams.filter(e => e.id !== id);
            renderExamsList();
        }

        function addComponentRow() {
            const container = document.getElementById('componentContainer');
            const row = document.createElement('div');
            row.className = 'flex items-center space-x-2 component-row';
            row.innerHTML = `
            <input type="text" name="component_name[]" placeholder="Assessment Name" required class="comp-name flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
            <input type="number" name="weight[]" value="10" min="1" max="100" onchange="calculateTotalWeight()" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
            <span class="font-bold text-stone-400">%</span>
            <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2 cursor-pointer"><i class="fa-solid fa-trash-can"></i></button>
        `;
            container.appendChild(row);
            calculateTotalWeight();
        }

        function removeRow(btn) {
            btn.closest('.component-row').remove();
            calculateTotalWeight();
        }

        function calculateTotalWeight() {
            const weights = document.querySelectorAll('.comp-weight');
            let total = 0;
            weights.forEach(w => total += parseFloat(w.value) || 0);

            const badge = document.getElementById('weightTotalBadge');
            badge.innerText = `Total Weight: ${total}%`;
            badge.className = total === 100
                ? "text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200"
                : "text-xs font-bold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200";

            return total;
        }

        function validatePublishForm(e) {
            const total = calculateTotalWeight();
            if (total !== 100) {
                alert(`Total weight must equal 100%. Current total is ${total}%.`);
                return false;
            }
            const code = document.getElementById('courseCodeInput').value.trim();
            const title = document.getElementById('courseTitleInput').value.trim();
            if (!code || !title) {
                alert('Please enter Course Code and Title.');
                return false;
            }
            const submitBtn = document.querySelector('button[name="btnPublishTemplate"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerText = 'Publishing...';
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'btnPublishTemplate';
                hiddenInput.value = '1';
                e.target.appendChild(hiddenInput);
            }
            return true;
        }

        function removeTemplate(id) {
            if (confirm("Remove this course template?")) {
                window.location.href = `php/delete_template.php?id=${id}`;
            }
        }

        function logout() { window.location.href = 'php/logout.php'; }
>>>>>>> Stashed changes
    </script>
    <script src="./js/student-dashboard.js?v=<?php echo filemtime(__DIR__ . '/js/student-dashboard.js'); ?>"></script>
</body>
</html>
