// Garante que o objeto global não seja sobrescrito se já existir
window.directLabelPrinter = window.directLabelPrinter || {};

(function(dlp) {
    'use strict';

    let currentItemtype = null;
    let currentItems = []; // Pode ser um ou muitos itens

    // Chamado pelo PHP (DirectLabelPrinterActions::showMassiveActionsSubForm) depois que os
    // campos (layout/servidor/checkbox/botão) já foram renderizados como HTML real dentro do
    // modal nativo de ação em massa do GLPI. Não construímos mais um modal próprio aqui — GLPI
    // já abre e gerencia esse modal; criar um segundo resultava em dois modais sobrepostos.
    dlp.initInlinePrintForm = function(itemtype, items) {
        currentItemtype = itemtype;
        currentItems = items; // Array de objetos {id: x, titulo?: y, url?: z, ref?: w}

        const sendBtn = document.getElementById('dlp-send-btn');
        if (sendBtn) {
            sendBtn.addEventListener('click', handleSendClick);
        }
    };

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
        const layoutSelect = document.getElementById('dlp-layout-select');
        const serverSelect = document.getElementById('dlp-server-select');
        const rememberCheckbox = document.getElementById('dlp-remember-server');

        sendBtn.disabled = true;
        sendBtn.textContent = __('Enviando...', 'directlabelprinter');
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
            sendBtn.style.display = 'none';
        } catch (error) {
            console.error('Erro ao chamar a API de impressão:', error);
            displayStatusMessage(`Erro ao imprimir: ${error.message}`, 'error');
            sendBtn.disabled = false;
            sendBtn.textContent = __('Enviar', 'directlabelprinter');
        }
    }

    // --- Funções Auxiliares ---

    // --- NOVAS FUNÇÕES ---
    // Handlers para os botões "Testar Conexão" / "Buscar Impressoras" do formulário PrintServer.
    // Lêem url/chave DIRETO dos campos do formulário (não do banco) para funcionar mesmo antes
    // de salvar o registro — só o id (quando existe) vai junto, como fallback no backend para
    // a chave de API quando o campo de senha está vazio (= "manter a chave salva").
    function getPrintServerFormCredentials() {
        const urlInput = document.getElementById('dlp-url-input');
        const apiKeyInput = document.getElementById('dlp-api-key-input');
        return {
            url: urlInput ? urlInput.value : '',
            api_key_plain: apiKeyInput ? apiKeyInput.value : '',
        };
    }

    async function handleTestPrintServerClick(event) {
        const btn = event.currentTarget;
        const statusSpan = document.getElementById('dlp-test-connection-status');
        const { url, api_key_plain } = getPrintServerFormCredentials();
        btn.disabled = true;
        statusSpan.textContent = __('Testando...', 'directlabelprinter');
        statusSpan.style.color = '';

        try {
            const response = await fetch(`${CFG_GLPI.root_doc}/plugins/directlabelprinter/ajax/printserver_test.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': getAjaxCsrfToken()
                },
                body: `id=${encodeURIComponent(btn.dataset.id)}&url=${encodeURIComponent(url)}&api_key_plain=${encodeURIComponent(api_key_plain)}`
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
        const select = document.getElementById('dlp-default-printer-select');
        const { url, api_key_plain } = getPrintServerFormCredentials();
        btn.disabled = true;
        statusSpan.textContent = __('Buscando...', 'directlabelprinter');
        statusSpan.style.color = '';

        try {
            const response = await fetch(`${CFG_GLPI.root_doc}/plugins/directlabelprinter/ajax/printserver_fetch_printers.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': getAjaxCsrfToken()
                },
                body: `id=${encodeURIComponent(btn.dataset.id)}&url=${encodeURIComponent(url)}&api_key_plain=${encodeURIComponent(api_key_plain)}`
            });
            const result = await response.json();
            if (!result.success) {
                statusSpan.textContent = result.message;
                statusSpan.style.color = 'red';
                return;
            }
            if (select) {
                // Preserva a impressora padrão já configurada quando ela não aparece na busca
                // (lista vazia, ou a impressora foi renomeada/removida no servidor de impressão)
                // — sem isso, nenhuma <option> ficava 'selected' e o navegador selecionava a
                // primeira da lista automaticamente, apagando/trocando o valor salvo assim que
                // o formulário fosse salvo, mesmo sem o usuário escolher nada explicitamente.
                const currentValue = select.value;
                select.innerHTML = '';
                let matched = false;
                result.printers.forEach(function(printer) {
                    const opt = document.createElement('option');
                    opt.value = printer.nome;
                    opt.textContent = printer.nome;
                    if (printer.nome === currentValue) {
                        opt.selected = true;
                        matched = true;
                    }
                    select.appendChild(opt);
                });
                if (!matched && currentValue) {
                    const keep = document.createElement('option');
                    keep.value = currentValue;
                    keep.textContent = currentValue + ' ' + __('(não encontrada na busca atual)', 'directlabelprinter');
                    keep.selected = true;
                    select.insertBefore(keep, select.firstChild);
                }
                if (result.printers.length === 0 && !currentValue) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = __('Nenhuma impressora encontrada.', 'directlabelprinter');
                    opt.selected = true;
                    select.appendChild(opt);
                }
            }
            statusSpan.textContent = __('Impressoras atualizadas.', 'directlabelprinter');
            statusSpan.style.color = 'green';
        } catch (error) {
            statusSpan.textContent = `Erro: ${error.message}`;
            statusSpan.style.color = 'red';
        } finally {
            btn.disabled = false;
        }
    }

    // Delegação de eventos no document: o formulário do PrintServer é carregado via AJAX
    // (aba do GLPI 11, ajax/common.tabs.php) DEPOIS de 'DOMContentLoaded' já ter disparado,
    // então um addEventListener direto nos botões (dentro do listener de DOMContentLoaded)
    // nunca era anexado — os botões ficavam mudos, sem erro nenhum no console.
    document.addEventListener('click', function(event) {
        const testBtn = event.target.closest('#dlp-test-connection-btn');
        if (testBtn) {
            handleTestPrintServerClick({ currentTarget: testBtn });
            return;
        }
        const fetchBtn = event.target.closest('#dlp-fetch-printers-btn');
        if (fetchBtn) {
            handleFetchPrintersClick({ currentTarget: fetchBtn });
        }
    });


})(window.directLabelPrinter);