let nextComponentIndex = typeof enrolledTemplates !== 'undefined' ? document.querySelectorAll('#componentContainer .component-row').length : 3;

document.addEventListener("DOMContentLoaded", () => {
    renderTemplatesList();
    calculateTotalWeight();

    const addRowBtn = document.getElementById('addRowBtn');
    if (addRowBtn) {
        addRowBtn.addEventListener('click', addComponentRow);
    }

    const publishBtn = document.getElementById('publishBtn');
    if (publishBtn) {
        publishBtn.addEventListener('click', (e) => {
            const form = document.getElementById('publishTemplateForm');
            if (validatePublishForm() && form) {
                publishBtn.disabled = true;
                publishBtn.innerText = 'Publishing...';
                
                // Append hidden submit indicator so PHP handles it correctly
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'btnPublishTemplate';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);

                form.submit();
            }
        });
    }

    const componentContainer = document.getElementById('componentContainer');
    if (componentContainer) {
        componentContainer.addEventListener('input', (e) => {
            if (e.target.classList.contains('comp-weight')) {
                calculateTotalWeight();
            }
        });

        componentContainer.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('.remove-row-btn');
            if (deleteBtn) {
                deleteBtn.closest('.component-row').remove();
                calculateTotalWeight();
            }
        });
    }

    // Check URL parameters for successful database publish alert
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('template') === 'success') {
        const code = urlParams.get('code') ? `\nClass Sync Code: ${urlParams.get('code')}` : '';
        alert(`Syllabus template published and saved to database!${code}\nShare this code with your students.`);
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

function renderTemplatesList() {
    const container = document.getElementById('publishedTemplatesList');
    if (!container) return;
    container.innerHTML = '';

    if (!enrolledTemplates || enrolledTemplates.length === 0) {
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
    const index = nextComponentIndex++;
    row.className = 'flex flex-wrap items-center gap-2 component-row';
    row.innerHTML = `
        <input type="text" name="component_name[${index}]" placeholder="Assessment Name" required class="comp-name min-w-0 w-full sm:w-auto sm:flex-1 px-3 py-2 rounded-xl border border-stone-300 font-medium">
        <label class="flex items-center gap-1.5 font-semibold text-stone-600 cursor-pointer">
            <input type="checkbox" name="is_exam[${index}]" value="1" class="w-4 h-4 rounded border-stone-300 accent-maroon-900"> Exam
        </label>
        <input type="number" name="weight[${index}]" value="10" min="1" max="100" required class="comp-weight w-24 px-3 py-2 rounded-xl border border-stone-300 font-bold text-right">
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
    if (badge) {
        badge.innerText = `Total Weight: ${total}%`;
        badge.className = total === 100
            ? "text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200"
            : "text-xs font-bold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200";
    }

    return total;
}

function validatePublishForm() {
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
    return true;
}

window.removeTemplate = function(id) {
    if (confirm("Remove this published syllabus from database?")) {
        window.location.href = `php/delete_template.php?id=${id}`;
    }
}