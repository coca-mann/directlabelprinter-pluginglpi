<?php
// plugins/directlabelprinter/front/config.php

include ("../../../inc/includes.php"); // Inclui o GLPI

use Toolbox;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\Sanitizer;
use GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions; // Para a função makeAuthenticatedApiRequest
use Config as CoreConfig; // Necessário para setConfigurationValues se ainda usar algo


Toolbox::logInFile("debug", "[Config Page GET] Script accessed. User ID: " . Session::getLoginUserID());
Toolbox::logInFile("debug", "[Config Page GET] GET Params: " . print_r($_GET, true));
Toolbox::logInFile("debug", "[Config Page GET] POST Params: " . print_r($_POST, true)); // Deve estar vazio
Toolbox::logInFile("debug", "[Config Page GET] REQUEST Params: " . print_r($_REQUEST, true));

// --- Verificação de Permissões ---
// Usaremos 'config' -> UPDATE como permissão geral para esta página
// Session::checkRight('config', UPDATE);

// --- Variáveis Globais ---
$template_renderer = TemplateRenderer::getInstance();
global $DB;
$auth_table = 'glpi_plugin_directlabelprinter_auth';
$layouts_table = 'glpi_plugin_directlabelprinter_layouts';
$config_page_url = Plugin::getWebDir('directlabelprinter', true) . "/front/config.php"; // URL para redirecionamentos

// --- Lógica de Processamento POST ---
/* if (!empty($_POST)) {
    // Verificar CSRF Token
    Session::checkCSRF('plugin_directlabelprinter_config', $_POST['_glpi_csrf_token']);

    // Identificar a Ação
    if (isset($_POST['test_connection'])) {
        Toolbox::logInFile("debug", "[Config Page] Test Connection POST received.");
        try {
            // Obter dados do POST
            $api_url = rtrim(Sanitizer::getPostVariable('api_url'), '/');
            $username = Sanitizer::getPostVariable('api_user');
            $password = Sanitizer::getPostVariable('api_password'); // Obter a senha diretamente

            if (empty($api_url) || empty($username) || empty($password)) {
                throw new \Exception(__('Preencha URL, Usuário e Senha.', 'directlabelprinter'));
            }

            $token_endpoint = $api_url . '/api/auth/token/';
            Toolbox::logInFile("debug", "[Config Page] Calling cURL to: " . $token_endpoint);

            // Chamada cURL (copiada de test_connection.php)
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $token_endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $username, 'password' => $password]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $api_response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) throw new \Exception("Erro cURL: " . $curl_error);
            if ($http_code != 200) {
                 $error_details = json_decode($api_response_body, true);
                 throw new \Exception($error_details['detail'] ?? "Erro API ($http_code)");
            }

            $tokens = json_decode($api_response_body, true);
            if (empty($tokens['access']) || empty($tokens['refresh'])) throw new \Exception('Tokens não encontrados na resposta.');

            // Calcular expiração e preparar dados
            $expires_timestamp = time() + 3600;
            $expires_datetime = date('Y-m-d H:i:s', $expires_timestamp);
            $data_to_save = [
                'user'                 => $username,
                'api_url'              => $api_url, // Salvar URL aqui também
                'access_token'         => $tokens['access'],
                'refresh_token'        => $tokens['refresh'],
                'access_token_expires' => $expires_datetime
            ];

            // Salvar/Atualizar na tabela _auth
            $existing_result = $DB->request([
                'FROM' => $auth_table,
                'LIMIT' => 1
            ]);
            $existing = $existing_result->current();
            
            if (empty($existing)) {
                $DB->insert($auth_table, $data_to_save);
            } else {
                $DB->update($auth_table, $data_to_save, ['id' => $existing['id']]);
            }

            Session::addMessageAfterRedirect(__('Autenticação bem-sucedida! Tokens salvos.', 'directlabelprinter'), true, INFO);

        } catch (\Exception $e) {
            Toolbox::logInFile("error", "[Config Page] Test Connection Error: " . $e->getMessage());
            Session::addMessageAfterRedirect(__('Erro na conexão: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
        }
        // Redirecionar para evitar reenvio do formulário
        Html::redirect($config_page_url);

    } else if (isset($_POST['fetch_layouts'])) {
        Toolbox::logInFile("debug", "[Config Page] Fetch Layouts POST received.");
        try {
            // Usar a função centralizada (requer que ela esteja definida e funcional)
            $layouts_from_api = DirectLabelPrinterActions::makeAuthenticatedApiRequest('GET', '/api/layouts/');

            if (!is_array($layouts_from_api)) throw new \Exception("Resposta inesperada da API de layouts.");

            global $DB;
            // Limpar tabela e inserir novos layouts (código de ajax/fetch_layouts.php)
            $DB->query("TRUNCATE TABLE `$layouts_table`");
            $default_found = false;
            foreach ($layouts_from_api as $layout_data) {
                 $is_default = ($layout_data['padrao'] ?? false) ? 1 : 0;
                 $data_to_insert = [ 'padrao' => $is_default ];
                 if ($is_default) $default_found = true;
                 if (!empty($data_to_insert['id_api'])) {
                      $DB->insert($layouts_table, $data_to_insert);
                 }
            }
            Session::addMessageAfterRedirect(__('Layouts buscados e atualizados!', 'directlabelprinter'), true, INFO);

        } catch (\Exception $e) {
             Toolbox::logInFile("error", "[Config Page] Fetch Layouts Error: " . $e->getMessage());
             Session::addMessageAfterRedirect(__('Erro ao buscar layouts: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
        }
        Html::redirect($config_page_url);

    } else if (isset($_POST['update_defaults'])) {
        Toolbox::logInFile("debug", "[Config Page] Update Defaults POST received.");
        try {
            $selected_default_id_api = Sanitizer::getPostVariable('default_layout_id_api');

            global $DB;
            // Resetar todos para não padrão
            $DB->update($layouts_table, ['padrao' => 0], ['padrao' => 1]);
            // Definir o novo padrão
            if ($selected_default_id_api !== null && $selected_default_id_api !== '') {
                 $DB->update($layouts_table, ['padrao' => 1], ['id_api' => $selected_default_id_api]);
                 // Chamar API para definir padrão lá também
                 DirectLabelPrinterActions::makeAuthenticatedApiRequest('POST', "/api/layouts/{$selected_default_id_api}/selecionar-padrao/");
            }

            Session::addMessageAfterRedirect(__('Layout padrão atualizado!', 'directlabelprinter'), true, INFO);

        } catch (\Exception $e) {
            Toolbox::logInFile("error", "[Config Page] Update Defaults Error: " . $e->getMessage());
            Session::addMessageAfterRedirect(__('Erro ao atualizar layout padrão: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
        }
         Html::redirect($config_page_url);
    }
} */

