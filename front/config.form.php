<?php
// front/config.form.php

include ("../../../inc/includes.php");

// Importar a classe de configuração
use GlpiPlugin\Directlabelprinter\Config;
use Html;
use Session;

// Instanciar o objeto de configuração
$config_item = new Config();
// Garante que ID 1 exista e obtém o ID
$config_id = Config::getOrCreateDefaultConfig(); 

if (!empty($_POST)) {
    // Verificar Permissão
    $config_item->check($config_id, 'update', $_POST); // Verifica permissão E CSRF
    
    // O CommonDBTM::update() irá:
    // 1. Preencher $config_item->input
    // 2. Chamar $config_item->post_update()
    // 3. (Opcional) Fazer o update DB padrão (que não fazemos)
    $config_item->update($_POST);
    
    // Redirecionar de volta para o formulário
    Html::redirect($config_item->getFormURL(['id' => $config_id]));

} else {
    // --- Lógica de Exibição GET ---
    Html::header(Config::getTypeName(), $_SERVER['PHP_SELF'], "config", "plugins", Config::getTypeName());
    
    // O método display() irá carregar o item (ID 1) e depois chamar showForm()
    $config_item->display(['id' => $config_id]);
    
    // Html::footer() é chamado dentro do nosso showForm() agora
}
?>