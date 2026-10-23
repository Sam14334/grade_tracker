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

// fetch enrolled or created courses for this student
$enrolledStmt = $conn->prepare("
    SELECT DISTINCT gt.template_id, gt.course_code, gt.course_title, gt.class_code, ec.enrollment_id, gt.created_at 
    FROM grade_templates gt
    LEFT JOIN enrolled_courses ec ON gt.template_id = ec.template_id AND ec.user_id = ?
    WHERE ec.user_id = ? OR gt.user_id = ?
    ORDER BY gt.created_at DESC
");
$enrolledStmt->execute([$userId, $userId, $userId]);
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
        'classCode' => $row['class_code'],
        'components' => $comps
    ];
}
?>
﻿<!DOCTYPE html>
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
                <button onclick="switchTab('dashboard')" id="nav-dashboard" class="w-full text-left px-4 py-3 rounded-xl font-bold text-xs flex items-center space-x-3 bg-maroon-100 text-maroon-900 border border-maroon-200">
                    <i class="fa-solid fa-table-cells-large text-sm"></i>
                    <span>Dashboard</span>
                </button>

                <button onclick="switchTab('grades')" id="nav-grades" class="w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3">
                    <i class="fa-solid fa-pen-to-square text-sm"></i>
                    <span>Grade Entry & Calculator</span>
                </button>

                <button onclick="switchTab('templates')" id="nav-templates" class="w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3">
                    <i class="fa-solid fa-sliders text-sm"></i>
                    <span>Professor Templates</span>
                </button>
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
                    <button onclick="window.print()" class="block text-[11px] text-maroon-800 font-bold hover:underline no-print">
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
                        <p id="ruleDiagnosticText">Current Estimated GWA: <strong class="text-maroon-900" id="gwaValueDisplay">1.25</strong> (Qualifies for Target Goal GWA ≤ <span class="targetGwaSpan">1.45</span>).</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-6 print-card">

                    <div class="lg:col-span-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-slate-800 text-base">Upcoming Exams</h3>
                            <button onclick="addExamPrompt()" class="text-xs font-bold text-maroon-800 bg-maroon-50 hover:bg-maroon-100 px-2.5 py-1 rounded-full transition">
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

                    <div class="lg:col-span-7 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-slate-800 text-base">Class Schedule Timetable</h3>
                            <button onclick="switchTab('schedule')" class="text-xs font-bold text-maroon-800 hover:underline">Manage Schedule &rarr;</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-center border-collapse text-xs border border-stone-200">
                                <thead>
                                    <tr class="bg-stone-100 text-stone-600 font-bold border-b border-stone-200">
                                        <th class="p-2 border-r border-stone-200 w-20">Time Slot</th>
                                        <th class="p-2 border-r border-stone-200">Mon</th>
                                        <th class="p-2 border-r border-stone-200">Tue</th>
                                        <th class="p-2 border-r border-stone-200">Wed</th>
                                        <th class="p-2 border-r border-stone-200">Thu</th>
                                        <th class="p-2 border-r border-stone-200">Fri</th>
                                        <th class="p-2">Sat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-200 text-slate-700" id="dashboardScheduleMatrix">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-2 print-card">
                        <h4 class="font-bold text-slate-800 text-sm flex items-center justify-between">
                            <span><i class="fa-solid fa-chart-column text-maroon-800 mr-2"></i>Subject GWA Analytics</span>
                            <span class="text-[10px] text-stone-400">Live Statistics View</span>
                        </h4>
                        <div id="student_trend_chart" class="w-full h-52"></div>
                    </div>

                    <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-3 print-card">
                        <h4 class="font-bold text-slate-800 text-sm">
                            <i class="fa-solid fa-award text-maroon-800 mr-2"></i>Academic Honors Criteria Breakdown
                        </h4>
                        <div class="space-y-2.5 text-xs">
                            <div class="p-3 bg-maroon-50 rounded-xl border border-maroon-200 flex justify-between items-center">
                                <div>
                                    <p class="font-bold text-maroon-900">President's Lister Criteria</p>
                                    <p class="text-maroon-700 text-[10px]">GWA ≤ 1.45 & No grade lower than 2.50</p>
                                </div>
                                <span class="font-black text-maroon-800 text-xs px-2 py-1 bg-white rounded border border-maroon-200">Top Tier</span>
                            </div>

                            <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 flex justify-between items-center">
                                <div>
                                    <p class="font-bold text-amber-900">Dean's Lister Criteria</p>
                                    <p class="text-amber-700 text-[10px]">GWA ≤ 1.75</p>
                                </div>
                                <span class="font-black text-amber-800 text-xs px-2 py-1 bg-white rounded border border-amber-200">Second Tier</span>
                            </div>

                            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 flex justify-between items-center">
                                <div>
                                    <p class="font-bold text-emerald-900">Minimum Passing Grade</p>
                                    <p class="text-emerald-700 text-[10px]">GWA ≤ 3.00 & No grade of 5.00 (Failed)</p>
                                </div>
                                <span class="font-black text-emerald-800 text-xs px-2 py-1 bg-white rounded border border-emerald-200">Passing Grade</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div id="tab-content-grades" class="hidden space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Input & Calculate Subject Grades</h3>
                            <p class="text-xs text-stone-500">Enter your numerical grades (1.00 - 5.00) to update your overall statistics across the system.</p>
                        </div>
                        <button onclick="recalculateGradesAndStats()" class="px-5 py-2.5 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl shadow-sm transition flex items-center space-x-2">
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
                                <span class="font-bold text-slate-800 ml-1" id="totalUnitsDisplay">12.0</span>
                            </div>
                            <div>
                                <span class="text-stone-500 font-medium">Calculated GWA:</span>
                                <span class="font-black text-maroon-900 ml-1 text-sm" id="calcGwaVal">1.25</span>
                            </div>
                        </div>

                        <button onclick="addSubjectRow()" class="px-3 py-1.5 bg-stone-200 hover:bg-stone-300 text-stone-800 font-bold text-xs rounded-lg transition">
                            + Add Course Row
                        </button>
                    </div>
                </div>
            </div>

            <div id="tab-content-templates" class="hidden space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 text-lg">Inherit & Sync Class Grading Code</h3>
                    <p class="text-xs text-stone-500 leading-relaxed">
                        Enter a unique Class Code provided by your instructor to automatically distribute custom grading weights into your personal grade tracker.
                    </p>

                    <form action="php/enroll_course.php" method="POST" class="flex gap-3 max-w-md">
                        <input type="text" name="templateCodeInput" id="templateCodeInput" placeholder="Enter Class Code (e.g. CS301-SEC1)" required class="w-full px-4 py-2.5 rounded-xl border border-stone-300 text-xs font-bold uppercase outline-none focus:border-maroon-800">
                        <button type="submit" class="px-5 py-2.5 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl transition shrink-0">
                            Enroll & Sync
                        </button>
                    </form>
                </div>

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
                                        <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>

                                    <div class="flex items-center space-x-2 component-row">
                                        <input type="text" name="component_name[]" value="Midterm Examination" required class="comp-name flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
                                        <input type="number" name="weight[]" value="30" min="1" max="100" onchange="calculateTotalWeight()" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
                                        <span class="font-bold text-stone-400">%</span>
                                        <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>

                                    <div class="flex items-center space-x-2 component-row">
                                        <input type="text" name="component_name[]" value="Final Project & Exam" required class="comp-name flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
                                        <input type="number" name="weight[]" value="40" min="1" max="100" onchange="calculateTotalWeight()" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
                                        <span class="font-bold text-stone-400">%</span>
                                        <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </div>

                                <button type="button" onclick="addComponentRow()" class="text-xs font-bold text-maroon-800 hover:text-maroon-900 transition flex items-center space-x-1 pt-1">
                                    <i class="fa-solid fa-circle-plus"></i>
                                    <span>Add Assessment Component</span>
                                </button>
                            </div>

                            <div class="pt-4 border-t border-stone-200">
                                <button type="submit" name="btnPublishTemplate" class="w-full py-3 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl shadow-md transition text-center">
                                    Publish Template & Generate Class Code
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="lg:col-span-5 space-y-4">
                        <h3 class="font-bold text-slate-800 text-base">Enrolled Professor Templates</h3>
                        <div class="space-y-3" id="publishedTemplatesList">
                        </div>
                    </div>
                </div>
            </div>

            

        </main>
    </div>


    <!-- External Linked JavaScript File -->
    <script src="./js/student-dashboard.js"></script>
</body>
</html>