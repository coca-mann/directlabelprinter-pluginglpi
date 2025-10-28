<?php
// plugins/directlabelprinter/front/config.php

include ("../../../inc/includes.php");

use Toolbox;
use Html;
use Session;
use Glpi\Application\View\TemplateRenderer; // Necessário para a próxima etapa
use Config as CoreConfig; // Necessário para a próxima etapa
use Plugin; // Necessário para getWebDir

Toolbox::logInFile("debug", "[Config Page Step 3] Script accessed. User ID: " . Session::getLoginUserID());

// Verificar Permissão
Session::checkRight('config', UPDATE);
Toolbox::logInFile("debug", "[Config Page Step 3] Passed checkRight.");

// Header
Html::header(__('Direct Label Printer Configuration (CSRF Test)', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins");
Toolbox::logInFile("debug", "[Config Page Step 3] Passed Html::header.");

// --- Bloco POST ainda comentado ---
// if (!empty($_POST)) { ... }

// --- Busca de Dados DB (Já testada e funcionando) ---
global $DB;
$auth_table = 'glpi_plugin_directlabelprinter_auth';
$layouts_table = 'glpi_plugin_directlabelprinter_layouts';
// Ajuste: Usar a forma correta de obter a URL base do GLPI
$config_page_url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/directlabelprinter/front/config.php";

$current_auth = [];
$layouts_from_db = [];
$layout_options = [];
$default_layout_id_api = null;

try {
    $current_auth_result = $DB->request(['FROM' => $auth_table, 'LIMIT' => 1]);
    $current_auth = $current_auth_result->current() ?? [];

    $layouts_result = $DB->request(['FROM' => $layouts_table]);
    foreach ($layouts_result as $layout) {
        $layouts_from_db[] = $layout;
        $layout_options[$layout['id_api']] = $layout['nome'];
        if ($layout['padrao'] == 1) {
            $default_layout_id_api = $layout['id_api'];
        }
    }
    Toolbox::logInFile("debug", "[Config Page Step 3] DB data fetched successfully.");

} catch (\Exception $e) {
    Toolbox::logInFile("error", "[Config Page Step 3] DB Error: " . $e->getMessage());
    Html::displayErrorAndDie("Erro ao aceder ao banco de dados: " . $e->getMessage());
}
// --- Fim da Busca de Dados DB ---


// --- Reintroduzir Preparação de Dados para Twig e Geração CSRF ---
Toolbox::logInFile("debug", "[Config Page Step 3] Preparing Twig data and generating CSRF token...");
$csrf_token_name = 'plugin_directlabelprinter_config'; // Nome para o token
$csrf_token_value = ''; // Inicializa vazio

try {
    $csrf_token_value = Session::getNewCSRFToken($csrf_token_name); // Gera o token
    Toolbox::logInFile("debug", "[Config Page Step 3] CSRF token generated successfully: " . $csrf_token_value); // Log do token gerado

    $twig_data = [
        'plugin_name'           => 'directlabelprinter',
        'config_page_url'       => $config_page_url,
        'current_auth'          => $current_auth,
        'layouts'               => $layouts_from_db,
        'layout_options'        => $layout_options,
        'default_layout_id_api' => $default_layout_id_api,
        'can_edit'              => Session::haveRight('config', UPDATE),
        'csrf_token'            => $csrf_token_value // Usa o token gerado
    ];
    Toolbox::logInFile("debug", "[Config Page Step 3] Twig data array prepared.");

} catch (\Exception $e) {
    // Capturar erros potenciais da geração do token CSRF (raro, mas possível)
    Toolbox::logInFile("error", "[Config Page Step 3] Error preparing Twig data/CSRF: " . $e->getMessage());
    Html::displayErrorAndDie("Erro ao preparar dados da página: " . $e->getMessage());
}
// --- Fim da Preparação de Dados ---


// --- Renderização Twig AINDA COMENTADA ---
// $template_renderer = TemplateRenderer::getInstance();
// try {
//     echo $template_renderer->render('@directlabelprinter/config_page.html.twig', $twig_data);
//     Toolbox::logInFile("debug", "[Config Page Step 3] Twig template rendered.");
// } catch (\Exception $e) {
//     Html::displayErrorAndDie("Erro ao renderizar template: " . $e->getMessage());
// }
// --- Fim Renderização Twig ---

// Exibir uma Mensagem Simples
echo "<h1>Teste da Página de Configuração - Passo 3 (CSRF Token)</h1>";
echo "<p>Se você vê esta mensagem, a busca no DB e a geração do token CSRF funcionaram (verifique os logs).</p>";
echo "<p>CSRF Token Gerado: " . Html::clean($csrf_token_value) . "</p>"; // Mostra o token gerado na página
Toolbox::logInFile("debug", "[Config Page Step 3] Displaying simple message.");


// Footer
Html::footer();
Toolbox::logInFile("debug", "[Config Page Step 3] Script finished.");

?>