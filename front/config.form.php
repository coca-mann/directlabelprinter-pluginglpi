<?php
// front/config.form.php

include ("../../../inc/includes.php");

// Importar a classe de configuração
use GlpiPlugin\Directlabelprinter\Config;

// Instanciar o objeto de configuração
$config_item = new Config();
$config_id = Config::getOrCreateDefaultConfig(); // Garante que ID 1 exista

if (isset($_POST['update_defaults']) || isset($_POST['test_connection']) || isset($_POST['fetch_layouts'])) {
    // Verificar CSRF (CommonDBTM::check() faz isso, mas podemos ser explícitos se necessário)
    // $config_item->check($_POST['id'], 'update', $_POST); // Verifica permissão e CSRF

    // O CommonDBTM::check() pode ser complexo. Vamos verificar permissão e CSRF manualmente.
    Session::checkLoginUser();
    if (Session::haveRight(Config::$rightname, UPDATE)) {
        // Validar CSRF token
        Session::checkCSRF($config_item->getCsrfTokenName('config'), $_POST['_glpi_csrf_token']);

        // O CommonDBTM espera que o ID esteja em $_POST['id']
        $_POST['id'] = $config_id;
        
        // Chamar o método de atualização do CommonDBTM, que por sua vez
        // chamará o nosso hook post_update() dentro da classe Config
        $config_item->update($_POST);
    }
    
    // Redirecionar de volta para o formulário
    Html::redirect($config_item->getFormURL(['id' => $config_id]));

} else {
    // --- Lógica de Exibição GET ---
    Html::header(Config::getTypeName(), $_SERVER['PHP_SELF'], "config", "plugins", Config::getTypeName());
    
    // O método display() irá carregar o item (ID 1) e depois chamar showForm()
    $config_item->display(['id' => $config_id]);
    
    // Html::footer() é chamado dentro de showForm()
}
?>