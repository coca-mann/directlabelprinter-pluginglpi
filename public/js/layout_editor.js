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
        // maxRow caps it at that SAME value: 'column' already stops widgets from being
        // dragged/resized past the label's width (GridStack enforces column bounds natively),
        // but there's no equivalent built-in row ceiling — without maxRow, dragging/resizing
        // an element past the bottom edge silently grows the whole grid taller instead of
        // being clamped like the side margins are.
        grid = GridStack.init({
            column: columns,
            cellHeight: 10,
            float: true,
            margin: 0,
            minRow: rows,
            maxRow: rows,
        }, gridEl);

        grid.on('change', syncElementsFromGrid);

        elements = JSON.parse(document.getElementById('dlp-elements-input').value || '[]');
        // batchUpdate defers GridStack's collision/placement recalculation until the whole
        // batch commits, instead of re-running it after every single addWidget() call. Without
        // it, an element saved slightly out of bounds (e.g. from before maxRow existed) could
        // get relocated by GridStack's own collision-avoidance during its own add, and that
        // relocation could in turn collide with and displace an ENTIRELY UNRELATED, validly
        // placed element (e.g. the QR code overflowing the bottom pushing the title down too)
        // — confirmed happening with a real saved layout during testing.
        grid.batchUpdate();
        elements.forEach(el => addGridItem(el, widthMm, heightMm, columns));
        grid.batchUpdate(false);

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
        const rows = Math.round(heightMm * PX_PER_MM / 10);
        const w = el.type === 'qrcode' ? el.size : el.width;
        const h = el.type === 'qrcode' ? el.size : el.height;

        // Clamp to the grid's own bounds before handing off to GridStack. An element saved
        // with x/y/size that no longer fits (e.g. an old QR positioned to extend past the
        // label's edge, from before size limits existed) would otherwise reach GridStack
        // already invalid, and its own bounds-correction is what triggers the collision
        // cascade the batchUpdate() above can't fully prevent on its own.
        let gridW = Math.min(mmToCol(w, widthMm, columns), columns);
        let gridH = Math.min(Math.round(h * PX_PER_MM / 10), rows);
        let gridX = Math.max(0, Math.min(mmToCol(el.x, widthMm, columns), columns - gridW));
        let gridY = Math.max(0, Math.min(Math.round(el.y * PX_PER_MM / 10), rows - gridH));

        const widgetEl = grid.addWidget({
            x: gridX,
            y: gridY,
            w: gridW,
            h: gridH,
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
