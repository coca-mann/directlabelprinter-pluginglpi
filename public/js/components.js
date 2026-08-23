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
        sendBtn.textContent = __('Sending...', 'directlabelprinter');
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
            sendBtn.textContent = __('Send', 'directlabelprinter');
        }
    }

    // --- Funções Auxiliares ---

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