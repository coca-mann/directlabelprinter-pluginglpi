<?php
// plugins/directlabelprinter/front/config.php

// 1. Incluir GLPI (Essencial)
include ("../../../inc/includes.php");

// Use Toolbox para log, Html para header/footer
use Toolbox;
use Html;
use Session; // Ainda precisamos de Session para checkRight

Toolbox::logInFile("debug", "[Config Page Minimal] Script accessed. User ID: " . Session::getLoginUserID());

// 2. Verificar Permissão (Mínimo de Segurança)
// Se isto causar 403, sabemos que é permissão. Se causar 400, é outra coisa.
// Vamos manter descomentado por enquanto. Se ainda der 400, comente esta linha também.
Session::checkRight('config', UPDATE);
Toolbox::logInFile("debug", "[Config Page Minimal] Passed checkRight.");

// 3. Header Básico
// Usar Html::header() mas sem o último parâmetro 'context' que pode não ser esperado aqui
Html::header(__('Direct Label Printer Configuration (Minimal Test)', 'directlabelprinter'), $_SERVER['PHP_SELF'], "config", "plugins");
Toolbox::logInFile("debug", "[Config Page Minimal] Passed Html::header.");

// --- TODA A LÓGICA POST, BUSCA DB, TWIG REMOVIDA ---

// 4. Exibir uma Mensagem Simples
echo "<h1>Teste da Página de Configuração</h1>";
echo "<p>Se você vê esta mensagem, o script PHP básico executou.</p>";
Toolbox::logInFile("debug", "[Config Page Minimal] Displaying simple message.");


// 5. Footer Básico
Html::footer();
Toolbox::logInFile("debug", "[Config Page Minimal] Script finished.");

?>