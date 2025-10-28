<?php
// plugins/directlabelprinter/front/config.php

include ("../../../inc/includes.php"); // Inclui o GLPI Core

// --- Namespaces Usados ---
use Glpi\Toolbox\Sanitizer;
use GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions;
use Html;
use Session;
use Toolbox;
use Plugin;
use DateTime;
use Config as CoreConfig; // Para addMessageAfterRedirect

// --- Verificação de Permissões ---
Session::checkLoginUser();
if (!Session::haveRight('config', READ)) {
    Html::displayRightError();
}
$can_edit = Session::haveRight('config', UPDATE);

// --- Variáveis Globais e Constantes ---
global $DB;
$auth_table = 'glpi_plugin_directlabelprinter_auth';
$layouts_table = 'glpi_plugin_directlabelprinter_layouts';
$config_page_url = Plugin::getWebDir('directlabelprinter', true) . "/front/config.php";
$csrf_token_name = 'plugin_directlabelprinter_config_form'; // Nome consistente

// --- Lógica de Processamento POST ---
if ($can_edit && !empty($_POST)) {
    // Verificar CSRF Token (usar a variável já definida globalmente)

    // ---> LOG ANTES DA VERIFICAÇÃO <---
    $received_token = $_POST['_glpi_csrf_token'] ?? 'NÃO RECEBIDO'; // Pega o token recebido ou 'NÃO RECEBIDO'
    // Tenta obter o token esperado da sessão (usando método interno, pode não ser público/estável)
    // $expected_token = $_SESSION['glpi_csrf_token'][$csrf_token_name] ?? 'NÃO ENCONTRADO NA SESSÃO';
    Toolbox::logInFile("debug", "[Config Page POST] Tentando verificar CSRF. Nome: $csrf_token_name");
    Toolbox::logInFile("debug", "[Config Page POST] Token Recebido via POST: " . $received_token);
    Toolbox::logInFile("debug", "[Config Page POST] Todos os campos POST recebidos: " . json_encode(array_keys($_POST)));
    // Toolbox::logInFile("debug", "[Config Page POST] Token Esperado na Sessão: " . $expected_token); // Opcional, mais complexo
    // ---> FIM DO LOG <---

    try { // Coloca a verificação dentro do try/catch também
        Session::checkCSRF($csrf_token_name, $_POST['_glpi_csrf_token']); // Linha ~34
        Toolbox::logInFile("debug", "[Config Page POST] Verificação CSRF OK."); // Log se passou
        // --- AÇÃO: Testar Conexão ---
        if (isset($_POST['test_connection'])) {
            Toolbox::logInFile("debug", "[Config Page] Test Connection POST received.");

            // Obter dados do POST
            $api_url = rtrim(Sanitizer::getPostVariable('api_url'), '/');
            $username = Sanitizer::getPostVariable('api_user');
            $password = Sanitizer::getPostVariable('api_password'); // Senha vem direto do POST

            if (empty($api_url) || empty($username) || empty($password)) {
                throw new \Exception(__('Preencha URL, Usuário e Senha.', 'directlabelprinter'));
            }
            $token_endpoint = $api_url . '/api/auth/token/';
            Toolbox::logInFile("debug", "[Config Page] Calling cURL to: " . $token_endpoint);

            // Chamada cURL
            $ch = curl_init($token_endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $username, 'password' => $password]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para teste SSL

            $api_response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Processar resposta cURL
            if ($curl_error) {
                 Toolbox::logInFile("error", "[Config Page] Test Connection cURL Error: " . $curl_error);
                 throw new \Exception("Erro cURL: " . $curl_error);
            }
            Toolbox::logInFile("debug", "[Config Page] Test Connection HTTP Code: " . $http_code);
            Toolbox::logInFile("debug", "[Config Page] Test Connection Raw Response: " . substr($api_response_body, 0, 500)); // Logar início da resposta

            if ($http_code != 200) {
                $error_details = json_decode($api_response_body, true);
                $error_message = $error_details['detail'] ?? "Erro API ($http_code)";
                 Toolbox::logInFile("error", "[Config Page] Test Connection API Error: " . $error_message);
                throw new \Exception($error_message);
            }
            $tokens = json_decode($api_response_body, true);
            if (empty($tokens['access']) || empty($tokens['refresh'])) {
                 Toolbox::logInFile("error", "[Config Page] Test Connection Error: Tokens not found in response.");
                throw new \Exception('Tokens não encontrados na resposta da API.');
            }

            // Calcular expiração e preparar dados
            $expires_datetime = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');
            $data_to_save = [
                'user'                 => $username,
                'api_url'              => $api_url,
                'access_token'         => $tokens['access'],
                'refresh_token'        => $tokens['refresh'],
                'access_token_expires' => $expires_datetime
            ];

            // Salvar/Atualizar na tabela _auth
            $existing_result = $DB->request(['FROM' => $auth_table, 'LIMIT' => 1]);
            $existing = $existing_result->current(); // Corrigido aqui também
            if (empty($existing)) {
                $DB->insert($auth_table, $data_to_save);
                Toolbox::logInFile("info", "[Config Page] Test Connection: Inserted auth data for user " . $username);
            } else {
                $DB->update($auth_table, $data_to_save, ['id' => $existing['id']]);
                Toolbox::logInFile("info", "[Config Page] Test Connection: Updated auth data for user " . $username);
            }
            Session::addMessageAfterRedirect(__('Autenticação bem-sucedida! Tokens salvos.', 'directlabelprinter'), false, INFO);

        // --- AÇÃO: Buscar Layouts ---
        } else if (isset($_POST['fetch_layouts'])) {
            Toolbox::logInFile("debug", "[Config Page] Fetch Layouts POST received.");

            // Verificar se a classe/método existe
            if (!class_exists(DirectLabelPrinterActions::class) || !method_exists(DirectLabelPrinterActions::class, 'makeAuthenticatedApiRequest')) {
                 Toolbox::logInFile("error", "[Config Page] Fetch Layouts Error: DirectLabelPrinterActions::makeAuthenticatedApiRequest not found.");
                 throw new \Exception("Erro interno: Função de requisição API não encontrada.");
            }

            // Chamar API via função auxiliar
            $layouts_from_api = DirectLabelPrinterActions::makeAuthenticatedApiRequest('GET', '/api/layouts/');
            if (!is_array($layouts_from_api)) {
                 Toolbox::logInFile("error", "[Config Page] Fetch Layouts Error: Invalid API response.");
                 throw new \Exception("Resposta inesperada da API de layouts.");
            }
            Toolbox::logInFile("debug", "[Config Page] Fetch Layouts: Received " . count($layouts_from_api) . " layouts from API.");

            // Limpar tabela e inserir novos
            $DB->query("TRUNCATE TABLE `$layouts_table`");
            $inserted_count = 0;
            foreach ($layouts_from_api as $layout_data) {
                 // Mapear dados da API para colunas do BD
                 $data_to_insert = [
                     'id_api'                  => $layout_data['id'] ?? null,
                     'nome'                    => $layout_data['nome'] ?? null,
                     'descricao'               => $layout_data['descricao'] ?? null,
                     'largura_mm'              => $layout_data['largura_mm'] ?? null,
                     'altura_mm'               => $layout_data['altura_mm'] ?? null,
                     'altura_titulo_mm'        => $layout_data['altura_titulo_mm'] ?? null,
                     'tamanho_fonte_titulo'    => $layout_data['tamanho_fonte_titulo'] ?? null,
                     'margem_vertical_qr_mm'   => $layout_data['margem_vertical_qr_mm'] ?? null,
                     'nome_fonte'              => $layout_data['nome_fonte_reportlab'] ?? basename($layout_data['arquivo_fonte'] ?? ''),
                     'padrao'                  => ($layout_data['padrao'] ?? false) ? 1 : 0
                 ];
                 if (!empty($data_to_insert['id_api'])) {
                     $DB->insert($layouts_table, $data_to_insert);
                     $inserted_count++;
                 } else {
                      Toolbox::logInFile("warning", "[Config Page] Fetch Layouts: Skipped layout with missing API ID: " . json_encode($layout_data));
                 }
            }
            Toolbox::logInFile("info", "[Config Page] Fetch Layouts: Inserted " . $inserted_count . " layouts into DB.");
            Session::addMessageAfterRedirect(__('Layouts buscados e atualizados!', 'directlabelprinter'), false, INFO);

        // --- AÇÃO: Atualizar Layout Padrão ---
        } else if (isset($_POST['update_defaults'])) {
            Toolbox::logInFile("debug", "[Config Page] Update Defaults POST received.");

            $selected_default_id_api = Sanitizer::getPostVariable('default_layout_id_api');
            Toolbox::logInFile("debug", "[Config Page] Update Defaults: Selected API ID: " . ($selected_default_id_api ?? 'NONE'));

            // Resetar todos para não padrão no DB
            $DB->update($layouts_table, ['padrao' => 0], ['padrao' => 1]);
            Toolbox::logInFile("debug", "[Config Page] Update Defaults: Reset previous default in DB.");

            // Definir o novo padrão no DB e chamar API
            if ($selected_default_id_api !== null && $selected_default_id_api !== '') {
                 $DB->update($layouts_table, ['padrao' => 1], ['id_api' => $selected_default_id_api]);
                 Toolbox::logInFile("info", "[Config Page] Update Defaults: Set new default in DB (API ID: " . $selected_default_id_api . ").");

                 // Chamar API para definir padrão lá também (verificar classe/método)
                 if (class_exists(DirectLabelPrinterActions::class) && method_exists(DirectLabelPrinterActions::class, 'makeAuthenticatedApiRequest')) {
                    try {
                        Toolbox::logInFile("debug", "[Config Page] Update Defaults: Calling API to set default...");
                        DirectLabelPrinterActions::makeAuthenticatedApiRequest('POST', "/api/layouts/{$selected_default_id_api}/selecionar-padrao/");
                        Toolbox::logInFile("info", "[Config Page] Update Defaults: API call to set default successful.");
                    } catch (\Exception $api_error) {
                         // Logar o erro da API mas não impedir a mensagem de sucesso do GLPI,
                         // pois o padrão foi salvo localmente. Opcionalmente, mostrar um aviso.
                         Toolbox::logInFile("error", "[Config Page] Update Defaults: API call failed: " . $api_error->getMessage());
                         Session::addMessageAfterRedirect(__('Aviso: Layout padrão salvo localmente, mas falha ao definir na API: ', 'directlabelprinter') . $api_error->getMessage(), true, WARNING);
                    }
                 } else {
                     Toolbox::logWarning("[Config Page] Update Defaults: makeAuthenticatedApiRequest not found to update API.");
                 }
            } else {
                 Toolbox::logInFile("info", "[Config Page] Update Defaults: No default layout selected.");
            }
            // Mensagem de sucesso principal (salvo localmente)
            Session::addMessageAfterRedirect(__('Layout padrão atualizado!', 'directlabelprinter'), false, INFO);
        } else {
             // Nenhuma ação POST conhecida foi acionada
             Toolbox::logInFile("warning", "[Config Page] Unknown POST action attempted.");
             Session::addMessageAfterRedirect(__('Ação desconhecida.'), true, WARNING);
        }

    } catch (\Exception $e) {
        // Captura exceções de qualquer ação POST
        // Log específico para falha CSRF
        if (strpos($e->getMessage(), 'CSRF') !== false || $e instanceof \Glpi\CsrfException) {
             Toolbox::logInFile("error", "[Config Page POST] ERRO CSRF: " . $e->getMessage());
        } else {
             Toolbox::logInFile("error", "[Config Page POST] Erro Geral POST: " . $e->getMessage());
        }
        Session::addMessageAfterRedirect(__('Erro: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
        // Html::redirect($config_page_url); // Redireciona mesmo em erro
    }

    // Redirecionar em TODOS os casos POST para limpar o POST e mostrar mensagens
    // Se foi erro CSRF, checkCSRF já pode ter interrompido e redirecionado.
    if (isset($e) && !($e instanceof \Glpi\CsrfException) && strpos($e->getMessage(), 'CSRF') === false) {
       // Se o erro NÃO foi CSRF, talvez não redirecionar para ver o erro?
       // Mas geralmente redirecionamos para mostrar a mensagem de erro da sessão.
       Html::redirect($config_page_url);
    } else if (!isset($e)) {
       // Se não houve exceção, redireciona.
       Html::redirect($config_page_url);
    }
} // Fim do if (!empty($_POST))

// --- Lógica de Exibição GET ---
Html::header(__('Direct Label Printer Configuration', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins", __('Direct Label Printer', 'directlabelprinter'));

// Obter dados atuais das tabelas do plugin (IDÊNTICO)
$current_auth_result = $DB->request(['FROM' => $auth_table, 'LIMIT' => 1]);
$current_auth = $current_auth_result->current() ?? [];
$layouts_result = $DB->request(['FROM' => $layouts_table]);
$layouts_from_db = []; foreach ($layouts_result as $layout) { $layouts_from_db[] = $layout; }
$layout_options = []; $default_layout_id_api = null;
foreach ($layouts_from_db as $layout) {
    if (isset($layout['id_api']) && isset($layout['nome'])) $layout_options[$layout['id_api']] = $layout['nome'];
    if (isset($layout['padrao']) && $layout['padrao'] == 1) $default_layout_id_api = $layout['id_api'] ?? null;
}

// Gerar Token CSRF para o formulário
$csrf_token_value = Session::getNewCSRFToken($csrf_token_name);
Toolbox::logInFile("debug", "[Config Page GET] Token CSRF gerado: " . substr($csrf_token_value, 0, 20) . "... (primeiros 20 chars)");
Toolbox::logInFile("debug", "[Config Page GET] Nome do token CSRF: " . $csrf_token_name);

// --- Construir Formulário Diretamente com PHP/HTML ---
echo "<form name='config_form_directlabelprinter' action='$config_page_url' method='POST' class='glpi_form'>";
echo Html::hidden('_glpi_csrf_token', ['value' => $csrf_token_value]);
Toolbox::logInFile("debug", "[Config Page GET] Campo hidden CSRF adicionado ao formulário com valor: " . substr($csrf_token_value, 0, 20) . "...");

// --- Authentication Section ---
echo "<div class='card card-flush shadow-sm m-6'>";
echo "  <div class='card-header'><h3 class='card-title fw-bold'>" . __('Authentication', 'directlabelprinter') . "</h3></div>";
echo "  <div class='card-body'>";
echo "      <table class='tab_cadre_noborder' width='100%'>"; // Usar tabela para alinhar
echo "          <tr>";
echo "              <td width='30%'>" . __('API URL', 'directlabelprinter') . "</td>";
echo "              <td>" . Html::input('api_url', ['value' => $current_auth['api_url'] ?? '', 'size' => 60]) . "</td>";
echo "          </tr>";
echo "          <tr>";
echo "              <td>" . __('User', 'directlabelprinter') . "</td>";
echo "              <td>" . Html::input('api_user', ['value' => $current_auth['user'] ?? '']) . "</td>";
echo "          </tr>";
echo "          <tr>";
echo "              <td>" . __('Password', 'directlabelprinter') . "</td>";
// Sempre mostrar campo de senha vazio, mas indicar se algo está salvo (opcional)
$pass_options = ['value' => '', 'type' => 'password'];
// if (!empty($current_auth['access_token'])) { $pass_options['placeholder'] = __('Senha salva - preencha para alterar', 'directlabelprinter'); }
echo "              <td>" . Html::input('api_password', $pass_options) . "</td>";
echo "          </tr>";
echo "          <tr><td></td><td>";
echo "              <button type='submit' name='test_connection' value='1' class='btn btn-secondary btn-sm'>" . __('Test Connection', 'directlabelprinter') . "</button>";
echo "          </td></tr>";
if (!empty($current_auth['access_token'])) {
    echo "      <tr><td colspan='2'><div class='alert alert-info mt-3' role='alert'>";
    echo            __('Token de Acesso válido até:', 'directlabelprinter') . ' ' . Html::convDateTime($current_auth['access_token_expires']); // Usar helper de data do GLPI
    echo "      </div></td></tr>";
}
echo "      </table>";
echo "  </div>"; // end card-body
echo "</div>"; // end card

// --- Layouts Section ---
echo "<div class='card card-flush shadow-sm m-6'>";
echo "  <div class='card-header'><h3 class='card-title fw-bold'>" . __('Layouts', 'directlabelprinter') . "</h3></div>";
echo "  <div class='card-body'>";
echo "      <button type='submit' name='fetch_layouts' value='1' class='btn btn-secondary btn-sm'>" . __('Fetch Layouts', 'directlabelprinter') . "</button>";
echo "      <div class='mt-4'>";
echo            __('Default Layout', 'directlabelprinter') . "&nbsp;"; // Label
Dropdown::showFromArray("default_layout_id_api", $layout_options, ['value' => $default_layout_id_api]); // Usar Dropdown::showFromArray
echo "      </div>";

echo "      <h4 class='mt-5'>" . __('Layout Details', 'directlabelprinter') . "</h4>";
if (!empty($layouts_from_db)) {
    echo "  <table class='tab_cadre_fixehov'>";
    echo "      <thead><tr>";
    echo "          <th>" . __('Name') . "</th>";
    echo "          <th>" . __('Description') . "</th>";
    // ... (headers para outras colunas) ...
    echo "          <th>" . __('Default', 'directlabelprinter') . "</th>";
    echo "      </tr></thead>";
    echo "      <tbody>";
    foreach ($layouts_from_db as $layout) {
        echo "  <tr>";
        echo "      <td>" . ($layout['nome'] ?? '') . "</td>";
        echo "      <td>" . ($layout['descricao'] ?? '') . "</td>";
        // ... (células para outras colunas) ...
        echo "      <td>" . (isset($layout['padrao']) && $layout['padrao'] ? "<i class='fas fa-check text-success'></i>" : '') . "</td>";
        echo "  </tr>";
    }
    echo "      </tbody>";
    echo "  </table>";
} else {
    echo "  <p>" . __('No layouts fetched yet.', 'directlabelprinter') . "</p>";
}
echo "  </div>"; // end card-body
echo "  <div class='card-footer d-flex justify-content-end py-6 px-9'>";
echo "      <button type='submit' name='update_defaults' value='" . _x('button', 'Save') . "' class='btn btn-primary'>";
echo "          <i class='ti ti-device-floppy me-2'></i> " . _x('button', 'Save');
echo "      </button>";
echo "  </div>"; // end card-footer
echo "</div>"; // end card

// Fechar o formulário
echo "</form>";

// --- Fim do Formulário ---

Html::footer();

?>