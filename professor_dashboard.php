<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}
// Enforce professor role access
if (isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'Student') === 0) {
    header("Location: student_dashboard.php");
    exit();
}
include 'php/db.php';
$userId = (int)$_SESSION['user_id'];
$userName = htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['email']);
$userInitial = strtoupper(substr($userName, 0, 1));

// Fetch published templates created by this professor from MySQL
$templatesQuery = mysqli_query($conn, "
    SELECT template_id, course_code, course_title, class_code 
    FROM grade_templates 
    WHERE user_id = '$userId'
    ORDER BY created_at DESC
");
$publishedTemplates = [];
while ($row = mysqli_fetch_assoc($templatesQuery)) {
    $tId = (int)$row['template_id'];
    $compQuery = mysqli_query($conn, "SELECT component_name, weight FROM template_components WHERE template_id = '$tId'");
    $comps = [];
    while ($c = mysqli_fetch_assoc($compQuery)) {
        $comps[] = ['name' => $c['component_name'], 'weight' => (int)$c['weight']];
    }
    $publishedTemplates[] = [
        'id' => $tId,
        'code' => $row['course_code'],
        'title' => $row['course_title'],
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
    <title>Faculty Portal - Course Templates</title>
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
                <div class="w-full text-left px-4 py-3 rounded-xl font-bold text-xs flex items-center space-x-3 bg-maroon-100 text-maroon-900 border border-maroon-200">
                    <i class="fa-solid fa-sliders text-sm"></i>
                    <span>Course Templates</span>
                </div>
            </div>

            <div class="pt-4 border-t border-stone-300/80 text-[11px] text-stone-500 font-medium">
                <p>College of Computer Studies</p>
                <p class="text-[10px] text-stone-400">PUP Biñan Campus • Professor</p>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="flex-1 p-6 sm:p-8 space-y-6 max-w-7xl">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-stone-300/60">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Professor Syllabus Templates</h2>
                    <p class="text-xs font-semibold text-stone-500 mt-0.5">Design course evaluation components and generate enrollment sync codes for students.</p>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-xs sm:text-sm font-semibold text-stone-500">Oct 2, 2026</span>
                </div>
            </div>

            <!-- Templates Section -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Syllabus Builder Form -->
                <div class="lg:col-span-7 bg-white p-6 rounded-3xl border border-stone-200 shadow-sm space-y-5">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <h3 class="font-bold text-slate-800 text-base flex items-center space-x-2">
                            <i class="fa-solid fa-sliders text-maroon-800"></i>
                            <span>Create Course Template</span>
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
                            <label class="block font-bold uppercase text-stone-600">Grading System Components & Percentage Weights</label>

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
                                <span>Add Assessment Criterion</span>
                            </button>
                        </div>

                        <div class="pt-4 border-t border-stone-200">
                            <button type="submit" name="btnPublishTemplate" class="w-full py-3 bg-maroon-900 hover:bg-maroon-800 text-white font-bold text-xs rounded-xl shadow-md transition text-center">
                                Publish Syllabus & Generate Class Code
                            </button>
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

        </main>
    </div>

    <script>
        let enrolledTemplates = <?php echo json_encode($publishedTemplates); ?>;

        document.addEventListener("DOMContentLoaded", () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('template') === 'success') {
                const code = urlParams.get('code') ? `\nClass Sync Code: ${urlParams.get('code')}` : '';
                alert(`Syllabus template published and saved to database!${code}\nShare this code with your students.`);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            renderTemplatesList();
            calculateTotalWeight();
        });

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

                let componentsHtml = tpl.components.map(c => `
                    <div class="flex justify-between text-stone-600">
                        <span>${c.name}:</span>
                        <span class="font-bold text-slate-800">${c.weight}%</span>
                    </div>
                `).join('');

                card.innerHTML = `
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">PUBLISHED SYLLABUS</span>
                            <h4 class="font-extrabold text-slate-900 text-base mt-1">${tpl.code}</h4>
                            <p class="text-xs text-stone-500 font-medium">${tpl.title}</p>
                        </div>
                        <div class="text-right flex items-center space-x-3">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-stone-400 block">Class Sync Code</span>
                                <span class="font-mono font-black text-maroon-900 text-sm bg-stone-100 px-2.5 py-1 rounded-lg border border-stone-200 inline-block mt-0.5">${tpl.classCode}</span>
                            </div>
                            <button onclick="removeTemplate(${tpl.id})" class="text-stone-400 hover:text-rose-600 transition text-sm">
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

        function addComponentRow() {
            const container = document.getElementById('componentContainer');
            const row = document.createElement('div');
            row.className = 'flex items-center space-x-2 component-row';
            row.innerHTML = `
                <input type="text" name="component_name[]" placeholder="Assessment Name" required class="comp-name flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
                <input type="number" name="weight[]" value="10" min="1" max="100" onchange="calculateTotalWeight()" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
                <span class="font-bold text-stone-400">%</span>
                <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2"><i class="fa-solid fa-trash-can"></i></button>
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
            if (confirm("Remove this published syllabus from database?")) {
                window.location.href = `php/delete_template.php?id=${id}`;
            }
        }

        function logout() { window.location.href = 'php/logout.php'; }
    </script>
</body>
</html>