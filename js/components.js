// Garante que o objeto global não seja sobrescrito se já existir
window.directLabelPrinter = window.directLabelPrinter || {};

(function(dlp) {
    'use strict';

    let currentItemtype = null;
    let currentItems = []; // Pode ser um ou muitos itens
    let layoutOptions = [];
    let defaultLayoutId = null;
    let apiUrl = '';
    let apiToken = '';

    // Função para criar o HTML do modal (pode ser melhorado com templates)
    function createModalHtml(layouts, defaultId) {
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

    // Função para buscar a configuração da API (URL e Token) do backend
    async function fetchApiConfig() {
        // Criar um endpoint AJAX seguro no plugin para retornar a config
        // Exemplo: /plugins/directlabelprinter/ajax/apiconfig.php
        const ajaxUrl = `${CFG_GLPI.root_doc}/plugins/directlabelprinter/ajax/apiconfig.php`;

        try {
            // Usando fetch API moderna
            const response = await fetch(ajaxUrl, {
                method: 'GET', // Ou POST se precisar enviar dados/tokens
                headers: {
                    'Content-Type': 'application/json',
                    // Incluir headers de sessão/CSRF se necessário para o endpoint apiconfig.php
                    'Session-Token': CFG_GLPI.glpi_token // Exemplo, verifique se está disponível
                    // 'X-CSRF-Token': CFG_GLPI.csrf_token // Exemplo, verifique se está disponível
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const config = await response.json();

            if (config.error) {
                throw new Error(config.error);
            }

            if (!config.api_url || !config.access_token) {
                 throw new Error('Configuração da API incompleta retornada pelo servidor.');
            }

            apiUrl = config.api_url;
            apiToken = config.access_token;
            return true;

        } catch (error) {
            console.error('Erro ao buscar configuração da API:', error);
            displayStatusMessage(`Erro ao buscar configuração da API: ${error.message}`, 'error');
            return false;
        }
    }


    // Função chamada pelo PHP para iniciar o modal
    dlp.openPrintModal = function(itemtype, items, layouts, defaultId) {
        currentItemtype = itemtype;
        currentItems = items; // Array de objetos {id: x, name?: y, url?: z}
        layoutOptions = layouts;
        defaultLayoutId = defaultId;

        // Remove modal antigo se existir
        const oldModal = document.getElementById('directlabelprinter-modal');
        const oldOverlay = document.getElementById('directlabelprinter-overlay');
        if (oldModal) oldModal.remove();
        if (oldOverlay) oldOverlay.remove();


        // Cria e adiciona o HTML do modal ao body
        const modalHtml = createModalHtml(layoutOptions, defaultLayoutId);
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

        sendBtn.disabled = true;
        loadingSpan.style.display = 'inline';
        displayStatusMessage('', 'info'); // Limpa mensagens anteriores

        const selectedLayoutId = layoutSelect.value;

        // 1. Buscar configuração da API (URL e Token)
        const configOk = await fetchApiConfig();
        if (!configOk) {
            sendBtn.disabled = false;
            loadingSpan.style.display = 'none';
            // Mensagem de erro já exibida por fetchApiConfig
            return;
        }

        // 2. Preparar payload para a API externa
        let apiPayload = [];
        let itemsToProcess = currentItems; // Array de {id: x, name?: y, url?: z}

        // Simplificação: Assumimos que o PHP já passou 'name' e 'url' no array 'items'
        // Se não passou, precisaríamos de outro AJAX call ao GLPI aqui para buscar esses dados
        if (itemsToProcess && itemsToProcess.length > 0) {
            itemsToProcess.forEach(item => {
                 // Verifica se temos os dados mínimos
                 if (item.id && item.name && item.url) {
                    apiPayload.push({
                        titulo: item.name, // Nome do ativo
                        url: item.url,     // URL do ativo no GLPI
                        layout_id: parseInt(selectedLayoutId) // ID do layout selecionado
                    });
                 } else {
                     console.warn("Item sem dados suficientes para impressão:", item);
                     // Poderia mostrar uma mensagem parcial de erro
                 }
            });
        }

        if (apiPayload.length === 0) {
             displayStatusMessage('Nenhum item válido para processar.', 'error');
             sendBtn.disabled = false;
             loadingSpan.style.display = 'none';
             closeBtn.style.display = 'inline'; // Mostra botão fechar
             return;
        }


        // 3. Chamar a API externa /imprimir/
        try {
            const printApiUrl = `${apiUrl.replace(/\/$/, '')}/imprimir/`; // Garante que não haja //
            const response = await fetch(printApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${apiToken}` // Envia o token
                },
                body: JSON.stringify(apiPayload) // Envia o payload como JSON
            });

            // Processa a resposta da API externa
            const responseData = await response.json(); // Ou response.text() se não for JSON

            if (!response.ok) {
                 // Tenta pegar mensagem de erro do corpo da resposta, se houver
                 const errorMsg = responseData?.detail || responseData?.message || `HTTP error! status: ${response.status}`;
                 throw new Error(errorMsg);
            }

            // Sucesso!
            displayStatusMessage(responseData.message || 'Etiqueta(s) enviada(s) para impressão com sucesso!', 'success'); // Exibe mensagem de sucesso da API

        } catch (error) {
            console.error('Erro ao chamar a API de impressão:', error);
            displayStatusMessage(`Erro ao imprimir: ${error.message}`, 'error');
        } finally {
            // Limpeza da interface do modal após sucesso ou erro
            loadingSpan.style.display = 'none';
            sendBtn.style.display = 'none'; // Esconde botão enviar
            closeBtn.style.display = 'inline'; // Mostra botão fechar
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

    // Adicionar listeners aos botões da PÁGINA DE CONFIGURAÇÃO quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        const testBtn = document.getElementById('test_connection_btn');
        if (testBtn) { // Verifica se o botão existe na página atual (página de config)
            testBtn.addEventListener('click', handleTestConnectionClick);
        }

        const fetchBtn = document.getElementById('fetch_layouts_btn');
        if (fetchBtn) { // Verifica se o botão existe
            fetchBtn.addEventListener('click', handleFetchLayoutsClick);
        }

        // Nota: O listener para 'dlp-send-btn' é adicionado dentro de openPrintModal
        // porque o botão só existe depois que o modal é criado dinamicamente.
    });

    // --- NOVA FUNÇÃO ---
    // Função para o botão Testar Conexão na página de configuração
    async function handleTestConnectionClick() {
        const statusSpan = document.getElementById('connection_status');
        const apiUrlInput = document.querySelector('input[name="api_url"]');
        const apiUserInput = document.querySelector('input[name="api_user"]');
        const apiPasswordInput = document.querySelector('input[name="api_password"]');
        const testBtn = document.getElementById('test_connection_btn');

        if (!apiUrlInput || !apiUserInput || !apiPasswordInput || !statusSpan || !testBtn) {
            console.error('Elementos do formulário de autenticação não encontrados.');
            return;
        }

        const apiUrl = apiUrlInput.value.trim();
        const apiUser = apiUserInput.value.trim();
        const apiPassword = apiPasswordInput.value;

        if (!apiUrl || !apiUser || !apiPassword) {
            statusSpan.textContent = __('Preencha URL, Usuário e Senha.', 'directlabelprinter'); // Idealmente usar gettext JS
            statusSpan.style.color = 'red';
            return;
        }

        statusSpan.textContent = __('Testando...', 'directlabelprinter'); // Idealmente usar gettext JS
        statusSpan.style.color = 'orange';
        testBtn.disabled = true;

        const ajaxUrl = `${CFG_GLPI.root_doc}/plugins/directlabelprinter/ajax/test_connection.php`;
        const csrfToken = document.querySelector('input[name="_glpi_csrf_token"]')?.value || '';

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Glpi-Csrf-Token': csrfToken // Envia token CSRF
                },
                body: JSON.stringify({
                    config: { // Envia os dados dentro de uma chave 'config'
                        api_url: apiUrl,
                        api_user: apiUser,
                        api_password: apiPassword
                    }
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || `Erro HTTP ${response.status}`);
            }

            // Sucesso
            statusSpan.textContent = result.message;
            statusSpan.style.color = 'green';
            apiPasswordInput.value = ''; // Limpa campo de senha

        } catch (error) {
            console.error('Erro no teste de conexão:', error);
            statusSpan.textContent = `Erro: ${error.message}`;
            statusSpan.style.color = 'red';
        } finally {
            testBtn.disabled = false;
        }
    }

    // --- NOVA FUNÇÃO ---
    // Função para o botão Buscar Layouts na página de configuração
    async function handleFetchLayoutsClick() {
        const statusSpan = document.getElementById('fetch_status');
        const fetchBtn = document.getElementById('fetch_layouts_btn');

        if (!statusSpan || !fetchBtn) {
            console.error('Elementos de busca de layout não encontrados.');
            return;
        }

        statusSpan.textContent = __('Buscando...', 'directlabelprinter'); // Idealmente usar gettext JS
        statusSpan.style.color = 'orange';
        fetchBtn.disabled = true;

        const ajaxUrl = `${CFG_GLPI.root_doc}/plugins/directlabelprinter/ajax/fetch_layouts.php`;
        const csrfToken = document.querySelector('input[name="_glpi_csrf_token"]')?.value || '';

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Glpi-Csrf-Token': csrfToken
                }
                // Não precisa de body para este endpoint
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || `Erro HTTP ${response.status}`);
            }

            // Sucesso
            statusSpan.textContent = result.message;
            statusSpan.style.color = 'green';

            // Recarregar a página para mostrar os layouts
            alert(__('Layouts buscados com sucesso! A página será recarregada.', 'directlabelprinter')); // Idealmente usar gettext JS
            window.location.reload();

        } catch (error) {
            console.error('Erro ao buscar layouts:', error);
            statusSpan.textContent = `Erro: ${error.message}`;
            statusSpan.style.color = 'red';
        } finally {
            fetchBtn.disabled = false;
        }
    }


})(window.directLabelPrinter);