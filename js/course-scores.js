const scoreForm = document.getElementById('courseScoresForm');
let scoresChanged = scoreForm.dataset.unsaved === 'true';

function updateScorePreview() {
    const components = document.querySelectorAll('.score-component');
    let percentage = 0;
    let weightTotal = 0;
    let complete = components.length > 0;
    let validWeights = components.length > 0;
    let validEntries = true;

    components.forEach(component => {
        const weight = Number(component.dataset.weight);
        weightTotal += weight;
        if (weight < 0 || weight > 100) validWeights = false;
        let earned = 0;
        let maximum = 0;
        const rows = component.querySelectorAll('.score-entry');
        rows.forEach(row => {
            const score = row.querySelector('.entry-score');
            const max = row.querySelector('.entry-max');
            score.setCustomValidity('');
            max.setCustomValidity('');
            const scoreBlank = score.value === '' && !score.validity.badInput;
            const maxBlank = max.value === '' && !max.validity.badInput;
            if (scoreBlank && maxBlank) return;
            if (scoreBlank) score.setCustomValidity('Enter a score, including 0 if nothing was earned.');
            if (maxBlank) max.setCustomValidity('Enter the maximum score.');
            if (!scoreBlank && !/^\d{1,7}(?:\.\d{1,2})?$/.test(score.value)) {
                score.setCustomValidity('Use a score with up to two decimal places.');
            }
            if (!maxBlank && !/^[1-9]\d*$/.test(max.value)) {
                max.setCustomValidity('Use a positive whole-number maximum.');
            }
            if (!maxBlank && Number(score.value) > Number(max.value)) {
                score.setCustomValidity('Score cannot exceed the maximum.');
            }
            if (!score.checkValidity() || !max.checkValidity()) {
                validEntries = false;
                return;
            }
            earned += Number(score.value);
            maximum += Number(max.value);
        });
        const summary = component.querySelector('.component-summary');
        if (maximum > 0) {
            const componentPercentage = (earned / maximum) * 100;
            const weighted = (earned / maximum) * weight;
            percentage += weighted;
            summary.textContent = `${earned.toFixed(2)} / ${maximum} · ${componentPercentage.toFixed(2)}% · ${weighted.toFixed(2)} weighted points`;
        } else {
            complete = false;
            summary.textContent = 'No scored entries yet';
        }
        rows.forEach(row => {
            const remove = row.querySelector('.remove-entry');
            remove.disabled = rows.length === 1;
            remove.classList.toggle('opacity-40', rows.length === 1);
        });
    });

    const gradePreview = document.getElementById('courseGradePreview');
    const percentagePreview = document.getElementById('coursePercentagePreview');
    const note = document.getElementById('courseCalculationNote');
    percentagePreview.textContent = '';
    if (!validWeights || weightTotal !== 100) {
        gradePreview.textContent = 'Invalid weights';
        note.textContent = `Template weights must total 100% and each be between 0% and 100% (currently ${weightTotal}%). Ask your professor to correct them. Scores can still be saved.`;
    } else if (!validEntries) {
        gradePreview.textContent = 'Check entries';
        note.textContent = 'Use scores from 0 to their maximum, up to two decimal places, and positive whole-number maximum scores.';
    } else if (!complete) {
        gradePreview.textContent = 'Incomplete';
        note.textContent = 'Each component needs at least one scored entry. Completely blank rows are ignored. Partial scores can be saved.';
    } else {
        const precisePercentage = Number(percentage.toFixed(10));
        const grade = window.courseGradeScale.find(([minimum]) => precisePercentage >= minimum)[1];
        gradePreview.textContent = grade.toFixed(2);
        percentagePreview.textContent = `${percentage.toFixed(2)}% overall`;
        note.textContent = 'Calculated from total points within each component and its weight. Save Scores to update your calculator and GWA.';
    }
}

function markScoresChanged() {
    scoresChanged = true;
    document.getElementById('scoreSaveStatus').textContent = 'Unsaved changes';
    updateScorePreview();
}

scoreForm.addEventListener('input', markScoresChanged);
scoreForm.addEventListener('click', event => {
    const add = event.target.closest('.add-entry');
    const remove = event.target.closest('.remove-entry');
    if (add) {
        const component = add.closest('.score-component');
        const index = Number(component.dataset.nextIndex);
        component.dataset.nextIndex = index + 1;
        const row = document.getElementById('scoreEntryTemplate').content.cloneNode(true);
        row.querySelectorAll('[data-field]').forEach(input => {
            input.name = `entries[${component.dataset.componentId}][${index}][${input.dataset.field}]`;
        });
        row.querySelector('.entry-title').value = `Entry ${index + 1}`;
        component.querySelector('.score-entries').appendChild(row);
        markScoresChanged();
    } else if (remove) {
        const component = remove.closest('.score-component');
        if (component.querySelectorAll('.score-entry').length <= 1) return;
        remove.closest('.score-entry').remove();
        markScoresChanged();
    }
});

scoreForm.addEventListener('submit', event => {
    updateScorePreview();
    if (!scoreForm.reportValidity()) {
        event.preventDefault();
        return;
    }
    scoresChanged = false;
});

window.addEventListener('beforeunload', event => {
    if (!scoresChanged) return;
    event.preventDefault();
    event.returnValue = '';
});

updateScorePreview();
