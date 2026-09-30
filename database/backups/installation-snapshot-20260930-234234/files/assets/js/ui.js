document.addEventListener('DOMContentLoaded', () => {
    const french = document.documentElement.lang.startsWith('fr');
    document.querySelectorAll('table').forEach(table => {
        if (table.closest('.ui-table-scroll')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'ui-table-scroll';
        wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', french ? 'Tableau de données défilant' : 'Scrollable data table');
        table.before(wrapper);
        wrapper.append(table);
    });
    // Associate existing visible labels with legacy form fields without changing data.
    document.querySelectorAll('label').forEach((label, i) => {
        if (label.htmlFor || label.querySelector('input,select,textarea')) return;
        const group = label.parentElement;
        const controls = group.querySelectorAll('input:not([type=hidden]),select,textarea');
        if (controls.length !== 1) return;
        const field = controls[0];
        if (!field.id) field.id = `ui-field-${i}`;
        label.htmlFor = field.id;
    });
    const main = document.querySelector('main');
    if (main) {
        if (!main.id) main.id = 'main-content';
        const skip = document.createElement('a');
        skip.className = 'skip-link';
        skip.href = `#${main.id}`;
        skip.textContent = french ? 'Aller au contenu principal' : 'Skip to main content';
        document.body.prepend(skip);
    }
});

document.addEventListener('DOMContentLoaded', () => {
 const fr = document.documentElement.lang.startsWith('fr');
 document.querySelectorAll('input[type="password"]').forEach((input) => {
  const button = document.createElement('button');
  button.type = 'button'; button.className = 'password-toggle';
  const update = () => {
   const shown = input.type === 'text';
   button.textContent = '👁 ' + (shown ? (fr ? 'Masquer' : 'Hide') : (fr ? 'Afficher' : 'Show'));
   button.setAttribute('aria-label', fr ? (shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe') : (shown ? 'Hide password' : 'Show password'));
   button.setAttribute('aria-pressed', String(shown));
  };
  button.addEventListener('click', () => { input.type = input.type === 'password' ? 'text' : 'password'; update(); });
  update(); input.after(button);
 });
});
