// Never submit a grading, editing or recovery form merely to switch language.
(() => {
    'use strict';
    let dirty = false;
    for (const eventName of ['input', 'change']) {
        document.addEventListener(eventName, event => {
            if (event.target.closest('form, [contenteditable="true"]')) dirty = true;
        });
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-professor-language]');
        if (!link || link.hasAttribute('aria-current')) return;
        if (dirty && !window.confirm(link.dataset.unsavedMessage)) event.preventDefault();
    });
})();