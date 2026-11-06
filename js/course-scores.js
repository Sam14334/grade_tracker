const scoreForm = document.getElementById('courseScoresForm');
const periodLabels = {midterms: 'Midterms', finals: 'Finals'};
let scoresChanged = scoreForm.dataset.unsaved === 'true';

function calculatePeriodPreview(components) {
    const weight = components.reduce((sum, component) => sum + component.weight, 0);
    if (!components.length || weight !== 100 || components.some(component => component.weight < 0 || component.weight > 100)) {
        return {status: 'Invalid weights', grade: null, percentage: null};
    }
    if (components.some(component => !component.valid)) return {status: 'Check entries', grade: null, percentage: null};
    if (components.some(component => !component.isExam && component.maximum === 0)) {
        return {status: 'Incomplete', grade: null, percentage: null};
    }
    const percentage = components.reduce((sum, component) => sum +
        (component.maximum > 0 ? component.earned / component.maximum * component.weight : 0), 0);
    if (components.some(component => component.isExam && component.maximum === 0)) {
        return {status: 'In Progress', grade: null, percentage};
    }
    const grade = window.courseGradeScale.find(([minimum]) => Number(percentage.toFixed(10)) >= minimum)[1];
    return {status: 'Complete', grade, percentage};
}

function updateScorePreview() {
    const totals = [];
    document.querySelectorAll('.score-component').forEach(component => {
        const weight = Number(component.dataset.weight);
        const isExam = component.dataset.isExam === '1';
        let earned = 0;
        let maximum = 0;
        let valid = true;
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
            if (!scoreBlank && !/^\d{1,7}(?:\.\d{1,2})?$/.test(score.value)) score.setCustomValidity('Use a score with up to two decimal places.');
            if (!maxBlank && !/^[1-9]\d*$/.test(max.value)) max.setCustomValidity('Use a positive whole-number maximum.');
            if (!maxBlank && Number(score.value) > Number(max.value)) score.setCustomValidity('Score cannot exceed the maximum.');
            if (!score.checkValidity() || !max.checkValidity()) {
                valid = false;
                return;
            }
            earned += Number(score.value);
            maximum += Number(max.value);
        });
        totals.push({id: component.dataset.componentId, period: component.dataset.period,
            name: component.querySelector('h2').textContent, weight, earned, maximum, isExam, valid});
        component.querySelector('.component-summary').textContent = maximum > 0
            ? `${earned.toFixed(2)} / ${maximum} · ${(earned / maximum * 100).toFixed(2)}% · ${(earned / maximum * weight).toFixed(2)} weighted points`
            : isExam ? 'Awaiting exam score' : 'No scored entries yet';
    });

    const periods = {};
    Object.keys(periodLabels).forEach(period => {
        periods[period] = calculatePeriodPreview(totals.filter(component => component.period === period));
        const result = periods[period];
        document.getElementById(`periodGrade-${period}`).textContent = result.grade === null ? result.status : result.grade.toFixed(2);
        document.getElementById(`periodPercentage-${period}`).textContent = result.percentage === null ? ''
            : `${result.percentage.toFixed(2)}%${result.status === 'Complete' ? '' : ' earned so far'}`;
    });
    const results = Object.values(periods);
    const status = ['Invalid weights', 'Check entries', 'Incomplete', 'In Progress'].find(value => results.some(result => result.status === value)) ?? 'Complete';
    const percentage = results.every(result => result.percentage !== null)
        ? (periods.midterms.percentage + periods.finals.percentage) / 2 : null;
    const grade = status === 'Complete'
        ? window.courseGradeScale.find(([minimum]) => Number(percentage.toFixed(10)) >= minimum)[1] : null;
    document.getElementById('courseGradePreview').textContent = grade === null ? status : grade.toFixed(2);
    document.getElementById('coursePercentagePreview').textContent = percentage === null ? ''
        : `${percentage.toFixed(2)}% ${status === 'Complete' ? 'overall' : 'earned so far'}`;
    const notes = {
        'Invalid weights': 'Midterms and Finals must each total 100%. Ask your professor to correct the template.',
        'Check entries': 'Correct invalid score entries. Scores cannot exceed their maximum.',
        'Incomplete': 'Each non-exam component needs at least one scored entry. Partial scores can be saved.',
        'In Progress': 'Each period retains its full weights. Pending exams add no points yet; this course stays out of GWA until both periods are complete.',
        'Complete': 'Overall percentage = 50% Midterms + 50% Finals, converted once to the PUP scale. Save Scores to update your calculator and GWA.'
    };
    document.getElementById('courseCalculationNote').textContent = notes[status];
    updateExamPrediction(totals);
}

