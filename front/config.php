<?php
// plugins/directlabelprinter/front/config.php

include ("../../../inc/includes.php");

use Toolbox;
use Html;
use Session;
use Glpi\Application\View\TemplateRenderer;
use Config as CoreConfig;
use Plugin;

Toolbox::logInFile("debug", "[Config Page Step 4] Script accessed. User ID: " . Session::getLoginUserID());

// Verificar Permissão
Session::checkRight('config', UPDATE);
Toolbox::logInFile("debug", "[Config Page Step 4] Passed checkRight.");

// Header
// Usar Html::header() para o título e breadcrumbs
Html::header(__('Direct Label Printer Configuration', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins", __('Direct Label Printer', 'directlabelprinter'));
Toolbox::logInFile("debug", "[Config Page Step 4] Passed Html::header.");

// --- Bloco POST ainda comentado ---
// if (!empty($_POST)) { ... }

// --- Busca de Dados DB (Funcionando) ---
global $DB, $CFG_GLPI;
$auth_table = 'glpi_plugin_directlabelprinter_auth';
$layouts_table = 'glpi_plugin_directlabelprinter_layouts';
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
        if (isset($layout['id_api']) && isset($layout['nome'])) {
             $layout_options[$layout['id_api']] = $layout['nome'];
        }
        if (isset($layout['padrao']) && $layout['padrao'] == 1) {
            $default_layout_id_api = $layout['id_api'] ?? null;
        }
    }
    Toolbox::logInFile("debug", "[Config Page Step 4] DB data fetched successfully.");

} catch (\Exception $e) {
    Toolbox::logInFile("error", "[Config Page Step 4] DB Error: " . $e->getMessage());
    Html::displayErrorAndDie("Erro ao aceder ao banco de dados: " . $e->getMessage());
}
// --- Fim da Busca de Dados DB ---


// --- Preparação de Dados para Twig e Geração CSRF (Funcionando) ---
Toolbox::logInFile("debug", "[Config Page Step 4] Preparing Twig data and generating CSRF token...");
$csrf_token_name = 'plugin_directlabelprinter_config';
$csrf_token_value = '';

try {
    $csrf_token_value = Session::getNewCSRFToken($csrf_token_name);
    Toolbox::logInFile("debug", "[Config Page Step 4] CSRF token generated successfully: " . $csrf_token_value);

    $twig_data = [
        'plugin_name'           => 'directlabelprinter',
        'config_page_url'       => $config_page_url,
        'current_auth'          => $current_auth,
        'layouts'               => $layouts_from_db,
        'layout_options'        => $layout_options,
        'default_layout_id_api' => $default_layout_id_api,
        'can_edit'              => Session::haveRight('config', UPDATE),
        'csrf_token'            => $csrf_token_value
    ];
    Toolbox::logInFile("debug", "[Config Page Step 4] Twig data array prepared.");

} catch (\Exception $e) {
    Toolbox::logInFile("error", "[Config Page Step 4] Error preparing Twig data/CSRF: " . $e->getMessage());
    Html::displayErrorAndDie("Erro ao preparar dados da página: " . $e->getMessage());
}
// --- Fim da Preparação de Dados ---


// --- Reintroduzir Renderização Twig ---
Toolbox::logInFile("debug", "[Config Page Test 1 Corrected] Attempting to get TemplateRenderer instance...");
// ---> OBTER A INSTÂNCIA DO RENDERER <---
$template_renderer = TemplateRenderer::getInstance(); // Esta linha estava faltando ou comentada

if ($template_renderer === null) {
    Toolbox::logInFile("error", "[Config Page Test 1 Corrected] Failed to get TemplateRenderer instance!");
    Html::displayErrorAndDie("Erro crítico: Não foi possível obter o motor de templates.");
} else {
    Toolbox::logInFile("debug", "[Config Page Test 1 Corrected] Got TemplateRenderer instance. Attempting to render SIMPLE STRING...");
    try {
        // Tenta renderizar uma string Twig básica
        // Passar um array vazio para os dados, pois não são usados aqui
        echo $template_renderer->render(
            '{% extends "@core/layout.html.twig" %}{% block content %}<h1>Teste Twig Simples</h1><p>Renderização básica funcionou.</p>{% endblock %}',
            [] // Passa array vazio
        );
        Toolbox::logInFile("debug", "[Config Page Test 1 Corrected] Simple string rendered successfully.");
    } catch (\Exception $e) {
        Toolbox::logInFile("error", "[Config Page Test 1 Corrected] Twig Simple String Rendering Error: " . $e->getMessage());
        Html::displayErrorAndDie("Erro ao renderizar string Twig: " . $e->getMessage());
    }
}

/* Toolbox::logInFile("debug", "[Config Page Step 4] Attempting to render Twig template...");
$template_renderer = TemplateRenderer::getInstance(); // Obter instância do renderer
try {
    // Usar o namespace do plugin
    echo $template_renderer->render('@directlabelprinter/config_page.html.twig', $twig_data);
    Toolbox::logInFile("debug", "[Config Page Step 4] Twig template rendered successfully.");
} catch (\Exception $e) {
    Toolbox::logInFile("error", "[Config Page Step 4] Twig Rendering Error: " . $e->getMessage()); // Log específico
    Html::displayErrorAndDie("Erro ao renderizar template: " . $e->getMessage());
} */
// --- Fim Renderização Twig ---

// --- Remover Mensagem Simples ---
// echo "<h1>Teste da Página de Configuração - Passo 3 (CSRF Token)</h1>";
// echo "<p>Se você vê esta mensagem, a busca no DB e a geração do token CSRF funcionaram (verifique os logs).</p>";
// echo "<p>CSRF Token Gerado: " . htmlspecialchars($csrf_token_value, ENT_QUOTES, 'UTF-8') . "</p>";
// Toolbox::logInFile("debug", "[Config Page Step 3] Displaying simple message.");


// Footer
Html::footer();
Toolbox::logInFile("debug", "[Config Page Step 4] Script finished.");

?>