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
        const rows = Math.round(heightMm * PX_PER_MM / 10); // 10px-tall rows

        const gridEl = document.getElementById('dlp-grid');
        gridEl.style.width = (widthMm * PX_PER_MM) + 'px';

        // GridStack.init() replaces the element's inline style with its own (width/columns
        // via CSS custom properties) and sizes height from CONTENT — number of rows actually
        // occupied by widgets, times cellHeight. On a brand new layout (zero elements) that's
        // 0, so the grid collapsed to an invisible 0px-tall box. minRow forces it to always
        // reserve the label's full height, matching the canvas size regardless of content.
        //
        // maxRow is deliberately NOT set here. GridStack.addWidget()/moveNode() always run
        // collision avoidance against it, relocating a widget away from wherever an
        // already-placed widget sits — there's no reliable "just put it exactly here" escape
        // hatch (moveNode's forceCollide + update() were tried and, empirically, still let
        // GridStack's own collision search win). Since this editor intentionally allows
        // elements to overlap (the label author's call, e.g. text over a background box),
        // loading saved elements at their exact stored x/y/w/h — unclamped, maxRow-free — is
        // the only way that doesn't risk moving an unrelated, validly-placed element as a
        // side effect (confirmed happening with a real saved layout: an oversized QR pushed
        // the title to the bottom margin on every reload). maxRow is applied further down,
        // AFTER the initial load, so it only constrains interactive drag/resize going forward.
        grid = GridStack.init({
            column: columns,
            cellHeight: 10,
            float: true,
            margin: 0,
            minRow: rows,
        }, gridEl);

        grid.on('change', syncElementsFromGrid);

        elements = JSON.parse(document.getElementById('dlp-elements-input').value || '[]');
        elements.forEach(el => addGridItem(el, widthMm, heightMm, columns));

        // Now that every saved element sits exactly where it was saved, cap future
        // interactive drags/resizes at the label's bottom edge — 'column' already does the
        // equivalent for width natively.
        grid.opts.maxRow = rows;
        grid.engine.maxRow = rows;

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

        initFontUploadToggle();
    }

    // O upload de fonte customizada só faz sentido quando 'Personalizada' está selecionada
    // no dropdown de Fonte — escondido/mostrado conforme o valor atual, tanto no carregamento
    // quanto a cada troca.
    function initFontUploadToggle() {
        const fontSelect = document.querySelector('#dlp-font-choice-cell select[name="font_choice"]');
        const uploadCells = document.querySelectorAll('.dlp-font-upload-cell');
        if (!fontSelect || uploadCells.length === 0) {
            return;
        }
        const toggle = () => {
            const show = fontSelect.value === 'custom';
            uploadCells.forEach(cell => {
                cell.style.display = show ? '' : 'none';
            });
        };
        fontSelect.addEventListener('change', toggle);
        toggle();
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
            y: Math.round(el.y * PX_PER_MM / 10),
            w: mmToCol(w, widthMm, columns),
            h: Math.round(h * PX_PER_MM / 10),
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

    // Este script é injetado via AJAX (aba do GLPI 11, ajax/common.tabs.php) DEPOIS de
    // 'DOMContentLoaded' já ter disparado — esperar por esse evento aqui significa que ele
    // nunca chega a rodar. O <div id="dlp-layout-editor"> já existe no DOM neste ponto
    // (jQuery só executa scripts injetados depois de inserir o HTML completo).
    //
    // MAS: o <script src="gridstack.min.js"> e este arquivo são carregados como duas tags
    // <script> externas separadas na mesma leva de HTML injetado — jQuery busca e avalia
    // scripts externos via AJAX assíncrono (jQuery._evalUrl), sem garantir que um termine
    // antes do outro começar. Sem essa espera, GridStack podia ainda não existir quando
    // este arquivo rodava, e GridStack.init() falhava em silêncio (nada aparecia, sem erro
    // visível se o console não fosse checado no momento certo).
    function waitForGridStackThenInit() {
        if (typeof GridStack === 'undefined') {
            setTimeout(waitForGridStackThenInit, 20);
            return;
        }
        init();
    }
    waitForGridStackThenInit();
})();
