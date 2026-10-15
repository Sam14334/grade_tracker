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
    renderTemplatesList();
    calculateTotalWeight();

    document.getElementById('addRowBtn').addEventListener('click', addComponentRow);
    document.getElementById('publishBtn').addEventListener('click', publishTemplate);

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
        components.push({ name: n.value || 'Criterion', weight: parseFloat(compWeights[i].value) || 0 });
    });

    const generatedCode = `${code.replace(/[^a-zA-Z0-9]/g, '')}-SEC${Math.floor(1 + Math.random() * 9)}`;

    enrolledTemplates.push({ id: Date.now(), code, title, classCode: generatedCode, components });
    renderTemplatesList();
    alert(`Syllabus published successfully!\nShare this Class Sync Code with students: ${generatedCode}`);
}

window.removeTemplate = function(id) {
    if (confirm("Remove this published syllabus class code?")) {
        enrolledTemplates = enrolledTemplates.filter(t => t.id !== id);
        renderTemplatesList();
    }
}