<?php
// plugins/directlabelprinter/front/config.php

include ("../../../inc/includes.php");

use Toolbox;
use Html;
use Session;
use Glpi\Application\View\TemplateRenderer;
use Config as CoreConfig;
use Plugin;

Toolbox::logInFile("debug", "[Config Page Hybrid] Script accessed. User ID: " . Session::getLoginUserID());

// Verificar Permissão
Session::checkRight('config', UPDATE);
Toolbox::logInFile("debug", "[Config Page Hybrid] Passed checkRight.");

// Header Padrão GLPI
Html::header(__('Direct Label Printer Configuration', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins", __('Direct Label Printer', 'directlabelprinter'));
Toolbox::logInFile("debug", "[Config Page Hybrid] Passed Html::header.");

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
    // ... (código de busca DB existente) ...
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
    Toolbox::logInFile("debug", "[Config Page Hybrid] DB data fetched successfully.");
} catch (\Exception $e) { /* ... tratamento de erro DB ... */ }

// --- Preparação de Dados para Twig e Geração CSRF (Funcionando) ---
$csrf_token_name = 'plugin_directlabelprinter_config';
$csrf_token_value = '';
$twig_data = [];
try {
    $csrf_token_value = Session::getNewCSRFToken($csrf_token_name);
    $twig_data = [
        'plugin_name'           => 'directlabelprinter',
        'config_page_url'       => $config_page_url, // URL para action do form
        'current_auth'          => $current_auth,
        'layouts'               => $layouts_from_db,
        'layout_options'        => $layout_options,
        'default_layout_id_api' => $default_layout_id_api,
        'can_edit'              => Session::haveRight('config', UPDATE),
        'csrf_token'            => $csrf_token_value // Passa o token para o template
    ];
    Toolbox::logInFile("debug", "[Config Page Hybrid] Twig data array prepared.");
} catch (\Exception $e) { /* ... tratamento de erro CSRF ... */ }


// --- Renderizar APENAS o formulário Twig ---
Toolbox::logInFile("debug", "[Config Page Hybrid] Attempting to render Twig FORM template...");
$template_renderer = TemplateRenderer::getInstance();
if ($template_renderer === null) {
     Html::displayErrorAndDie("Erro crítico: Não foi possível obter o motor de templates.");
} else {
    try {
        // Usa um NOVO template que contém APENAS o <form>...</form>
        echo $template_renderer->render('@directlabelprinter/config_form_content.html.twig', $twig_data);
        Toolbox::logInFile("debug", "[Config Page Hybrid] Twig form template rendered successfully.");
    } catch (\Exception $e) {
        Toolbox::logInFile("error", "[Config Page Hybrid] Twig Form Rendering Error: " . $e->getMessage());
        Html::displayErrorAndDie("Erro ao renderizar template do formulário: " . $e->getMessage());
    }
}
// --- Fim Renderização Twig ---


// Footer Padrão GLPI
Html::footer();
Toolbox::logInFile("debug", "[Config Page Hybrid] Script finished.");

?>