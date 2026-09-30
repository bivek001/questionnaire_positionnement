(() => {
    'use strict';
    const rows = document.getElementById('qp-choice-rows');
    const type = document.getElementById('question_type');
    if (!rows || !type) return;
    const renumber = () => {
        [...rows.children].forEach((row, index) => {
            const text = row.querySelector('input[type="text"]');
            text.name = `choices[${index}]`;
            text.id = `qp-option-${index}`;
            const label = row.querySelector('.qp-option-label');
            label.htmlFor = text.id;
            label.textContent = `${rows.dataset.option} ${index + 1}`;
            row.querySelector('[name="correct_choices[]"]').value = String(index);
            row.querySelector('.qp-remove').setAttribute('aria-label', `${rows.dataset.remove} ${index + 1}`);
        });
    };
    const syncType = () => {
        const controls = [...rows.querySelectorAll('[name="correct_choices[]"]')];
        const chosen = controls.filter(control => control.checked);
        controls.forEach(control => { control.checked = false; });
        controls.forEach(control => {
            control.type = type.value === 'single_choice' ? 'radio' : 'checkbox';
        });
        (type.value === 'single_choice' ? chosen.slice(0, 1) : chosen).forEach(control => { control.checked = true; });
        document.getElementById('qp-open-settings').hidden = type.value !== 'open';
    };
    document.getElementById('qp-add-option').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'qp-choice-row question-choice';
        const label = document.createElement('label'); label.className = 'qp-option-label';
        const text = document.createElement('input'); text.type = 'text';
        const correctLabel = document.createElement('label'); correctLabel.className = 'question-correct';
        const correct = document.createElement('input'); correct.type = type.value === 'single_choice' ? 'radio' : 'checkbox'; correct.name = 'correct_choices[]';
        correctLabel.append(correct, document.createTextNode(' ' + rows.dataset.correct));
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-secondary qp-remove'; remove.textContent = rows.dataset.remove;
        row.append(label, text, correctLabel, remove); rows.append(row); renumber(); text.focus();
    });
    rows.addEventListener('click', event => {
        const button = event.target.closest('.qp-remove');
        if (!button) return;
        const row = button.closest('.qp-choice-row');
        const focusTarget = row.nextElementSibling || row.previousElementSibling;
        row.remove(); renumber();
        (focusTarget?.querySelector('input[type="text"]') || document.getElementById('qp-add-option')).focus();
    });
    type.addEventListener('change', syncType);
    rows.closest('form').addEventListener('submit', renumber);
    renumber(); syncType();
})();
