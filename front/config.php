<?php
// plugins/directlabelprinter/front/config.php

include ("../../../inc/includes.php"); // Inclui o GLPI Core

// --- Namespaces Usados ---
use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\Sanitizer;
use GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions; // Para makeAuthenticatedApiRequest
use Html;
use Session;
use Toolbox;
use Plugin;
use DateTime; // Para cálculo da expiração

// --- Verificação de Permissões ---
// Permitir acesso à página com READ, mas ações exigirão UPDATE
Session::checkLoginUser();
if (!Session::haveRight('config', READ)) {
    Html::displayRightError(); // Mostra erro e sai se não puder ler
}
$can_edit = Session::haveRight('config', UPDATE); // Variável para o template

// --- Variáveis Globais e Constantes ---
$template_renderer = TemplateRenderer::getInstance();
global $DB; // Acesso ao DB
$auth_table = 'glpi_plugin_directlabelprinter_auth';
$layouts_table = 'glpi_plugin_directlabelprinter_layouts';
// URL da própria página para action do form e redirecionamentos
$config_page_url = Plugin::getWebDir('directlabelprinter', true) . "/front/config.php";

// --- Lógica de Processamento POST ---
if ($can_edit && !empty($_POST)) {
    // Verificar CSRF Token
    // Usar um nome consistente para o token
    $csrf_token_name = 'plugin_directlabelprinter_config_form';
    Session::checkCSRF($csrf_token_name, $_POST['_glpi_csrf_token']);

    // Identificar a Ação
    if (isset($_POST['test_connection'])) {
        Toolbox::logInFile("debug", "[Config Page] Test Connection POST received.");
        try {
            // Obter dados do POST
            $api_url = rtrim(Sanitizer::getPostVariable('api_url'), '/');
            $username = Sanitizer::getPostVariable('api_user');
            $password = Sanitizer::getPostVariable('api_password');

            if (empty($api_url) || empty($username) || empty($password)) {
                throw new \Exception(__('Preencha URL, Usuário e Senha.', 'directlabelprinter'));
            }
            $token_endpoint = $api_url . '/api/auth/token/';

            // Chamada cURL
            $ch = curl_init($token_endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $username, 'password' => $password]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para teste
            $api_response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Processar resposta cURL
            if ($curl_error) throw new \Exception("Erro cURL: " . $curl_error);
            if ($http_code != 200) {
                $error_details = json_decode($api_response_body, true);
                throw new \Exception($error_details['detail'] ?? "Erro API ($http_code)");
            }
            $tokens = json_decode($api_response_body, true);
            if (empty($tokens['access']) || empty($tokens['refresh'])) throw new \Exception('Tokens não encontrados na resposta.');

            // Calcular expiração e preparar dados
            $expires_datetime = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');
            $data_to_save = [
                'user'                 => $username,
                'api_url'              => $api_url, // Salvar URL na tabela _auth
                'access_token'         => $tokens['access'],
                'refresh_token'        => $tokens['refresh'],
                'access_token_expires' => $expires_datetime
                 // Não salvamos a senha aqui
            ];

            // Salvar/Atualizar na tabela _auth
            $existing_result = $DB->request(['FROM' => $auth_table, 'LIMIT' => 1]);
            $existing = [];
            foreach ($existing_result as $row) {
                $existing = $row;
                break; // Pega apenas a primeira linha
            }
            if (empty($existing)) {
                $DB->insert($auth_table, $data_to_save);
            } else {
                $DB->update($auth_table, $data_to_save, ['id' => $existing['id']]);
            }
            Session::addMessageAfterRedirect(__('Autenticação bem-sucedida! Tokens salvos.', 'directlabelprinter'), false, INFO);

        } catch (\Exception $e) {
            Toolbox::logInFile("error", "[Config Page] Test Connection Error: " . $e->getMessage());
            Session::addMessageAfterRedirect(__('Erro na conexão: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
        }
        Html::redirect($config_page_url); // Redireciona após processar

    } else if (isset($_POST['fetch_layouts'])) {
        Toolbox::logInFile("debug", "[Config Page] Fetch Layouts POST received.");
        try {
            // Verificar se a função existe antes de chamar
            if (!class_exists(DirectLabelPrinterActions::class) || !method_exists(DirectLabelPrinterActions::class, 'makeAuthenticatedApiRequest')) {
                 throw new \Exception("Função makeAuthenticatedApiRequest não encontrada.");
            }
            $layouts_from_api = DirectLabelPrinterActions::makeAuthenticatedApiRequest('GET', '/api/layouts/');
            if (!is_array($layouts_from_api)) throw new \Exception("Resposta inesperada da API de layouts.");

            $DB->query("TRUNCATE TABLE `$layouts_table`");
            foreach ($layouts_from_api as $layout_data) {
                 // Mapear dados da API para colunas do BD (Exemplo, ajuste os nomes se necessário)
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
                 }
            }
            Session::addMessageAfterRedirect(__('Layouts buscados e atualizados!', 'directlabelprinter'), false, INFO);

        } catch (\Exception $e) {
             Toolbox::logInFile("error", "[Config Page] Fetch Layouts Error: " . $e->getMessage());
             Session::addMessageAfterRedirect(__('Erro ao buscar layouts: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
        }
        Html::redirect($config_page_url);

    } else if (isset($_POST['update_defaults'])) {
        Toolbox::logInFile("debug", "[Config Page] Update Defaults POST received.");
        try {
            $selected_default_id_api = Sanitizer::getPostVariable('default_layout_id_api');

            // Resetar todos para não padrão
            $DB->update($layouts_table, ['padrao' => 0], ['padrao' => 1]);
            // Definir o novo padrão no DB
            if ($selected_default_id_api !== null && $selected_default_id_api !== '') {
                 $DB->update($layouts_table, ['padrao' => 1], ['id_api' => $selected_default_id_api]);
                 // Chamar API para definir padrão lá também
                 if (class_exists(DirectLabelPrinterActions::class) && method_exists(DirectLabelPrinterActions::class, 'makeAuthenticatedApiRequest')) {
                    DirectLabelPrinterActions::makeAuthenticatedApiRequest('POST', "/api/layouts/{$selected_default_id_api}/selecionar-padrao/");
                 } else {
                     Toolbox::logWarning("[Config Page] Função makeAuthenticatedApiRequest não encontrada para atualizar padrão na API.");
                 }
            }
            Session::addMessageAfterRedirect(__('Layout padrão atualizado!', 'directlabelprinter'), false, INFO);

        } catch (\Exception $e) {
            Toolbox::logInFile("error", "[Config Page] Update Defaults Error: " . $e->getMessage());
            Session::addMessageAfterRedirect(__('Erro ao atualizar layout padrão: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
        }
         Html::redirect($config_page_url);
    }
} // Fim do if (!empty($_POST))

// --- Lógica de Exibição GET ---
Html::header(__('Direct Label Printer Configuration', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins", __('Direct Label Printer', 'directlabelprinter'));

// Obter dados atuais das tabelas do plugin
$current_auth_result = $DB->request(['FROM' => $auth_table, 'LIMIT' => 1]);
$current_auth = [];
foreach ($current_auth_result as $row) {
    $current_auth = $row;
    break; // Pega apenas a primeira linha
}

$layouts_result = $DB->request(['FROM' => $layouts_table]);
$layouts_from_db = []; foreach ($layouts_result as $layout) { $layouts_from_db[] = $layout; }

$layout_options = [];
$default_layout_id_api = null;
foreach ($layouts_from_db as $layout) {
    if (isset($layout['id_api']) && isset($layout['nome'])) {
        $layout_options[$layout['id_api']] = $layout['nome'];
    }
    if (isset($layout['padrao']) && $layout['padrao'] == 1) {
        $default_layout_id_api = $layout['id_api'] ?? null;
    }
}

// Preparar dados e gerar CSRF token para o Twig
$csrf_token_name = 'plugin_directlabelprinter_config_form'; // Usar o mesmo nome da validação POST
$csrf_token_value = Session::getNewCSRFToken($csrf_token_name);
$twig_data = [
    'plugin_name'           => 'directlabelprinter',
    'config_page_url'       => $config_page_url,
    'current_auth'          => $current_auth,
    'layouts'               => $layouts_from_db,
    'layout_options'        => $layout_options,
    'default_layout_id_api' => $default_layout_id_api,
    'can_edit'              => $can_edit,
    'csrf_token'            => $csrf_token_value
];

// Renderizar o template Twig que contém apenas o formulário
try {
    echo $template_renderer->render('@directlabelprinter/config_form_content.html.twig', $twig_data);
} catch (\Exception $e) {
    Html::displayErrorAndDie("Erro ao renderizar template: " . $e->getMessage());
}

Html::footer();
?>