// --- Lógica de Exibição GET ---
// Html::header(__('Direct Label Printer Configuration', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins", __('Direct Label Printer', 'directlabelprinter'));

// Obter dados atuais para exibir no formulário
$current_auth_result = $DB->request([
    'FROM' => $auth_table,
    'LIMIT' => 1
]);
$current_auth = $current_auth_result->current() ?? []; // Pega a primeira linha ou array vazio

$layouts_result = $DB->request([
    'FROM' => $layouts_table
]);
$layouts_from_db = [];
foreach ($layouts_result as $layout) {
    $layouts_from_db[] = $layout;
}

$layout_options = [];
$default_layout_id_api = null; // Usar o ID da API como valor
foreach ($layouts_from_db as $layout) {
    $layout_options[$layout['id_api']] = $layout['nome'];
    if ($layout['padrao'] == 1) {
        $default_layout_id_api = $layout['id_api'];
    }
}

// Passar dados para o Twig
$twig_data = [
    'plugin_name'           => 'directlabelprinter',
    'config_page_url'       => $config_page_url,
    'current_auth'          => $current_auth, // Passa dados da tabela _auth
    'layouts'               => $layouts_from_db,
    'layout_options'        => $layout_options,
    'default_layout_id_api' => $default_layout_id_api,
    'can_edit'              => Session::haveRight('config', UPDATE),
    'csrf_token'            => Session::getNewCSRFToken('plugin_directlabelprinter_config') // Gera um novo token para o form
];

// Renderizar o template Twig
try {
    // Usar o namespace do plugin '@directlabelprinter' // <-- CORRETO
    // O nome do ficheiro é relativo ao diretório 'templates' do plugin
    echo $template_renderer->render('@directlabelprinter/config_page.html.twig', $twig_data);
} catch (\Exception $e) {
    // Lidar com erro de renderização do Twig
    // Usar Html::displayErrorAndDie() para um erro mais integrado ao GLPI
    Html::displayErrorAndDie("Erro ao renderizar template: " . $e->getMessage());
    // O log pode ser útil também, mas displayErrorAndDie interrompe
    // Toolbox::logInFile("error", "Erro Twig: " . $e->getMessage());
}

Html::footer();

?>