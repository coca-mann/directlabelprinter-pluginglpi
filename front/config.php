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
    Session::checkCSRF($csrf_token_name, $_POST['_glpi_csrf_token']);

    try {
        if (isset($_POST['test_connection'])) {
            // ... (Lógica POST para Test Connection - IDÊNTICA À ANTERIOR, usando cURL) ...
            $api_url = rtrim(Sanitizer::getPostVariable('api_url'), '/');
            $username = Sanitizer::getPostVariable('api_user');
            $password = Sanitizer::getPostVariable('api_password');
            if (empty($api_url) || empty($username) || empty($password)) throw new \Exception(__('Preencha URL, Usuário e Senha.', 'directlabelprinter'));
            $token_endpoint = $api_url . '/api/auth/token/';
            // cURL Call... (copie a lógica cURL completa daqui)
            $ch = curl_init(/*...*/); /*...*/ $api_response_body = curl_exec($ch); /*...*/ curl_close($ch);
            if ($curl_error || $http_code != 200) throw new \Exception(/*...*/);
            $tokens = json_decode($api_response_body, true); if (empty($tokens['access'])) throw new \Exception(/*...*/);
            // Salvar no DB...
            $expires_datetime = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');
            $data_to_save = [/*...*/];
            $existing_result = $DB->request(['FROM'=>$auth_table,'LIMIT'=>1]); $existing = $existing_result->current();
            if (empty($existing)) {$DB->insert($auth_table, $data_to_save);} else {$DB->update($auth_table, $data_to_save, ['id'=>$existing['id']]);}
            Session::addMessageAfterRedirect(__('Autenticação bem-sucedida! Tokens salvos.', 'directlabelprinter'), false, INFO);

        } else if (isset($_POST['fetch_layouts'])) {
             // ... (Lógica POST para Fetch Layouts - IDÊNTICA À ANTERIOR, usando makeAuthenticatedApiRequest) ...
             if (!class_exists(DirectLabelPrinterActions::class) || !method_exists(DirectLabelPrinterActions::class, 'makeAuthenticatedApiRequest')) throw new \Exception(/*...*/);
             $layouts_from_api = DirectLabelPrinterActions::makeAuthenticatedApiRequest('GET', '/api/layouts/');
             if (!is_array($layouts_from_api)) throw new \Exception(/*...*/);
             $DB->query("TRUNCATE TABLE `$layouts_table`");
             foreach ($layouts_from_api as $layout_data) { $data_to_insert = [/*...*/]; $DB->insert($layouts_table, $data_to_insert); }
             Session::addMessageAfterRedirect(__('Layouts buscados e atualizados!', 'directlabelprinter'), false, INFO);

        } else if (isset($_POST['update_defaults'])) {
            // ... (Lógica POST para Update Defaults - IDÊNTICA À ANTERIOR) ...
             $selected_default_id_api = Sanitizer::getPostVariable('default_layout_id_api');
             $DB->update($layouts_table, ['padrao' => 0], ['padrao' => 1]);
             if ($selected_default_id_api !== null && $selected_default_id_api !== '') {
                  $DB->update($layouts_table, ['padrao' => 1], ['id_api' => $selected_default_id_api]);
                  if (class_exists(DirectLabelPrinterActions::class) /*...*/) { DirectLabelPrinterActions::makeAuthenticatedApiRequest('POST', "/api/layouts/{$selected_default_id_api}/selecionar-padrao/"); }
             }
             Session::addMessageAfterRedirect(__('Layout padrão atualizado!', 'directlabelprinter'), false, INFO);
        }
    } catch (\Exception $e) {
        Session::addMessageAfterRedirect(__('Erro: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
    }
    // Redirecionar em TODOS os casos POST para limpar o POST e mostrar mensagens
    Html::redirect($config_page_url);
}

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

// --- Construir Formulário Diretamente com PHP/HTML ---
echo "<form name='config_form_directlabelprinter' action='$config_page_url' method='POST' class='glpi_form'>";
echo Html::hidden('_glpi_csrf_token', ['value' => $csrf_token_value]);

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