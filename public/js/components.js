// Garante que o objeto global não seja sobrescrito se já existir
window.directLabelPrinter = window.directLabelPrinter || {};

(function(dlp) {
    'use strict';

    let currentItemtype = null;
    let currentItems = []; // Pode ser um ou muitos itens
    let layoutOptions = [];
    let defaultLayoutId = null;
    let serverOptions = [];
    let defaultServerId = null;

    // Função para criar o HTML do modal (pode ser melhorado com templates)
    function createModalHtml(layouts, defaultId, servers, defaultServerId) {
        let optionsHtml = '';
        layouts.forEach(layout => {
            const selected = (layout.id == defaultId) ? ' selected' : '';
            // Usamos textContent para evitar XSS nos nomes dos layouts
            const option = document.createElement('option');
            option.value = layout.id;
            option.textContent = layout.name;
            if (selected) {
                option.selected = true;
            }
            optionsHtml += option.outerHTML;
        });

        let serverOptionsHtml = '';
        if ((servers || []).length === 0) {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.disabled = true;
            placeholder.selected = true;
            placeholder.textContent = __('No print servers configured', 'directlabelprinter');
            serverOptionsHtml = placeholder.outerHTML;
        } else {
            (servers || []).forEach(server => {
                const selected = (server.id == defaultServerId) ? ' selected' : '';
                // Usamos textContent para evitar XSS nos nomes dos servidores
                const option = document.createElement('option');
                option.value = server.id;
                option.textContent = server.name;
                if (selected) {
                    option.selected = true;
                }
                serverOptionsHtml += option.outerHTML;
            });
        }

        // Tradução (idealmente, passar traduções do PHP)
        const modalTitle = 'Imprimir Etiqueta(s)'; // __('Imprimir Etiqueta(s)', 'directlabelprinter')
        const layoutLabel = 'Layout'; // __('Layout', 'directlabelprinter')
        const sendButtonText = 'Enviar'; // __('Enviar', 'directlabelprinter')
        const closeButtonText = 'Fechar'; // __('Fechar', 'directlabelprinter')
        const printingText = 'Imprimindo...'; // __('Imprimindo...', 'directlabelprinter')

        return `
            <div id="directlabelprinter-modal" class="ui-dialog-content ui-widget-content" style="display: none; padding: 20px; background-color: white; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.5); z-index: 1001; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); min-width: 350px; max-width: 500px; border-radius: 5px;">
                <h3 class="ui-dialog-titlebar ui-corner-all ui-widget-header" style="padding: 10px; margin: -20px -20px 20px -20px; background-color: #f0f0f0; border-bottom: 1px solid #ccc; cursor: move; border-top-left-radius: 5px; border-top-right-radius: 5px;">${modalTitle}</h3>
                 <button title="Close" class="ui-button ui-corner-all ui-widget ui-button-icon-only ui-dialog-titlebar-close" style="position: absolute; right: 5px; top: 5px;" onclick="window.directLabelPrinter.closeModal()">
                    <span class="ui-button-icon ui-icon ui-icon-closethick"></span><span class="ui-button-icon-space"> </span>Close
                </button>
                <div class="modal-body">
                    <p>
                        <label for="dlp-layout-select">${layoutLabel}:</label><br/>
                        <select id="dlp-layout-select" class="form-control" style="width: 100%;">
                            ${optionsHtml}
                        </select>
                    </p>
                    <p>
                        <label for="dlp-server-select">${__('Print Server', 'directlabelprinter')}:</label><br/>
                        <select id="dlp-server-select" class="form-control" style="width: 100%;">
                            ${serverOptionsHtml}
                        </select>
                    </p>
                    <p>
                        <label><input type="checkbox" id="dlp-remember-server"> ${__('Remember this server', 'directlabelprinter')}</label>
                    </p>
                    <div id="dlp-status-message" style="margin-top: 15px; padding: 10px; border-radius: 4px; display: none;"></div>
                </div>
                <div class="modal-footer" style="margin-top: 20px; text-align: right;">
                    <button id="dlp-send-btn" class="btn btn-primary">${sendButtonText}</button>
                    <button id="dlp-close-btn" class="btn btn-secondary" style="display: none;">${closeButtonText}</button>
                    <span id="dlp-loading" style="display: none; margin-left: 10px;">${printingText}</span>
                </div>
            </div>
            <div id="directlabelprinter-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000;"></div>
        `;
    }

    // Função chamada pelo PHP para iniciar o modal
    dlp.openPrintModal = function(itemtype, items, layouts, defaultId, servers, defaultServerIdArg) {
        currentItemtype = itemtype;
        currentItems = items; // Array de objetos {id: x, name?: y, url?: z}
        layoutOptions = layouts;
        defaultLayoutId = defaultId;
        serverOptions = servers;
        defaultServerId = defaultServerIdArg;

        // Remove modal antigo se existir
        const oldModal = document.getElementById('directlabelprinter-modal');
        const oldOverlay = document.getElementById('directlabelprinter-overlay');
        if (oldModal) oldModal.remove();
        if (oldOverlay) oldOverlay.remove();


        // Cria e adiciona o HTML do modal ao body
        const modalHtml = createModalHtml(layoutOptions, defaultLayoutId, serverOptions, defaultServerId);
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Adiciona listeners aos botões
        document.getElementById('dlp-send-btn').addEventListener('click', handleSendClick);
        document.getElementById('dlp-close-btn').addEventListener('click', dlp.closeModal);

        // Mostra o modal e o overlay
        document.getElementById('directlabelprinter-overlay').style.display = 'block';
        document.getElementById('directlabelprinter-modal').style.display = 'block';

        // Opcional: Tornar o modal arrastável (exemplo simples)
        makeDraggable(document.getElementById('directlabelprinter-modal'));
    };

    // Função para fechar o modal
    dlp.closeModal = function() {
        const modal = document.getElementById('directlabelprinter-modal');
        const overlay = document.getElementById('directlabelprinter-overlay');
        if (modal) modal.style.display = 'none';
        if (overlay) overlay.style.display = 'none';
         // Redireciona de volta para evitar que a "página" da ação fique na tela
         // Isso é útil especialmente para ações em massa
         if (currentItems.length > 0) { // Verifica se há itens para evitar erro
            // Decide para onde redirecionar baseado se é ação em massa ou não
            if (currentItems.length > 1 || window.location.href.includes(currentItemtype.toLowerCase()+".php")) {
                // Se for ação em massa ou se estivermos na página de listagem
                window.location.href = `${CFG_GLPI.root_doc}/front/${currentItemtype.toLowerCase()}.php${getCurrentSearchParameters()}`;
            } else {
                 // Se for ação individual na página do item, apenas recarrega
                 // window.location.reload(); // Ou apenas fecha o modal sem redirecionar
            }
         }
         // Ou simplesmente remover os elementos:
         // if (modal) modal.remove();
         // if (overlay) overlay.remove();
    };

    // Função auxiliar para obter parâmetros de busca atuais (para redirecionamento)
    function getCurrentSearchParameters() {
        // Pega os parâmetros da URL atual que são relevantes para a busca do GLPI
        const params = new URLSearchParams(window.location.search);
        const searchParams = new URLSearchParams();
        // Adicione aqui os parâmetros que o GLPI usa para filtrar/paginar a lista
        // Exemplos comuns: is_deleted, start, sort, order, criteria, metacriteria
        ['is_deleted', 'start', 'sort', 'order'].forEach(p => {
            if (params.has(p)) searchParams.set(p, params.get(p));
        });
        // Para criteria e metacriteria (são mais complexos, podem exigir lógica adicional)
        // Se precisar manter a busca exata, pode ser necessário mais trabalho aqui

        const queryString = searchParams.toString();
        return queryString ? `?${queryString}` : '';
    }

    // Função para exibir mensagens de status no modal
    function displayStatusMessage(message, type = 'info') { // type pode ser 'info', 'success', 'error'
        const statusDiv = document.getElementById('dlp-status-message');
        if (!statusDiv) return;

        statusDiv.textContent = message;
        statusDiv.style.display = 'block';
        statusDiv.style.border = '1px solid';

        if (type === 'success') {
            statusDiv.style.backgroundColor = '#dff0d8';
            statusDiv.style.borderColor = '#d6e9c6';
            statusDiv.style.color = '#3c763d';
        } else if (type === 'error') {
            statusDiv.style.backgroundColor = '#f2dede';
            statusDiv.style.borderColor = '#ebccd1';
            statusDiv.style.color = '#a94442';
        } else { // info
            statusDiv.style.backgroundColor = '#d9edf7';
            statusDiv.style.borderColor = '#bce8f1';
            statusDiv.style.color = '#31708f';
        }
    }

    // Função chamada ao clicar em "Enviar"
    async function handleSendClick() {
        const sendBtn = document.getElementById('dlp-send-btn');
        const closeBtn = document.getElementById('dlp-close-btn');
        const loadingSpan = document.getElementById('dlp-loading');
        const layoutSelect = document.getElementById('dlp-layout-select');
        const serverSelect = document.getElementById('dlp-server-select');
        const rememberCheckbox = document.getElementById('dlp-remember-server');

        sendBtn.disabled = true;
        loadingSpan.style.display = 'inline';
        displayStatusMessage('', 'info');

        try {
            const response = await fetch(`${CFG_GLPI.root_doc}/plugins/directlabelprinter/ajax/print.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': getAjaxCsrfToken()
                },
                body: JSON.stringify({
                    itemtype: currentItemtype,
                    items: currentItems.map(item => ({ id: item.id })),
                    layout_id: layoutSelect.value ? parseInt(layoutSelect.value, 10) : null,
                    printserver_id: serverSelect.value ? parseInt(serverSelect.value, 10) : null,
                    remember_server: rememberCheckbox.checked
                })
            });

            const responseData = await response.json();

            if (!response.ok || !responseData.success) {
                throw new Error(responseData.message || `HTTP error! status: ${response.status}`);
            }

            displayStatusMessage(responseData.message, 'success');
        } catch (error) {
            console.error('Erro ao chamar a API de impressão:', error);
            displayStatusMessage(`Erro ao imprimir: ${error.message}`, 'error');
        } finally {
            loadingSpan.style.display = 'none';
            sendBtn.style.display = 'none';
            closeBtn.style.display = 'inline';
        }
    }

    // --- Funções Auxiliares ---

    // Função simples para tornar um elemento arrastável
    function makeDraggable(element) {
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
        const header = element.querySelector(".ui-dialog-titlebar"); // Arrastar pelo header

        if (header) {
            header.onmousedown = dragMouseDown;
        } else {
            element.onmousedown = dragMouseDown; // Fallback: arrastar pelo modal inteiro
        }

        function dragMouseDown(e) {
            e = e || window.event;
            e.preventDefault();
            // Posição inicial do mouse
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = closeDragElement;
            // Chama a função sempre que o mouse se move
            document.onmousemove = elementDrag;
        }

        function elementDrag(e) {
            e = e || window.event;
            e.preventDefault();
            // Calcula nova posição do cursor
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;
            // Define a nova posição do elemento
            element.style.top = (element.offsetTop - pos2) + "px";
            element.style.left = (element.offsetLeft - pos1) + "px";
        }

        function closeDragElement() {
            // Para de mover quando o botão do mouse é solto
            document.onmouseup = null;
            document.onmousemove = null;
        }
    }

    // --- NOVAS FUNÇÕES ---
    // Handlers para os botões "Testar Conexão" / "Buscar Impressoras" do formulário PrintServer
    async function handleTestPrintServerClick(event) {
        const btn = event.currentTarget;
        const statusSpan = document.getElementById('dlp-test-connection-status');
        btn.disabled = true;
        statusSpan.textContent = __('Testing...', 'directlabelprinter');

        try {
            const response = await fetch(`${CFG_GLPI.root_doc}/plugins/directlabelprinter/ajax/printserver_test.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': getAjaxCsrfToken()
                },
                body: `id=${encodeURIComponent(btn.dataset.id)}`
            });
            const result = await response.json();
            statusSpan.textContent = result.message;
            statusSpan.style.color = result.success ? 'green' : 'red';
        } catch (error) {
            statusSpan.textContent = `Erro: ${error.message}`;
            statusSpan.style.color = 'red';
        } finally {
            btn.disabled = false;
        }
    }

    async function handleFetchPrintersClick(event) {
        const btn = event.currentTarget;
        const statusSpan = document.getElementById('dlp-printer-fetch-status');
        btn.disabled = true;
        statusSpan.textContent = __('Fetching...', 'directlabelprinter');

        try {
            const response = await fetch(`${CFG_GLPI.root_doc}/plugins/directlabelprinter/ajax/printserver_fetch_printers.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': getAjaxCsrfToken()
                },
                body: `id=${encodeURIComponent(btn.dataset.id)}`
            });
            const result = await response.json();
            if (!result.success) {
                statusSpan.textContent = result.message;
                statusSpan.style.color = 'red';
                return;
            }
            const names = result.printers.map(p => p.nome).join(', ') || __('No printers found.', 'directlabelprinter');
            statusSpan.textContent = names;
            statusSpan.style.color = 'green';
        } catch (error) {
            statusSpan.textContent = `Erro: ${error.message}`;
            statusSpan.style.color = 'red';
        } finally {
            btn.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const testPsBtn = document.getElementById('dlp-test-connection-btn');
        if (testPsBtn) {
            testPsBtn.addEventListener('click', handleTestPrintServerClick);
        }
        const fetchPrintersBtn = document.getElementById('dlp-fetch-printers-btn');
        if (fetchPrintersBtn) {
            fetchPrintersBtn.addEventListener('click', handleFetchPrintersClick);
        }
    });


})(window.directLabelPrinter);