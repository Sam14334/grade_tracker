google.charts.load('current', { 'packages': ['corechart'] });
google.charts.setOnLoadCallback(() => renderStudentTrendChart());

// Load saved target GWA goal or default to 1.45
let targetGwaGoal = parseFloat(localStorage.getItem('targetGwaGoal')) || 1.45;

let studentGradesData = (window.studentDashboardData?.enrolledCourses ?? []).map(course => ({
    id: course.templateId,
    enrollmentId: course.enrollmentId,
    subject: course.code,
    title: course.title,
    grade: null,
    target: 1.00,
    units: course.units
}));

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
    renderTemplatesList();
    recalculateGradesAndStats(false);

    if (['#templates', '#grades'].includes(window.location.hash)) {
        switchTab(window.location.hash.slice(1));
    }

    // The PHP dashboard uses inline handlers and submits enrollment to PHP.
    if (window.studentDashboardData) return;

    // Sidebar Nav Event Listeners
    document.getElementById('nav-dashboard').addEventListener('click', () => switchTab('dashboard'));
    document.getElementById('nav-grades').addEventListener('click', () => switchTab('grades'));
    document.getElementById('nav-templates').addEventListener('click', () => switchTab('templates'));
    document.getElementById('nav-settings').addEventListener('click', () => switchTab('settings'));


    // Grades table actions
    document.getElementById('updateStatsBtn').addEventListener('click', () => recalculateGradesAndStats(true));
    document.getElementById('addCourseRowBtn').addEventListener('click', addSubjectRow);

    // Templates actions
    document.getElementById('enrollSyncBtn').addEventListener('click', enrollTemplateCode);
    document.getElementById('addComponentBtn').addEventListener('click', addComponentRow);
    document.getElementById('publishTemplateBtn').addEventListener('click', publishTemplate);


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
    ['dashboard', 'grades', 'templates', 'settings'].forEach(id => {
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
    let gradedUnits = 0;
    let hasGradeBelow250 = false;

    for (const input of document.querySelectorAll('.grade-input, .target-grade-input')) {
        if (!input.checkValidity()) {
            input.reportValidity();
            return;
        }
    }

    inputs.forEach(input => {
        const item = studentGradesData.find(g => g.id === Number(input.getAttribute('data-id')));
        if (item) item.grade = input.value === '' ? null : Number(input.value);
    });

    document.querySelectorAll('.target-grade-input').forEach(input => {
        const item = studentGradesData.find(g => g.id === Number(input.getAttribute('data-id')));
        if (item) item.target = Number(input.value);
    });

    studentGradesData.forEach(item => {
        totalUnits += item.units;
        if (item.grade === null) return;
        if (item.grade > 2.50) hasGradeBelow250 = true;
        totalWeightedGrades += item.grade * item.units;
        gradedUnits += item.units;
    });

    const calculatedGWA = gradedUnits > 0 ? (totalWeightedGrades / gradedUnits).toFixed(2) : '--';

    document.getElementById('calcGwaVal').innerText = calculatedGWA;
    document.getElementById('totalUnitsDisplay').innerText = totalUnits.toFixed(1);

    document.querySelectorAll('.targetGwaSpan').forEach(span => {
        span.innerText = targetGwaGoal.toFixed(2);
    });

    const diagBox = document.getElementById('ruleDiagnosticBox');
    const diagText = document.getElementById('ruleDiagnosticText');
    const badge = document.getElementById('bannerTargetBadge');

    badge.innerText = `Target Goal: GWA ≤ ${targetGwaGoal.toFixed(2)}`;

    if (gradedUnits === 0 || gradedUnits < totalUnits) {
        diagBox.className = "bg-stone-50 border-l-4 border-stone-400 p-4 rounded-2xl flex items-start space-x-3 shadow-sm";
        diagText.textContent = gradedUnits === 0
            ? 'Enter grades for your enrolled courses to calculate your GWA.'
            : `Current Estimated GWA: ${calculatedGWA}. Enter the remaining grades to check your target qualification.`;
    } else if (parseFloat(calculatedGWA) <= targetGwaGoal && !hasGradeBelow250) {
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

function escapeGradeText(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
}

function renderGradesTable() {
    const tbody = document.getElementById('gradeInputTableBody');
    tbody.innerHTML = '';

    if (studentGradesData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-3 text-stone-500">No courses yet. Enroll using your professor\'s class code in Professor Templates.</td></tr>';
        return;
    }

    studentGradesData.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="p-3 font-bold text-maroon-900">${escapeGradeText(item.subject)}</td>
            <td class="p-3 font-medium">${escapeGradeText(item.title)}</td>
            <td class="p-3 font-bold">${item.units.toFixed(1)}</td>
            <td class="p-3">
                <input type="number" step="0.25" min="1.00" max="5.00" value="${item.grade === null ? '' : item.grade.toFixed(2)}" placeholder="Enter grade"
                       class="grade-input w-28 px-3 py-1.5 rounded-lg border border-stone-300 font-bold text-xs focus:border-maroon-800 outline-none"
                       data-id="${item.id}" data-units="${item.units}">
            </td>
            <td class="p-3">
                <input type="number" step="0.25" min="1.00" max="5.00" value="${item.target.toFixed(2)}" required
                       class="target-grade-input w-28 px-3 py-1.5 rounded-lg border border-stone-300 font-bold text-xs focus:border-maroon-800 outline-none"
                       data-id="${item.id}">
            </td>
            <td class="p-3">
                <span class="${item.grade === null ? 'text-stone-500' : item.grade <= 2.50 ? 'text-emerald-700' : 'text-rose-700'} font-bold text-[11px]">
                    <i class="fa-solid ${item.grade === null ? 'fa-minus' : item.grade <= 2.50 ? 'fa-check-circle' : 'fa-triangle-exclamation'} mr-1"></i>
                    ${item.grade === null ? 'Not entered' : item.grade <= 2.50 ? 'PL Valid (≤ 2.50)' : 'PL Ineligible (> 2.50)'}
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

function renderTemplatesList() {
    if (window.studentDashboardData) return;

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
    const course = studentGradesData.find(item => item.id === id);
    if (!course) return;

    if (course.enrollmentId > 0) {
        if (!confirm("Unenroll from this course? It will also be removed from your enrolled professor templates.")) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'php/unenroll_course.php';
        const fields = { btnUnenrollCourse: '1', template_id: id };
        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        return;
    }

    if (!confirm("Remove this manually added course from your tracker?")) return;
    studentGradesData = studentGradesData.filter(item => item.id !== id);
    recalculateGradesAndStats(false);
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
