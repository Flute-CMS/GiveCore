function initCustomDriverFields() {
    var editor = document.getElementById('cdf-fields-editor');
    if (!editor || editor.dataset.cdfInit) return;
    editor.dataset.cdfInit = '1';

    var list = document.getElementById('cdf-fields-list');
    var addBtn = document.getElementById('cdf-add-field');
    var config = JSON.parse(editor.dataset.config || '{}');
    var i18n = config.i18n || {};
    var idx = editor.querySelectorAll('.cdf-row').length;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function selectHtml(name, opts, selected) {
        var html = '';
        Object.keys(opts).forEach(function (k) {
            html += '<option value="' + esc(k) + '"' + (k === selected ? ' selected' : '') + '>' + esc(opts[k]) + '</option>';
        });
        return '<div class="select-wrapper"><div class="select__field-container">'
            + '<select name="' + esc(name) + '" class="select__field" data-select'
            + ' data-initial-value="' + esc(JSON.stringify(selected || 'text')) + '">'
            + html + '</select></div></div>';
    }

    function createRow(data) {
        var i = idx++;
        var reqId = 'cdf-req-' + i;

        var html = '<div class="cdf-row" data-index="' + i + '">'
            + '<div class="cdf-col cdf-col--name">'
            +   '<label class="form__label">' + esc(i18n.name || 'Name') + '</label>'
            +   '<div class="input-wrapper"><div class="input__field-container">'
            +   '<input type="text" name="field_name[' + i + ']" class="input__field" value="' + esc(data.name) + '" placeholder="group">'
            +   '</div></div>'
            + '</div>'
            + '<div class="cdf-col cdf-col--label">'
            +   '<label class="form__label">' + esc(i18n.label || 'Label') + '</label>'
            +   '<div class="input-wrapper"><div class="input__field-container">'
            +   '<input type="text" name="field_label[' + i + ']" class="input__field" value="' + esc(data.label) + '" placeholder="">'
            +   '</div></div>'
            + '</div>'
            + '<div class="cdf-col cdf-col--type">'
            +   '<label class="form__label">' + esc(i18n.type || 'Type') + '</label>'
            +   selectHtml('field_type[' + i + ']', { text: 'text', number: 'number', textarea: 'textarea' }, data.type || 'text')
            + '</div>'
            + '<div class="cdf-col cdf-col--req">'
            +   '<label class="form__label">' + esc(i18n.required || '*') + '</label>'
            +   '<input type="hidden" name="field_required[' + i + ']" value="0">'
            +   '<input type="checkbox" name="field_required[' + i + ']" value="1" class="checkbox__field" id="' + reqId + '"' + (data.required ? ' checked' : '') + '>'
            +   '<label for="' + reqId + '"></label>'
            + '</div>'
            + '<div class="cdf-col cdf-col--act">'
            +   '<button type="button" class="btn btn-outline-error btn-tiny cdf-remove">&times;</button>'
            + '</div>'
            + '</div>';

        list.insertAdjacentHTML('beforeend', html);

        var row = list.lastElementChild;
        bindRow(row);
        initNewSelects(row);
    }

    function bindRow(row) {
        var btn = row.querySelector('.cdf-remove');
        if (btn && !btn._cdfBound) {
            btn._cdfBound = true;
            btn.addEventListener('click', function () {
                row.remove();
            });
        }
    }

    function initNewSelects(el) {
        if (!window.Select) return;
        el.querySelectorAll('[data-select]').forEach(function (s) {
            var existing = (typeof FluteSelect !== 'undefined') ? FluteSelect.get(s) : null;
            if (!existing && window.Select.createInstance) {
                window.Select.createInstance(s);
            } else if (!existing && window.Select.init) {
                window.Select.init(el);
            }
        });
    }

    editor.querySelectorAll('.cdf-row').forEach(bindRow);

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            createRow({ name: '', label: '', type: 'text', required: false });
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCustomDriverFields);
} else {
    initCustomDriverFields();
}

document.body.addEventListener('htmx:afterSettle', initCustomDriverFields);
