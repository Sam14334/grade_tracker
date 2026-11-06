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
$userId = (int)$_SESSION['user_id'];
$userName = htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['email']);
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal - Professor Dashboard</title>
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
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <span class="font-extrabold text-base tracking-wide">Grade Tracker</span>
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
                <p class="px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-stone-400">Main Menu</p>
                
                <button onclick="switchTab('overview')" id="nav-overview" class="w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3 cursor-pointer">
                    <i class="fa-solid fa-chart-pie text-sm"></i>
                    <span>Faculty Overview</span>
                </button>

                <button onclick="switchTab('standing')" id="nav-standing" class="w-full text-left px-4 py-3 rounded-xl font-bold text-xs flex items-center space-x-3 bg-maroon-100 text-maroon-900 border border-maroon-200 cursor-pointer">
                    <i class="fa-solid fa-users-rectangle text-sm"></i>
                    <span>Class Standing</span>
                </button>
            </div>

            <div class="pt-4 border-t border-stone-300/80 text-[11px] text-stone-500 font-medium space-y-1">
                <p class="font-bold text-stone-700">1st Semester 2026</p>
                <p class="text-[10px] text-stone-400">PUP Biñan Campus • Professor</p>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="flex-1 p-6 sm:p-8 space-y-6 max-w-7xl">

            <!-- 1. Faculty Overview Tab Content -->
            <div id="tab-content-overview" class="hidden space-y-6">
                <div class="flex items-center justify-between pb-2 border-b border-stone-300/60">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Faculty Overview</h2>
                        <p class="text-xs font-semibold text-stone-500 mt-0.5">High-level summary of active faculty courses, department metrics, and general standing.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-2">
                        <span class="text-xs font-bold text-stone-400 uppercase tracking-wider">Active Courses</span>
                        <h3 class="text-3xl font-black text-maroon-900">3 Courses</h3>
                        <p class="text-xs text-stone-500">CS-301, BIO-105, ENG-210</p>
                    </div>

                    <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-2">
                        <span class="text-xs font-bold text-stone-400 uppercase tracking-wider">Total Enrolled Students</span>
                        <h3 class="text-3xl font-black text-slate-900">142 Students</h3>
                        <p class="text-xs text-emerald-700 font-semibold">Active across all sections</p>
                    </div>

                    <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-2">
                        <span class="text-xs font-bold text-stone-400 uppercase tracking-wider">Department Status</span>
                        <h3 class="text-3xl font-black text-slate-900">PUP Biñan</h3>
                        <p class="text-xs text-stone-500">College of Computer Studies</p>
                    </div>
                </div>
            </div>

            <!-- 2. Class Standing Tab Content -->
            <div id="tab-content-standing" class="space-y-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-2 border-b border-stone-300/60">
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Class Standing Overview</h2>
                    <div class="flex items-center space-x-2">
                        <span class="px-3 py-1 bg-maroon-50 text-maroon-900 border border-maroon-200 rounded-full text-xs font-bold flex items-center space-x-1.5">
                            <i class="fa-solid fa-chart-line text-xs"></i>
                            <span>Acceleration Insights Active</span>
                        </span>
                        <span class="px-3 py-1 bg-white text-stone-700 border border-stone-300 rounded-full text-xs font-bold flex items-center space-x-1.5 shadow-sm">
                            <i class="fa-solid fa-filter text-xs text-stone-400"></i>
                            <span>Top Accelerating Delta</span>
                        </span>
                    </div>
                </div>

                <!-- Course Filter Bar -->
                <div class="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center space-x-3 w-full sm:w-auto">
                        <span class="text-xs font-bold uppercase text-stone-500">Course:</span>
                        <select id="courseFilterSelect" onchange="filterByCourse()" class="px-4 py-2 rounded-xl border border-stone-300 bg-white text-xs font-bold text-slate-800 outline-none focus:border-maroon-800 cursor-pointer w-full sm:w-80 shadow-sm">
                            <option value="ALL">All Enrolled Courses (Overall Standing) — 142 Students</option>
                            <option value="CS-301">CS-301 Computer Science — 48 Students</option>
                            <option value="BIO-105">BIO-105 General Biology — 52 Students</option>
                            <option value="ENG-210">ENG-210 English Literature — 42 Students</option>
                        </select>
                    </div>
                    <div class="text-xs text-stone-500 flex items-center space-x-2">
                        <i class="fa-solid fa-book-open text-stone-400"></i>
                        <span id="filteredViewText">Filtered View: <strong>Currently showing aggregated standings across all active classes</strong></span>
                    </div>
                </div>

                <!-- Metric Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-1">
                        <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider block">Top Rank (#1)</span>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-black text-slate-900" id="metricTopGrade">96.5%</span>
                        </div>
                        <p class="text-xs text-stone-600 font-medium" id="metricTopStudent">Jane Doe <span class="text-[10px] text-stone-400">(CS-301)</span></p>
                    </div>

                    <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-1">
                        <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider block">Class Average</span>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-black text-slate-900" id="metricClassAvg">85.4%</span>
                            <span class="text-[11px] font-bold text-emerald-700">+2.1% from Midterm</span>
                        </div>
                        <p class="text-xs text-stone-600 font-medium">Overall Class Performance</p>
                    </div>

                    <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-1">
                        <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider block">Total Enrolled</span>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-black text-slate-900" id="metricTotalEnrolled">142</span>
                        </div>
                        <p class="text-xs text-stone-600 font-medium" id="metricCourseCount">Across 3 Courses</p>
                    </div>

                    <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-1">
                        <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider block">At Risk (&lt;75%)</span>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-black text-rose-700" id="metricAtRiskCount">2 Students</span>
                        </div>
                        <p class="text-xs text-rose-600 font-bold">Requires Intervention</p>
                    </div>
                </div>

                <!-- Velocity & Acceleration Bar -->
                <div class="bg-white p-5 rounded-3xl border border-stone-200 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-stone-100 pb-3">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-gauge-high text-maroon-800"></i>
                            <h4 class="font-bold text-slate-800 text-sm">Course Acceleration & Velocity Index</h4>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">Monthly Surge</span>
                        </div>
                        <span class="text-xs text-stone-400 font-medium">Updated after Midterm Exam Cycle</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div class="p-4 bg-stone-50 rounded-2xl border border-stone-200 flex justify-between items-center">
                            <div>
                                <span class="text-[10px] font-bold text-stone-400 block uppercase">CS-301 Computer Science</span>
                                <span class="font-bold text-slate-900 text-sm">Avg 87.3%</span>
                                <span class="block text-[10px] text-stone-500">48 Enrolled</span>
                            </div>
                            <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-200 text-xs">+4.8% <small class="block text-[9px] font-normal">High Velocity</small></span>
                        </div>

                        <div class="p-4 bg-stone-50 rounded-2xl border border-stone-200 flex justify-between items-center">
                            <div>
                                <span class="text-[10px] font-bold text-stone-400 block uppercase">BIO-105 General Biology</span>
                                <span class="font-bold text-slate-900 text-sm">Avg 86.4%</span>
                                <span class="block text-[10px] text-stone-500">52 Enrolled</span>
                            </div>
                            <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-200 text-xs">+2.1% <small class="block text-[9px] font-normal">Steady Climb</small></span>
                        </div>

                        <div class="p-4 bg-stone-50 rounded-2xl border border-stone-200 flex justify-between items-center">
                            <div>
                                <span class="text-[10px] font-bold text-stone-400 block uppercase">ENG-210 English Literature</span>
                                <span class="font-bold text-slate-900 text-sm">Avg 80.6%</span>
                                <span class="block text-[10px] text-stone-500">42 Enrolled</span>
                            </div>
                            <span class="font-bold text-rose-700 bg-rose-50 px-2 py-1 rounded-lg border border-rose-200 text-xs">-0.6% <small class="block text-[9px] font-normal">Velocity Stall</small></span>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard Section -->
                <div class="bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-4">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-stone-100 pb-4">
                        <h3 class="font-bold text-slate-800 text-base flex items-center space-x-2">
                            <i class="fa-solid fa-trophy text-amber-500"></i>
                            <span>Class Standing Leaderboard</span>
                        </h3>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" onclick="setCategoryFilter('ALL', this)" class="cat-btn px-3 py-1.5 bg-maroon-900 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer transition">All Students</button>
                            <button type="button" onclick="setCategoryFilter('ACCELERATING', this)" class="cat-btn px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl transition cursor-pointer">Accelerating Students</button>
                            <button type="button" onclick="setCategoryFilter('TOP', this)" class="cat-btn px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl transition cursor-pointer">Top Performers</button>
                            <button type="button" onclick="setCategoryFilter('RISK', this)" class="cat-btn px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl transition cursor-pointer">At Risk (&lt;75%)</button>
                            
                            <div class="relative ml-auto">
                                <input type="text" id="searchInput" oninput="renderTable()" placeholder="Search student..." class="px-3 py-1.5 pl-8 rounded-xl border border-stone-300 text-xs outline-none focus:border-maroon-800 w-48 shadow-sm">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-stone-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Leaderboard Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-stone-100 text-stone-600 font-bold border-b border-stone-200">
                                    <th class="p-3 w-16">Rank</th>
                                    <th class="p-3">Student</th>
                                    <th class="p-3">Course</th>
                                    <th class="p-3">Overall Grade</th>
                                    <th class="p-3">Velocity / Accel</th>
                                    <th class="p-3 text-center">Quizzes (25%)</th>
                                    <th class="p-3 text-center">Midterm (25%)</th>
                                    <th class="p-3 text-center">Project (20%)</th>
                                    <th class="p-3 text-center">Final Exam (30%)</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="leaderboardTableBody" class="divide-y divide-stone-200 text-slate-700 font-medium">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- External JavaScript File -->
    <script src="js/professor-dashboard.js"></script>
</body>
</html>