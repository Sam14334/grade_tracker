/**
 * Faculty Portal - Professor Dashboard Script
 * Handles Tab Switching, Class Standing Filters/Search, and Syllabus Template Builder
 */

// Global state variables
let enrolledTemplates = window.publishedTemplatesData || [];
let nextComponentIndex = document.querySelectorAll('#componentContainer .component-row').length;

// Mock Student Data for Interactive Class Standing Filtering & Searching
const studentsData = [
    { rank: '1st', initials: 'JD', name: 'Jane Doe', id: '2024-8841', course: 'CS-301', grade: 96.6, velocity: 3.4, accel: true, q: 98, m: 96, p: 95, f: 97, status: 'Outstanding' },
    { rank: '2nd', initials: 'MS', name: 'Marcus Smith', id: '2024-8842', course: 'BIO-105', grade: 92.4, velocity: 2.8, accel: true, q: 94, m: 92, p: 90, f: 93, status: 'Outstanding' },
    { rank: '3rd', initials: 'EL', name: 'Emily Lin', id: '2024-8843', course: 'ENG-210', grade: 89.5, velocity: 1.5, accel: true, q: 89, m: 88, p: 91, f: 90, status: 'Good Standing' },
    { rank: '#4', initials: 'CR', name: 'Carlos Rodriguez', id: '2024-8844', course: 'CS-301', grade: 83.8, velocity: 4.2, accel: true, q: 85, m: 82, p: 84, f: 84, status: 'Good Standing' },
    { rank: '#5', initials: 'SV', name: 'Samantha Vance', id: '2024-8845', course: 'BIO-105', grade: 80.5, velocity: 1.8, accel: true, q: 82, m: 79, p: 80, f: 81, status: 'Good Standing' },
    { rank: '#6', initials: 'DK', name: 'David Kim', id: '2024-8846', course: 'ENG-210', grade: 71.7, velocity: -1.2, accel: false, q: 74, m: 71, p: 75, f: 68, status: 'At Risk' },
    { rank: '#7', initials: 'AP', name: 'Arthur Pendelton', id: '2024-8847', course: 'CS-301', grade: 61.5, velocity: 3.1, accel: true, q: 62, m: 65, p: 60, f: 59, status: 'At Risk' }
];

let currentCourseFilter = 'ALL';
let currentStatusFilter = 'ALL';
let searchQuery = '';

document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('template') === 'success') {
        const code = urlParams.get('code') ? `\nClass Sync Code: ${urlParams.get('code')}` : '';
        alert(`Syllabus template published and saved to database!${code}\nShare this code with your students.`);
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Default active tab & views
    switchTab('templates');
    renderTemplatesList();
    renderLeaderboard();
    calculateTotalWeight();
});

