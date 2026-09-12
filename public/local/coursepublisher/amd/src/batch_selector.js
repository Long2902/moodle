/**
 * Multi-target selector helpers.
 *
 * @module local_coursepublisher/batch_selector
 */

const updateCount = root => {
    const count = root.querySelectorAll('.cp-batch-checkbox:checked').length;
    const output = root.querySelector('[data-region="selected-count"]');
    if (output) {
        output.textContent = String(count);
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="batch-selector"]');
    if (!root) {
        return;
    }
    root.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) {
            return;
        }
        const action = button.dataset.action;
        if (action === 'select-all' || action === 'clear-all') {
            const checked = action === 'select-all';
            root.querySelectorAll('.cp-batch-target:not([hidden]) .cp-batch-checkbox').forEach(box => {
                box.checked = checked;
            });
        } else if (action === 'select-region') {
            const regionid = button.dataset.regionId;
            root.querySelectorAll(`.cp-batch-target[data-region-id="${regionid}"]:not([hidden]) .cp-batch-checkbox`).forEach(box => {
                box.checked = true;
            });
        } else {
            return;
        }
        updateCount(root);
    });
    root.addEventListener('change', event => {
        if (event.target.matches('.cp-batch-checkbox')) {
            updateCount(root);
        }
    });
    const search = root.querySelector('[data-action="search"]');
    if (search) {
        search.addEventListener('input', () => {
            const needle = search.value.trim().toLocaleLowerCase();
            root.querySelectorAll('.cp-batch-target').forEach(row => {
                row.hidden = needle !== '' && !(row.dataset.search || '').toLocaleLowerCase().includes(needle);
            });
        });
    }
    updateCount(root);
};
