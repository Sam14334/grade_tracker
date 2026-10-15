google.charts.load('current', { 'packages': ['corechart'] });
google.charts.setOnLoadCallback(() => renderStudentTrendChart());

// Load saved target GWA goal or default to 1.45
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

let scheduleMatrix = [
    { id: 1, time: '10:00 AM', mon: 'CS-301', tue: '', wed: 'CS-301', thu: '', fri: '', sat: 'IT-ELECT' },
    { id: 2, time: '11:00 AM', mon: '', tue: 'BIO-105', wed: '', thu: 'BIO-105', fri: '', sat: 'IT-ELECT' }
];

let enrolledTemplates = [
    {
        id: 1,
        code: 'CS-301',
        title: 'Software Engineering',
        classCode: 'CS301-SEC1',
        components: [
            { name: 'Quizzes & Seatwork', weight: 30 },
            { name: 'Midterm Examination', weight: 30 },
            { name: 'Final Project & Exam', weight: 40 }
        ]
    }
];

document.addEventListener("DOMContentLoaded", () => {
    const selectElement = document.getElementById('thresholdSettingInput');
    if (selectElement) {
        selectElement.value = targetGwaGoal.toFixed(2);
    }

    renderGradesTable();
    renderExamsList();
    renderScheduleTimetable();
    renderTemplatesList();
    recalculateGradesAndStats(false);

    // Sidebar Nav Event Listeners
    document.getElementById('nav-dashboard').addEventListener('click', () => switchTab('dashboard'));
    document.getElementById('nav-grades').addEventListener('click', () => switchTab('grades'));
    document.getElementById('nav-templates').addEventListener('click', () => switchTab('templates'));
    document.getElementById('nav-schedule').addEventListener('click', () => switchTab('schedule'));
    document.getElementById('nav-settings').addEventListener('click', () => switchTab('settings'));

    // Dashboard quick actions
    document.getElementById('addExamBtn').addEventListener('click', addExamPrompt);
    document.getElementById('goToScheduleBtn').addEventListener('click', () => switchTab('schedule'));

    // Grades table actions
    document.getElementById('updateStatsBtn').addEventListener('click', () => recalculateGradesAndStats(true));
    document.getElementById('addCourseRowBtn').addEventListener('click', addSubjectRow);

    // Templates actions
    document.getElementById('enrollSyncBtn').addEventListener('click', enrollTemplateCode);
    document.getElementById('addComponentBtn').addEventListener('click', addComponentRow);
    document.getElementById('publishTemplateBtn').addEventListener('click', publishTemplate);

    // Schedule actions
    document.getElementById('addScheduleSlotBtn').addEventListener('click', addScheduleSlotPrompt);

    // Settings actions
    document.getElementById('saveSettingsBtn').addEventListener('click', saveSettings);

    // Component weight live calculation event delegation
    document.getElementById('componentContainer').addEventListener('input', (e) => {
        if (e.target.classList.contains('comp-weight')) {
            calculateTotalWeight();
        }
    });

    document.getElementById('componentContainer').addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.remove-row-btn');
        if (deleteBtn) {
            deleteBtn.closest('.component-row').remove();
            calculateTotalWeight();
        }
    });
});

