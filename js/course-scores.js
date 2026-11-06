const scoreForm = document.getElementById('courseScoresForm');
let scoresChanged = scoreForm.dataset.unsaved === 'true';

function updateScorePreview() {
    const components = document.querySelectorAll('.score-component');
    let percentage = 0;
    let weightTotal = 0;
    let pendingExams = false;
    let missingCoursework = false;
    let validWeights = components.length > 0;
    let validEntries = true;
    const componentTotals = [];

    components.forEach(component => {
        const weight = Number(component.dataset.weight);
        const isExam = component.dataset.isExam === '1';
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
        componentTotals.push({
            id: Number(component.dataset.componentId),
            name: component.querySelector('h2').textContent,
            weight, earned, maximum, isExam
        });
        const summary = component.querySelector('.component-summary');
        if (maximum > 0) {
            const componentPercentage = (earned / maximum) * 100;
            const weighted = (earned / maximum) * weight;
            percentage += weighted;
            summary.textContent = `${earned.toFixed(2)} / ${maximum} · ${componentPercentage.toFixed(2)}% · ${weighted.toFixed(2)} weighted points`;
        } else {
            if (isExam) pendingExams = true;
            else missingCoursework = true;
            summary.textContent = isExam ? 'Awaiting exam score' : 'No scored entries yet';
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
    } else if (missingCoursework) {
        gradePreview.textContent = 'Incomplete';
        note.textContent = 'Each non-exam component needs at least one scored entry. Partial scores can be saved.';
    } else if (pendingExams) {
        gradePreview.textContent = 'In Progress';
        percentagePreview.textContent = `${percentage.toFixed(2)}% earned so far`;
        note.textContent = 'The full 100% weight is retained. Pending exams add no points yet. This course is excluded from GWA until all exams are scored.';
    } else {
        const precisePercentage = Number(percentage.toFixed(10));
        const grade = window.courseGradeScale.find(([minimum]) => precisePercentage >= minimum)[1];
        gradePreview.textContent = grade.toFixed(2);
        percentagePreview.textContent = `${percentage.toFixed(2)}% overall`;
        note.textContent = 'Calculated from total points within each component and its weight. Save Scores to update your calculator and GWA.';
    }
    updateExamPrediction(componentTotals, validWeights && weightTotal === 100, validEntries);
}

function updateExamPrediction(components, validWeights, validEntries) {
    const result = document.getElementById('examPredictionResult');
    const note = document.getElementById('examPredictionNote');
    const maxInput = document.getElementById('predictorMax');
    const examSelect = document.getElementById('predictorExam');
    const exams = components.filter(component => component.isExam);
    const pending = exams.filter(component => component.maximum === 0);
    // Keep the professor's exam names visible, including exams already scored.
    [...examSelect.options].forEach(option => {
        const exam = exams.find(component => component.id === Number(option.value));
        if (!exam) return;
        option.disabled = exam.maximum > 0;
        option.textContent = exam.name + (exam.maximum > 0 ? ' — Scored' : '');
    });
    let selected = exams.find(component => component.id === Number(examSelect.value));
    if (pending.length > 0 && (!selected || selected.maximum > 0)) {
        selected = pending[0];
        examSelect.value = String(selected.id);
        maxInput.value = '';
    }
    examSelect.disabled = exams.length === 0;
    maxInput.disabled = pending.length === 0;
    const examMaximum = Number(maxInput.value);
    const target = window.courseGradeScale.find(([, grade]) => grade === window.courseTargetGrade);
    note.textContent = 'Uses the full course weights and currently recorded scores. Other pending exams add no points yet. Predictions are not saved as scores.';
    if (exams.length === 0) {
        result.textContent = 'No exams are marked in this course template.';
        note.textContent = 'Only components marked Exam by your professor appear here.';
        return;
    }
    if (!validWeights) {
        result.textContent = 'Correct the template weights before predicting.';
        return;
    }
    if (!validEntries) {
        result.textContent = 'Correct the score entries above before predicting.';
        return;
    }
    if (pending.length === 0) {
        result.textContent = 'All exams are scored.';
        note.textContent = 'Actual exam scores are included in your course grade. Clear both score and max score to make an exam pending again.';
        return;
    }
    if (!selected) {
        result.textContent = 'Select a pending examination.';
        return;
    }
    if (maxInput.value === '') {
        result.textContent = 'Enter the examination\'s maximum score.';
        return;
    }
    if (!maxInput.checkValidity() || !/^[1-9]\d*$/.test(maxInput.value)) {
        result.textContent = 'Enter a positive whole-number exam maximum.';
        return;
    }
    if (!target || target[1] > 3) {
        result.textContent = 'Choose a passing target grade in Grade Entry.';
        return;
    }
    if (selected.weight <= 0) {
        result.textContent = 'This component has no weight in your course grade.';
        return;
    }
    const others = components.filter(component => component.id !== selected.id);
    const missing = others.filter(component => !component.isExam && component.maximum === 0);
    if (missing.length > 0) {
        result.textContent = 'Complete the non-exam coursework first.';
        note.textContent = `Enter at least one scored entry for: ${missing.map(component => component.name).join(', ')}.`;
        return;
    }

    const otherPoints = others.reduce((sum, component) =>
        sum + (component.maximum > 0 ? component.earned / component.maximum * component.weight : 0), 0);
    const required = (target[0] - otherPoints) / selected.weight * examMaximum;
    const otherPending = others.filter(component => component.isExam && component.maximum === 0);
    const pendingNote = otherPending.length > 0
        ? ` Pending exams (${otherPending.map(component => component.name).join(', ')}) retain their weights and add no points yet. This prediction may change when they are scored.`
        : '';
    // Round up to whole points, removing only floating-point noise first.
    const requiredScore = Math.max(0, Math.ceil(Number(required.toFixed(10))));
    if (requiredScore > examMaximum) {
        result.textContent = 'Target unreachable with this exam';
        note.textContent = `With currently recorded scores, you would need ${requiredScore} / ${examMaximum} on ${selected.name} to reach ${window.courseTargetGrade.toFixed(2)} or better.${pendingNote}`;
    } else if (requiredScore === 0) {
        result.textContent = 'Target already secured';
        note.textContent = `Even 0 / ${examMaximum} on ${selected.name} meets ${window.courseTargetGrade.toFixed(2)} or better using currently recorded scores.${pendingNote}`;
    } else {
        result.textContent = `You need at least ${requiredScore} / ${examMaximum} on ${selected.name}.`;
        note.textContent = `Target: ${window.courseTargetGrade.toFixed(2)} or better (${target[0]}% overall). Required score rounded up to a whole point using the full course weights. This is a prediction, not a saved score.${pendingNote}`;
    }
}

document.getElementById('predictorExam').addEventListener('change', () => {
    document.getElementById('predictorMax').value = '';
    updateScorePreview();
});
document.getElementById('predictorMax').addEventListener('input', updateScorePreview);

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
