// Mock Student Database for Class Standing
const studentsData = [
    { rank: '1st', name: 'Jane Doe', id: '2024-8841', initials: 'JD', course: 'CS-301', grade: 96.6, accel: '+3.4%', accelVal: 3.4, quizzes: '98%', midterm: '96%', project: '95%', final: '97%', status: 'Outstanding' },
    { rank: '2nd', name: 'Marcus Smith', id: '2024-8842', initials: 'MS', course: 'BIO-105', grade: 92.4, accel: '+2.8%', accelVal: 2.8, quizzes: '94%', midterm: '92%', project: '90%', final: '93%', status: 'Outstanding' },
    { rank: '3rd', name: 'Emily Lin', id: '2024-8843', initials: 'EL', course: 'ENG-210', grade: 89.5, accel: '+1.5%', accelVal: 1.5, quizzes: '89%', midterm: '88%', project: '91%', final: '90%', status: 'Good Standing' },
    { rank: '4th', name: 'Carlos Rodriguez', id: '2024-8844', initials: 'CR', course: 'CS-301', grade: 83.8, accel: '+4.2%', accelVal: 4.2, quizzes: '85%', midterm: '82%', project: '84%', final: '84%', status: 'Good Standing' },
    { rank: '5th', name: 'Samantha Vance', id: '2024-8845', initials: 'SV', course: 'BIO-105', grade: 80.5, accel: '+1.8%', accelVal: 1.8, quizzes: '82%', midterm: '79%', project: '80%', final: '81%', status: 'Good Standing' },
    { rank: '6th', name: 'David Kim', id: '2024-8846', initials: 'DK', course: 'ENG-210', grade: 71.7, accel: '-1.2%', accelVal: -1.2, quizzes: '74%', midterm: '71%', project: '75%', final: '68%', status: 'At Risk' },
    { rank: '7th', name: 'Arthur Pendelton', id: '2024-8847', initials: 'AP', course: 'CS-301', grade: 61.5, accel: '+3.1%', accelVal: 3.1, quizzes: '62%', midterm: '65%', project: '60%', final: '59%', status: 'At Risk' }
];

let currentCourseFilter = 'ALL';
let currentCategoryFilter = 'ALL';

document.addEventListener("DOMContentLoaded", () => {
    switchTab('standing');
    updateMetrics();
    renderTable();
});