function switchTab(tabId) {
    ['dashboard', 'grades', 'templates', 'schedule', 'settings'].forEach(id => {
        const el = document.getElementById(`tab-content-${id}`);
        if (el) el.classList.add('hidden');

        const btn = document.getElementById(`nav-${id}`);
        if (btn) {
            btn.className = "w-full text-left px-4 py-3 rounded-xl font-medium text-xs text-stone-700 hover:bg-stone-200/60 transition flex items-center space-x-3 cursor-pointer";
        }
    });

    document.getElementById(`tab-content-${tabId}`).classList.remove('hidden');
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

    // Exam Predictor Calculation
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

function saveSettings() {
    const selectElement = document.getElementById('thresholdSettingInput');
    if (!selectElement) return;

    const selectedVal = parseFloat(selectElement.value) || 1.45;

    targetGwaGoal = selectedVal;
    localStorage.setItem('targetGwaGoal', selectedVal.toString());

    recalculateGradesAndStats(false);

    const statusMsg = document.getElementById('saveStatusMsg');
    if (statusMsg) {
        statusMsg.classList.remove('hidden');
        setTimeout(() => {
            statusMsg.classList.add('hidden');
        }, 3000);
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

function renderScheduleTimetable() {
    const dashTable = document.getElementById('dashboardScheduleMatrix');
    const fullTable = document.getElementById('fullScheduleTableBody');

    dashTable.innerHTML = '';
    fullTable.innerHTML = '';

    scheduleMatrix.forEach(row => {
        const dashTr = document.createElement('tr');
        dashTr.innerHTML = `
            <td class="p-2 border-r border-stone-200 bg-stone-50 font-semibold text-[11px]">${row.time}</td>
            <td class="p-2 border-r border-stone-200 ${getSubjectBadgeStyle(row.mon)}">${row.mon}</td>
            <td class="p-2 border-r border-stone-200 ${getSubjectBadgeStyle(row.tue)}">${row.tue}</td>
            <td class="p-2 border-r border-stone-200 ${getSubjectBadgeStyle(row.wed)}">${row.wed}</td>
            <td class="p-2 border-r border-stone-200 ${getSubjectBadgeStyle(row.thu)}">${row.thu}</td>
            <td class="p-2 border-r border-stone-200 ${getSubjectBadgeStyle(row.fri)}">${row.fri}</td>
            <td class="p-2 ${getSubjectBadgeStyle(row.sat)}">${row.sat}</td>
        `;
        dashTable.appendChild(dashTr);

        const fullTr = document.createElement('tr');
        fullTr.innerHTML = `
            <td class="p-3 border-r border-stone-200 bg-stone-50 font-bold text-[11px]">${row.time}</td>
            <td class="p-3 border-r border-stone-200 ${getSubjectBadgeStyle(row.mon)}">${row.mon}</td>
            <td class="p-3 border-r border-stone-200 ${getSubjectBadgeStyle(row.tue)}">${row.tue}</td>
            <td class="p-3 border-r border-stone-200 ${getSubjectBadgeStyle(row.wed)}">${row.wed}</td>
            <td class="p-3 border-r border-stone-200 ${getSubjectBadgeStyle(row.thu)}">${row.thu}</td>
            <td class="p-3 border-r border-stone-200 ${getSubjectBadgeStyle(row.fri)}">${row.fri}</td>
            <td class="p-3 border-r border-stone-200 ${getSubjectBadgeStyle(row.sat)}">${row.sat}</td>
            <td class="p-3 text-center">
                <button type="button" onclick="removeScheduleSlot(${row.id})" class="text-rose-500 hover:text-rose-700 transition cursor-pointer">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        fullTable.appendChild(fullTr);
    });
}

function getSubjectBadgeStyle(subjectCode) {
    if (!subjectCode) return '';
    if (subjectCode.includes('CS')) return 'bg-maroon-50 text-maroon-900 font-bold text-[10px]';
    if (subjectCode.includes('BIO')) return 'bg-amber-50 text-amber-900 font-bold text-[10px]';
    if (subjectCode.includes('IT')) return 'bg-purple-50 text-purple-900 font-bold text-[10px]';
    return 'bg-blue-50 text-blue-900 font-bold text-[10px]';
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

window.addSubjectRow = function() {
    const subjCode = prompt("Enter Course Code:", "PHYS-101");
    if (!subjCode) return;
    const title = prompt("Enter Course Title:", "General Physics") || "Core Subject";
    const units = parseFloat(prompt("Enter Unit Value:", "3.0")) || 3.0;

    studentGradesData.push({ id: Date.now(), subject: subjCode.toUpperCase(), title: title, grade: 1.50, target: 1.00, units: units });
    recalculateGradesAndStats(false);
}

window.removeSubjectRow = function(id) {
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

window.removeExam = function(id) {
    upcomingExams = upcomingExams.filter(e => e.id !== id);
    renderExamsList();
}

function addScheduleSlotPrompt() {
    const time = prompt("Enter Time Slot (e.g. 01:00 PM):", "01:00 PM");
    if (!time) return;

    const mon = prompt("Monday Course Code:", "") || "";
    const tue = prompt("Tuesday Course Code:", "") || "";
    const wed = prompt("Wednesday Course Code:", "") || "";
    const thu = prompt("Thursday Course Code:", "") || "";
    const fri = prompt("Friday Course Code:", "") || "";
    const sat = prompt("Saturday Course Code:", "") || "";

    scheduleMatrix.push({ id: Date.now(), time, mon, tue, wed, thu, fri, sat });
    renderScheduleTimetable();
}

window.removeScheduleSlot = function(id) {
    if (confirm("Remove this time slot from your schedule?")) {
        scheduleMatrix = scheduleMatrix.filter(s => s.id !== id);
        renderScheduleTimetable();
    }
}

function addComponentRow() {
    const container = document.getElementById('componentContainer');
    const row = document.createElement('div');
    row.className = 'flex items-center space-x-2 component-row';
    row.innerHTML = `
        <input type="text" placeholder="Assessment Name" class="comp-name flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
        <input type="number" value="10" class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
        <span class="font-bold text-stone-400">%</span>
        <button type="button" class="remove-row-btn text-rose-500 hover:text-rose-700 p-2 cursor-pointer"><i class="fa-solid fa-trash-can"></i></button>
    `;
    container.appendChild(row);
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

function publishTemplate() {
    const total = calculateTotalWeight();
    if (total !== 100) return alert(`Total weight must equal 100%. Current total is ${total}%.`);

    const code = document.getElementById('courseCodeInput').value.trim();
    const title = document.getElementById('courseTitleInput').value.trim();
    if (!code || !title) return alert('Please enter Course Code and Title.');

    const compNames = document.querySelectorAll('.comp-name');
    const compWeights = document.querySelectorAll('.comp-weight');
    let components = [];

    compNames.forEach((n, i) => {
        components.push({ name: n.value || 'Component', weight: parseFloat(compWeights[i].value) || 0 });
    });

    const generatedCode = `${code.replace(/[^a-zA-Z0-9]/g, '')}-SEC${Math.floor(1 + Math.random() * 9)}`;

    enrolledTemplates.push({ id: Date.now(), code, title, classCode: generatedCode, components });
    renderTemplatesList();
    alert(`Template successfully published!\nShared Class Code: ${generatedCode}`);
}

function enrollTemplateCode() {
    const code = document.getElementById('templateCodeInput').value.trim();
    if (!code) return alert('Please enter a Class Code.');

    enrolledTemplates.push({
        id: Date.now(),
        code: code.split('-')[0] || 'SYNC-101',
        title: 'Enrolled Instructor Course',
        classCode: code.toUpperCase(),
        components: [
            { name: 'Quizzes & Assignments', weight: 40 },
            { name: 'Midterm & Final Examination', weight: 60 }
        ]
    });

    document.getElementById('templateCodeInput').value = '';
    renderTemplatesList();
    alert(`Enrolled in Class Code ${code}! Course grading structure inherited.`);
}

window.removeTemplate = function(id) {
    if (confirm("Remove this enrolled template?")) {
        enrolledTemplates = enrolledTemplates.filter(t => t.id !== id);
        renderTemplatesList();
    }
}