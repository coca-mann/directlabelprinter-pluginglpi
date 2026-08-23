(function() {
    'use strict';

    const PX_PER_MM = 4; // canvas scale factor; GridStack cellHeight/column width derive from this
    let grid = null;
    let elements = [];
    let selectedIndex = null;
    let selectedWidgetEl = null;

    function mmToCol(widthMm, labelWidthMm, columns) {
        return Math.max(1, Math.round((widthMm / labelWidthMm) * columns));
    }

    function init() {
        const container = document.getElementById('dlp-layout-editor');
        if (!container) {
            return;
        }
        const widthMm = parseFloat(container.dataset.widthMm);
        const heightMm = parseFloat(container.dataset.heightMm);
        const columns = Math.round(widthMm * PX_PER_MM / 10); // 10px-wide columns

        const gridEl = document.getElementById('dlp-grid');
        gridEl.style.width = (widthMm * PX_PER_MM) + 'px';
        gridEl.style.height = (heightMm * PX_PER_MM) + 'px';

        grid = GridStack.init({
            column: columns,
            cellHeight: 10,
            float: true,
            margin: 0,
        }, gridEl);

        grid.on('change', syncElementsFromGrid);

        elements = JSON.parse(document.getElementById('dlp-elements-input').value || '[]');
        elements.forEach(el => addGridItem(el, widthMm, heightMm, columns));

        document.getElementById('dlp-add-text-btn').addEventListener('click', () => {
            addElement({ type: 'text', x: 0, y: 0, width: 30, height: 8, data_source: 'titulo', font_size: 10, text_align: 'left', text_valign: 'top' }, widthMm, heightMm, columns);
        });
        document.getElementById('dlp-add-qr-btn').addEventListener('click', () => {
            addElement({ type: 'qrcode', x: 0, y: 0, size: 20, data_source: 'url' }, widthMm, heightMm, columns);
        });
        document.getElementById('dlp-remove-element-btn').addEventListener('click', removeSelected);

        ['dlp-prop-data-source', 'dlp-prop-custom-text', 'dlp-prop-font-size', 'dlp-prop-font-weight',
         'dlp-prop-wrap', 'dlp-prop-align', 'dlp-prop-valign', 'dlp-prop-background'].forEach(id => {
            document.getElementById(id).addEventListener('change', applyPropertyPanelToSelected);
        });

        // GLPI submits the form with a plain HTTP POST (see front/layout.form.php) — no JS
        // fetch involved, so we just keep the hidden field in sync on every grid change,
        // which already happens via the 'change' listener above.
    }

    function addElement(el, widthMm, heightMm, columns) {
        elements.push(el);
        addGridItem(el, widthMm, heightMm, columns);
        syncElementsFromGrid();
    }

    function addGridItem(el, widthMm, heightMm, columns) {
        const w = el.type === 'qrcode' ? el.size : el.width;
        const h = el.type === 'qrcode' ? el.size : el.height;
        const widgetEl = grid.addWidget({
            x: mmToCol(el.x, widthMm, columns),
            y: Math.round(el.y / heightMm * (heightMm * PX_PER_MM / 10)),
            w: mmToCol(w, widthMm, columns),
            h: Math.round(h / heightMm * (heightMm * PX_PER_MM / 10)),
            content: el.type === 'qrcode' ? 'QR' : (el.data_source === 'custom' ? el.custom_text : `{${el.data_source}}`),
        });
        widgetEl._dlpElement = el; // direct object reference, never goes stale (unlike a stored index)
        widgetEl.addEventListener('click', () => selectElement(elements.indexOf(el), widgetEl));
    }

    function selectElement(index, widgetEl) {
        selectedIndex = index;
        selectedWidgetEl = widgetEl;
        const el = elements[index];
        document.getElementById('dlp-property-panel').style.display = 'block';
        document.getElementById('dlp-prop-data-source').value = el.data_source || 'titulo';
        document.getElementById('dlp-prop-custom-text').value = el.custom_text || '';
        document.getElementById('dlp-prop-font-size').value = el.font_size || 10;
        document.getElementById('dlp-prop-font-weight').checked = !!el.font_weight;
        document.getElementById('dlp-prop-wrap').checked = !!el.allow_wrap;
        document.getElementById('dlp-prop-align').value = el.text_align || 'left';
        document.getElementById('dlp-prop-valign').value = el.text_valign || 'top';
        document.getElementById('dlp-prop-background').checked = !!el.has_background;
    }

    function applyPropertyPanelToSelected() {
        if (selectedIndex === null) {
            return;
        }
        const el = elements[selectedIndex];
        el.data_source = document.getElementById('dlp-prop-data-source').value;
        el.custom_text = document.getElementById('dlp-prop-custom-text').value;
        el.font_size = parseInt(document.getElementById('dlp-prop-font-size').value, 10) || 10;
        el.font_weight = document.getElementById('dlp-prop-font-weight').checked;
        el.allow_wrap = document.getElementById('dlp-prop-wrap').checked;
        el.text_align = document.getElementById('dlp-prop-align').value;
        el.text_valign = document.getElementById('dlp-prop-valign').value;
        el.has_background = document.getElementById('dlp-prop-background').checked;
        syncElementsFromGrid();
    }

    function removeSelected() {
        if (selectedIndex === null) {
            return;
        }
        if (selectedWidgetEl) {
            grid.removeWidget(selectedWidgetEl);
        }
        elements.splice(selectedIndex, 1);
        selectedIndex = null;
        selectedWidgetEl = null;
        document.getElementById('dlp-property-panel').style.display = 'none';
        syncElementsFromGrid();
    }

    function syncElementsFromGrid() {
        // Read back x/y/w/h from GridStack nodes into mm, write into `elements`, serialize.
        const container = document.getElementById('dlp-layout-editor');
        const widthMm = parseFloat(container.dataset.widthMm);
        const heightMm = parseFloat(container.dataset.heightMm);
        const columns = grid.getColumn();

        grid.engine.nodes.forEach(node => {
            const index = elements.indexOf(node.el._dlpElement);
            if (index === -1 || !elements[index]) {
                return;
            }
            const el = elements[index];
            el.x = Math.round((node.x / columns) * widthMm * 100) / 100;
            el.y = Math.round((node.y * 10 / PX_PER_MM / heightMm) * heightMm * 100) / 100;
            if (el.type === 'qrcode') {
                el.size = Math.round((node.w / columns) * widthMm * 100) / 100;
            } else {
                el.width = Math.round((node.w / columns) * widthMm * 100) / 100;
                el.height = Math.round((node.h * 10 / PX_PER_MM / heightMm) * heightMm * 100) / 100;
            }
        });

        document.getElementById('dlp-elements-input').value = JSON.stringify(elements);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