function switchTab(tabId) {
    ['overview', 'standing'].forEach(id => {
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

function filterByCourse() {
    currentCourseFilter = document.getElementById('courseFilterSelect').value;
    const viewText = document.getElementById('filteredViewText');

    if (currentCourseFilter === 'ALL') {
        viewText.innerHTML = 'Filtered View: <strong>Currently showing aggregated standings across all active classes</strong>';
    } else {
        viewText.innerHTML = `Filtered View: <strong>Showing standings for ${currentCourseFilter}</strong>`;
    }

    updateMetrics();
    renderTable();
}

function setCategoryFilter(category, btnElement) {
    currentCategoryFilter = category;
    
    document.querySelectorAll('.cat-btn').forEach(btn => {
        btn.className = "cat-btn px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl transition cursor-pointer";
    });
    btnElement.className = "cat-btn px-3 py-1.5 bg-maroon-900 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer transition";

    renderTable();
}

function getFilteredStudents() {
    const searchQuery = document.getElementById('searchInput').value.toLowerCase();

    return studentsData.filter(s => {
        const matchCourse = (currentCourseFilter === 'ALL' || s.course === currentCourseFilter);
        const matchSearch = s.name.toLowerCase().includes(searchQuery) || s.id.toLowerCase().includes(searchQuery);

        let matchCategory = true;
        if (currentCategoryFilter === 'ACCELERATING') matchCategory = s.accelVal > 0;
        else if (currentCategoryFilter === 'TOP') matchCategory = s.grade >= 90;
        else if (currentCategoryFilter === 'RISK') matchCategory = s.grade < 75;

        return matchCourse && matchSearch && matchCategory;
    });
}

function updateMetrics() {
    const relevantStudents = currentCourseFilter === 'ALL' 
        ? studentsData 
        : studentsData.filter(s => s.course === currentCourseFilter);

    const totalEnrolled = relevantStudents.length;
    const atRiskCount = relevantStudents.filter(s => s.grade < 75).length;
    
    let classAvg = 0;
    if (totalEnrolled > 0) {
        const sum = relevantStudents.reduce((acc, curr) => acc + curr.grade, 0);
        classAvg = (sum / totalEnrolled).toFixed(1);
    }

    const topStudent = relevantStudents.length > 0 
        ? [...relevantStudents].sort((a, b) => b.grade - a.grade)[0] 
        : null;

    document.getElementById('metricTotalEnrolled').innerText = totalEnrolled;
    document.getElementById('metricAtRiskCount').innerText = `${atRiskCount} Student${atRiskCount === 1 ? '' : 's'}`;
    document.getElementById('metricClassAvg').innerText = `${classAvg}%`;

    if (topStudent) {
        document.getElementById('metricTopGrade').innerText = `${topStudent.grade}%`;
        document.getElementById('metricTopStudent').innerHTML = `${topStudent.name} <span class="text-[10px] text-stone-400">(${topStudent.course})</span>`;
    } else {
        document.getElementById('metricTopGrade').innerText = '0.0%';
        document.getElementById('metricTopStudent').innerText = 'N/A';
    }

    if (currentCourseFilter === 'ALL') {
        document.getElementById('metricCourseCount').innerText = 'Across 3 Courses';
    } else {
        document.getElementById('metricCourseCount').innerText = `Enrolled in ${currentCourseFilter}`;
    }
}

function renderTable() {
    const tbody = document.getElementById('leaderboardTableBody');
    const filtered = getFilteredStudents();
    tbody.innerHTML = '';

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="11" class="p-6 text-center text-stone-400 italic">No matching student records found.</td></tr>`;
        return;
    }

    filtered.forEach((s, index) => {
        const tr = document.createElement('tr');
        
        let rankBadge = `<span class="text-stone-500 font-bold">#${index + 1}</span>`;
        if (index === 0) rankBadge = `<span class="px-2 py-1 bg-amber-100 text-amber-900 rounded font-black text-[11px]">1st</span>`;
        else if (index === 1) rankBadge = `<span class="px-2 py-1 bg-stone-200 text-stone-800 rounded font-black text-[11px]">2nd</span>`;
        else if (index === 2) rankBadge = `<span class="px-2 py-1 bg-orange-100 text-orange-900 rounded font-black text-[11px]">3rd</span>`;

        let statusBadge = '';
        if (s.status === 'Outstanding') {
            statusBadge = `<span class="inline-flex items-center space-x-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200/80 rounded-full font-bold text-[10px] shadow-2xs"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span><span>Outstanding</span></span>`;
        } else if (s.status === 'Good Standing') {
            statusBadge = `<span class="inline-flex items-center space-x-1.5 px-2.5 py-1 bg-sky-50 text-sky-800 border border-sky-200/80 rounded-full font-bold text-[10px] shadow-2xs"><span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span><span>Good Standing</span></span>`;
        } else {
            statusBadge = `<span class="inline-flex items-center space-x-1.5 px-2.5 py-1 bg-rose-50 text-rose-800 border border-rose-200/80 rounded-full font-bold text-[10px] shadow-2xs"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span><span>At Risk</span></span>`;
        }

        const accelColor = s.accelVal >= 0 ? 'text-emerald-700 font-bold' : 'text-rose-700 font-bold';

        tr.innerHTML = `
            <td class="p-3">${rankBadge}</td>
            <td class="p-3 flex items-center space-x-2">
                <div class="w-7 h-7 rounded-full bg-stone-200 flex items-center justify-center font-bold text-[10px] text-stone-700 shadow-2xs">${s.initials}</div>
                <div><p class="font-bold text-slate-900">${s.name}</p><p class="text-[10px] text-stone-400">${s.id}</p></div>
            </td>
            <td class="p-3 font-semibold text-maroon-900">${s.course}</td>
            <td class="p-3 font-black text-slate-900">${s.grade}%</td>
            <td class="p-3 ${accelColor}">${s.accel}</td>
            <td class="p-3 text-center">${s.quizzes}</td>
            <td class="p-3 text-center">${s.midterm}</td>
            <td class="p-3 text-center">${s.project}</td>
            <td class="p-3 text-center">${s.final}</td>
            <td class="p-3">${statusBadge}</td>
            <td class="p-3 text-right"><button onclick="alert('Viewing detailed academic breakdown for ${s.name}')" class="px-3 py-1 bg-stone-100 hover:bg-stone-200 rounded-lg text-xs font-bold transition cursor-pointer shadow-2xs">View Breakdown</button></td>
        `;
        tbody.appendChild(tr);
    });
}