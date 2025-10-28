<?php
// plugins/directlabelprinter/front/config.php

include ("../../../inc/includes.php");

use Toolbox;
use Html;
use Session;
use Glpi\Application\View\TemplateRenderer; // Adicionar se for usar Twig depois
use Config as CoreConfig; // Adicionar se for usar depois

Toolbox::logInFile("debug", "[Config Page Step 2] Script accessed. User ID: " . Session::getLoginUserID());

// Verificar Permissão
Session::checkRight('config', UPDATE);
Toolbox::logInFile("debug", "[Config Page Step 2] Passed checkRight.");

// Header
Html::header(__('Direct Label Printer Configuration (DB Test)', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins");
Toolbox::logInFile("debug", "[Config Page Step 2] Passed Html::header.");

// --- Bloco POST ainda comentado ---
// if (!empty($_POST)) { ... }

// --- Reintroduzir Busca de Dados DB ---
global $DB; // Acesso ao DB
$auth_table = 'glpi_plugin_directlabelprinter_auth';
$layouts_table = 'glpi_plugin_directlabelprinter_layouts';
$config_page_url = Plugin::getWebDir('directlabelprinter', true) . "/front/config.php"; // Manter para uso futuro

Toolbox::logInFile("debug", "[Config Page Step 2] Attempting DB requests...");
try {
    // Obter dados atuais para exibir no formulário (mesmo que ainda não usemos)
    $current_auth_result = $DB->request([
        'FROM' => $auth_table,
        'LIMIT' => 1
    ]);
    $current_auth = $current_auth_result->current() ?? [];
    Toolbox::logInFile("debug", "[Config Page Step 2] Auth data fetched (or empty array).");

    $layouts_result = $DB->request([
        'FROM' => $layouts_table
    ]);
    $layouts_from_db = [];
    foreach ($layouts_result as $layout) { // Iterar sobre o resultado
        $layouts_from_db[] = $layout;
    }
    Toolbox::logInFile("debug", "[Config Page Step 2] Layouts data fetched (Count: " . count($layouts_from_db) . ").");

    $layout_options = [];
    $default_layout_id_api = null;
    foreach ($layouts_from_db as $layout) {
        $layout_options[$layout['id_api']] = $layout['nome'];
        if ($layout['padrao'] == 1) {
            $default_layout_id_api = $layout['id_api'];
        }
    }
    Toolbox::logInFile("debug", "[Config Page Step 2] Layout options prepared.");

} catch (\Exception $e) {
    // Capturar erros potenciais do DB e exibir/logar
    Toolbox::logInFile("error", "[Config Page Step 2] DB Error: " . $e->getMessage());
    Html::displayErrorAndDie("Erro ao aceder ao banco de dados: " . $e->getMessage());
}
// --- Fim da Busca de Dados DB ---


// --- Renderização Twig e Geração CSRF ainda comentadas ---
// $twig_data = [ ... ];
// try { echo $template_renderer->render(...); } catch { ... }

// Exibir uma Mensagem Simples
echo "<h1>Teste da Página de Configuração - Passo 2 (DB)</h1>";
echo "<p>Se você vê esta mensagem, as buscas no banco de dados foram executadas (verifique os logs).</p>";
echo "<pre>Auth Data: " . print_r($current_auth, true) . "</pre>"; // Mostrar dados obtidos
echo "<pre>Layouts Count: " . count($layouts_from_db) . "</pre>";
Toolbox::logInFile("debug", "[Config Page Step 2] Displaying simple message.");


// Footer
Html::footer();
Toolbox::logInFile("debug", "[Config Page Step 2] Script finished.");

?>