function updateExamPrediction(components) {
    const result = document.getElementById('examPredictionResult');
    const note = document.getElementById('examPredictionNote');
    const maxInput = document.getElementById('predictorMax');
    const select = document.getElementById('predictorExam');
    const exams = components.filter(component => component.isExam);
    const pending = exams.filter(component => component.maximum === 0);
    [...select.options].forEach(option => {
        const exam = exams.find(component => component.id === option.value);
        if (!exam) return;
        option.disabled = exam.maximum > 0;
        option.textContent = `${periodLabels[exam.period]} · ${exam.name}${exam.maximum > 0 ? ' — Scored' : ''}`;
    });
    let selected = exams.find(component => component.id === select.value);
    if (pending.length > 0 && (!selected || selected.maximum > 0)) {
        selected = pending[0];
        select.value = selected.id;
        maxInput.value = '';
    }
    select.disabled = exams.length === 0;
    maxInput.disabled = pending.length === 0;
    note.textContent = 'Uses only the selected exam’s period and the target set in Grade Entry. Predictions are not saved as scores.';
    if (!exams.length) {
        result.textContent = 'No exams are marked in this course template.';
        return;
    }
    if (!pending.length) {
        result.textContent = 'All exams are scored.';
        note.textContent = 'Actual exam scores are included. Clear both score and max score to make an exam pending again.';
        return;
    }
    if (!selected) {
        result.textContent = 'Select a pending examination.';
        return;
    }
    const periodComponents = components.filter(component => component.period === selected.period);
    const period = calculatePeriodPreview(periodComponents);
    if (period.status === 'Invalid weights' || period.status === 'Check entries') {
        result.textContent = `Correct the ${periodLabels[selected.period]} weights or score entries before predicting.`;
        return;
    }
    const others = periodComponents.filter(component => component.id !== selected.id);
    const missing = others.filter(component => !component.isExam && component.maximum === 0);
    if (missing.length) {
        result.textContent = `Complete the ${periodLabels[selected.period]} non-exam coursework first.`;
        note.textContent = `Enter at least one scored entry for: ${missing.map(component => `${periodLabels[component.period]} · ${component.name}`).join(', ')}.`;
        return;
    }
    if (maxInput.value === '') {
        result.textContent = 'Enter the examination’s maximum score.';
        return;
    }
    if (!maxInput.checkValidity() || !/^[1-9]\d*$/.test(maxInput.value)) {
        result.textContent = 'Enter a positive whole-number exam maximum.';
        return;
    }
    const target = window.courseGradeScale.find(([, grade]) => grade === window.courseTargetGrade);
    if (!target || target[1] > 3 || selected.weight <= 0) {
        result.textContent = 'A passing target grade and a positive exam weight are required.';
        return;
    }
    const maximum = Number(maxInput.value);
    const otherPoints = others.reduce((sum, component) => sum +
        (component.maximum > 0 ? component.earned / component.maximum * component.weight : 0), 0);
    const required = Math.max(0, Math.ceil(Number(((target[0] - otherPoints) / selected.weight * maximum).toFixed(10))));
    if (required > maximum) result.textContent = 'Target unreachable with this exam';
    else if (required === 0) result.textContent = 'Target already secured for this period';
    else result.textContent = `You need at least ${required} / ${maximum} on ${selected.name}.`;
    note.textContent = `${periodLabels[selected.period]} target: ${window.courseTargetGrade.toFixed(2)} or better (${target[0]}%). Required score: ${required} / ${maximum}, rounded up. Only ${periodLabels[selected.period]} coursework is used, even when the other period has scores.`;
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
        const row = remove.closest('.score-entry');
        row.querySelector('.entry-score').value = '';
        row.querySelector('.entry-max').value = '';
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