// Sidebar Tab Switcher (Exposed globally)
window.switchTab = function(tabId) {
    ['overview', 'standing', 'templates'].forEach(id => {
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
}

// Class Standing Filtering & Searching
window.filterLeaderboardByCourse = function(courseCode) {
    currentCourseFilter = courseCode;
    const desc = document.getElementById('filterDescription');
    if (desc) {
        if (courseCode === 'ALL') {
            desc.innerHTML = `Filtered View: <strong>Currently showing aggregated standings across all active classes</strong>`;
        } else {
            desc.innerHTML = `Filtered View: <strong>Showing class standings for ${courseCode}</strong>`;
        }
    }
    renderLeaderboard();
}

window.filterLeaderboardStatus = function(statusType) {
    currentStatusFilter = statusType;
    ['ALL', 'ACCEL', 'TOP', 'RISK'].forEach(s => {
        const b = document.getElementById(`btn-status-${s.toLowerCase()}`);
        if (b) {
            b.className = "px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl transition cursor-pointer";
        }
    });
    const activeBtn = document.getElementById(`btn-status-${statusType.toLowerCase()}`);
    if (activeBtn) {
        activeBtn.className = "px-3 py-1.5 bg-maroon-900 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer";
    }
    renderLeaderboard();
}

window.searchStudents = function(query) {
    searchQuery = query.toLowerCase().trim();
    renderLeaderboard();
}

function renderLeaderboard() {
    const tbody = document.getElementById('leaderboardTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    let filtered = studentsData.filter(st => {
        let matchesCourse = (currentCourseFilter === 'ALL' || st.course === currentCourseFilter);
        let matchesSearch = (st.name.toLowerCase().includes(searchQuery) || st.id.includes(searchQuery) || st.course.toLowerCase().includes(searchQuery));
        let matchesStatus = true;
        if (currentStatusFilter === 'ACCEL') matchesStatus = st.accel;
        else if (currentStatusFilter === 'TOP') matchesStatus = st.grade >= 90;
        else if (currentStatusFilter === 'RISK') matchesStatus = st.status === 'At Risk';
        return matchesCourse && matchesSearch && matchesStatus;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="11" class="p-6 text-center text-stone-400 italic">No students found matching your filters.</td></tr>`;
        return;
    }

    filtered.forEach(st => {
        const tr = document.createElement('tr');
        let badgeClass = "px-2 py-0.5 rounded font-bold text-[10px] ";
        if (st.status === 'Outstanding') badgeClass += "bg-emerald-50 text-emerald-700 border border-emerald-200";
        else if (st.status === 'Good Standing') badgeClass += "bg-blue-50 text-blue-700 border border-blue-200";
        else badgeClass += "bg-rose-50 text-rose-700 border border-rose-200";

        let rankBadgeClass = "px-2 py-1 rounded font-black text-[11px] ";
        if (st.rank === '1st') rankBadgeClass += "bg-amber-100 text-amber-900";
        else if (st.rank === '2nd') rankBadgeClass += "bg-stone-200 text-stone-800";
        else if (st.rank === '3rd') rankBadgeClass += "bg-orange-100 text-orange-900";
        else rankBadgeClass += "text-stone-500 font-bold";

        tr.innerHTML = `
            <td class="p-3"><span class="${rankBadgeClass}">${st.rank}</span></td>
            <td class="p-3 flex items-center space-x-2">
                <div class="w-7 h-7 rounded-full bg-stone-200 flex items-center justify-center font-bold text-[10px] text-stone-700">${st.initials}</div>
                <div><p class="font-bold text-slate-900">${st.name}</p><p class="text-[10px] text-stone-400">${st.id}</p></div>
            </td>
            <td class="p-3 font-semibold text-maroon-900">${st.course}</td>
            <td class="p-3 font-black text-slate-900">${st.grade.toFixed(1)}%</td>
            <td class="p-3 ${st.velocity >= 0 ? 'text-emerald-700' : 'text-rose-700'} font-bold">${st.velocity >= 0 ? '+' : ''}${st.velocity}%</td>
            <td class="p-3 text-center">${st.q}%</td>
            <td class="p-3 text-center">${st.m}%</td>
            <td class="p-3 text-center">${st.p}%</td>
            <td class="p-3 text-center">${st.f}%</td>
            <td class="p-3"><span class="${badgeClass}">${st.status}</span></td>
            <td class="p-3 text-right"><button onclick="alert('Viewing detailed breakdown for ${st.name}')" class="px-3 py-1 bg-stone-100 hover:bg-stone-200 rounded-lg text-xs font-bold transition cursor-pointer">View Breakdown</button></td>
        `;
        tbody.appendChild(tr);
    });
}

// Template Builder Functions
function renderTemplatesList() {
    const container = document.getElementById('publishedTemplatesList');
    if (!container) return;
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
                    <button onclick="removeTemplate(${tpl.id})" class="text-stone-400 hover:text-rose-600 transition text-sm cursor-pointer">
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

window.addComponentRow = function() {
    const container = document.getElementById('componentContainer');
    const row = document.createElement('div');
    const index = nextComponentIndex++;
    row.className = 'flex flex-wrap items-center gap-2 component-row';
    row.innerHTML = `
        <input type="text" name="component_name[${index}]" placeholder="Assessment Name" required class="comp-name min-w-0 w-full sm:w-auto sm:flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
        <label class="flex items-center gap-1.5 font-semibold text-stone-600 cursor-pointer">
            <input type="checkbox" name="is_exam[${index}]" value="1" class="w-4 h-4 rounded border-stone-300 accent-maroon-900"> Exam
        </label>
        <input type="number" name="weight[${index}]" value="10" min="1" max="100" onchange="calculateTotalWeight()" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
        <span class="font-bold text-stone-400">%</span>
        <button type="button" onclick="removeRow(this)" class="text-rose-500 hover:text-rose-700 p-2 cursor-pointer"><i class="fa-solid fa-trash-can"></i></button>
    `;
    container.appendChild(row);
    calculateTotalWeight();
}

window.removeRow = function(btn) {
    btn.closest('.component-row').remove();
    calculateTotalWeight();
}

window.calculateTotalWeight = function() {
    const weights = document.querySelectorAll('.comp-weight');
    let total = 0;
    weights.forEach(w => total += parseFloat(w.value) || 0);

    const badge = document.getElementById('weightTotalBadge');
    if (badge) {
        badge.innerText = `Total Weight: ${total}%`;
        badge.className = total === 100
            ? "text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200"
            : "text-xs font-bold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200";
    }

    return total;
}

window.validatePublishForm = function(e) {
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

window.removeTemplate = function(id) {
    if (confirm("Remove this published syllabus from database?")) {
        window.location.href = `php/delete_template.php?id=${id}`;
    }
}

window.logout = function() { 
    window.location.href = 'php/logout.php'; 
}