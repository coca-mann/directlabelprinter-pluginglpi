<?php
// plugins/directlabelprinter/front/config.php

include ("../../../inc/includes.php");

use Toolbox;
use Html;
use Session;
use Glpi\Application\View\TemplateRenderer;
use Config as CoreConfig;
use Plugin;

Toolbox::logInFile("debug", "[Config Page Reverse Order] Script accessed. User ID: ". Session::getLoginUserID());

// Verificar Permissão
Session::checkRight('config', UPDATE);
Toolbox::logInFile("debug", "[Config Page Reverse Order] Passed checkRight.");

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
    Toolbox::logInFile("debug", "[Config Page Reverse Order] DB data fetched successfully.");
} catch (\Exception $e) { /* ... tratamento de erro DB ... */ }

// --- Preparação de Dados para Twig e Geração CSRF (Funcionando) ---
$csrf_token_name = 'plugin_directlabelprinter_config';
$csrf_token_value = '';
$twig_data = [];
try {
    $csrf_token_value = Session::getNewCSRFToken($csrf_token_name);
    $twig_data = [ /* ... dados existentes ... */ 'csrf_token' => $csrf_token_value ];
    Toolbox::logInFile("debug", "[Config Page Reverse Order] Twig data array prepared.");
} catch (\Exception $e) { /* ... tratamento de erro CSRF ... */ }


// --- Renderizar Twig para uma variável ANTES do Header ---
Toolbox::logInFile("debug", "[Config Page Reverse Order] Attempting to render Twig FORM template to variable...");
$twig_form_content = ''; // Inicializa a variável
$template_renderer = TemplateRenderer::getInstance();
if ($template_renderer === null) {
     Html::displayErrorAndDie("Erro crítico: Não foi possível obter o motor de templates.");
} else {
    try {
        // Renderiza para a variável, usando o template do formulário
        $twig_form_content = $template_renderer->render('@directlabelprinter/config_form_content.html.twig', $twig_data);
        Toolbox::logInFile("debug", "[Config Page Reverse Order] Twig form template rendered successfully to variable.");
    } catch (\Exception $e) {
        Toolbox::logInFile("error", "[Config Page Reverse Order] Twig Form Rendering Error: " . $e->getMessage());
        Html::displayErrorAndDie("Erro ao renderizar template do formulário: " . $e->getMessage());
    }
}
// --- Fim Renderização Twig para variável ---


// Header Padrão GLPI (APÓS renderizar Twig)
Html::header(__('Direct Label Printer Configuration', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins", __('Direct Label Printer', 'directlabelprinter'));
Toolbox::logInFile("debug", "[Config Page Reverse Order] Passed Html::header (after Twig render).");


// --- Bloco POST ainda comentado ---
// if (!empty($_POST)) { ... }


// --- Exibir o conteúdo Twig renderizado ---
Toolbox::logInFile("debug", "[Config Page Reverse Order] Echoing rendered Twig content...");
echo $twig_form_content; // Exibe o HTML que foi guardado na variável


// Footer Padrão GLPI
Html::footer();
Toolbox::logInFile("debug", "[Config Page Reverse Order] Script finished.");

?>