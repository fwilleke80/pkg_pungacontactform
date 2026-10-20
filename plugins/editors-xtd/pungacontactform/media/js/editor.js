/**
 * @file
 * @brief Joomla editor button dialog that inserts an allowlisted placeholder.
 */
import { JoomlaEditorButton } from 'editor-api';

/**
 * @brief Adds a labeled select element to the editor dialog.
 * @param {HTMLElement} container Parent dialog body.
 * @param {string} title Display label.
 * @param {Array<{value:string,text:string}>} items Allowed values.
 * @returns {HTMLSelectElement} Newly created selection control.
 */
function appendSelect(container, title, items)
{
    const label = document.createElement('label');
    label.textContent = title;
    label.style.display = 'block';
    label.style.marginBottom = '1rem';
    const select = document.createElement('select');
    select.className = 'form-select';
    select.style.display = 'block';
    select.style.width = '100%';
    select.style.marginTop = '.3rem';

    for (const item of items)
    {
        const option = document.createElement('option');
        option.value = item.value;
        option.textContent = item.text;
        select.appendChild(option);
    }
    label.appendChild(select);
    container.appendChild(label);
    return select;
}

JoomlaEditorButton.registerAction('insert-pungacontactform', (editor) =>
{
    const settings = Joomla.getOptions('plg_editors-xtd_pungacontactform') || {};
    const strings = settings.strings || {};
    const modules = Array.isArray(settings.modules) ? settings.modules : [];
    const dialog = document.createElement('dialog');
    dialog.setAttribute('aria-label', strings.title || 'Contact form');
    dialog.style.width = 'min(94vw, 29rem)';
    dialog.style.maxWidth = '29rem';
    dialog.style.border = '1px solid #888';
    dialog.style.borderRadius = '.6rem';
    dialog.style.padding = '1.5rem';
    dialog.style.boxShadow = '0 1rem 3rem #0003';

    const heading = document.createElement('h3');
    heading.textContent = strings.title || 'Contact form';
    heading.style.margin = '0 0 1.5rem';
    dialog.appendChild(heading);

    const module = appendSelect(dialog, strings.source || 'Configuration', [
        {value: '', text: strings.default || 'Component defaults'},
        ...modules.map((row) => ({value: String(row.id), text: row.title})),
    ]);
    const layout = appendSelect(dialog, strings.layout || 'Layout', [
        {value: '', text: strings.inherit || 'Inherit'},
        {value: 'left', text: strings.left || 'Left'},
        {value: 'centered', text: strings.centered || 'Centered'},
    ]);
    const intro = appendSelect(dialog, strings.intro || 'Introduction', [
        {value: 'yes', text: strings.yes || 'Yes'},
        {value: 'no', text: strings.no || 'No'},
    ]);

    const actions = document.createElement('div');
    actions.style.display = 'flex';
    actions.style.gap = '.6rem';
    actions.style.justifyContent = 'flex-end';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'btn btn-outline-secondary';
    cancel.textContent = strings.cancel || 'Cancel';
    cancel.addEventListener('click', () => dialog.close());
    const insert = document.createElement('button');
    insert.type = 'button';
    insert.className = 'btn btn-primary';
    insert.textContent = strings.insert || 'Insert';
    insert.addEventListener('click', () =>
    {
        const args = [];
        if (module.value)
        {
            args.push(`module=${module.value}`);
        }
        if (layout.value)
        {
            args.push(`layout=${layout.value}`);
        }
        if (intro.value === 'no')
        {
            args.push('intro=no');
        }
        const placeholder = `{contactform${args.length ? ` ${args.join(' ')}` : ''}}`;
        editor.replaceSelection(placeholder);
        dialog.close();
    });
    actions.append(cancel, insert);
    dialog.appendChild(actions);
    dialog.addEventListener('close', () => dialog.remove(), {once: true});
    document.body.appendChild(dialog);
    dialog.showModal();
});
