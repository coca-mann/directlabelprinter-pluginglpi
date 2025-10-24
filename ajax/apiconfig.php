<?php
// ajax/apiconfig.php

// Define DO_NOT_CHECK_HTTP_REFERER para permitir chamadas AJAX
// (Faça isso com cautela, considere verificar a origem ou usar CSRF tokens mais robustos)
// Alternativamente, passe o CSRF token do GLPI no header JS e valide aqui.
define('DO_NOT_CHECK_HTTP_REFERER', 1); // Simplificação para agora

// Inclui o básico do GLPI (sem header/footer HTML)
include ("../../../inc/includes.php");

// Garante que a resposta seja JSON
header('Content-Type: application/json');

// Verifica se o usuário está logado e tem permissão (MUITO IMPORTANTE!)
// Use um direito apropriado, talvez um direito customizado do plugin ou 'config'
Session::checkLoginUser();
// Session::checkRight('plugin_directlabelprinter_config', READ); // Exemplo de direito customizado

$response = [];

try {
    // Busca a URL da API da configuração do plugin
    $plugin_config = \GlpiPlugin\Directlabelprinter\Config::getConfigValues();
    $api_url = $plugin_config['api_url'] ?? null;

    if (empty($api_url)) {
        throw new \Exception(__('URL da API não configurada.', 'directlabelprinter'));
    }

    // Busca o token de acesso do banco de dados
    // Assumindo que só há uma linha na tabela de autenticação
    $dbu = new \Glpi\Toolbox\DbUtils();
    $auth_data = $dbu->getAllDataFromTable('glpi_plugin_directlabelprinter_auth', ['LIMIT' => 1]);

    if (empty($auth_data) || empty($auth_data[0]['access_token'])) {
         throw new \Exception(__('Token de acesso não encontrado. Realize o teste de conexão na configuração.', 'directlabelprinter'));
    }
    $access_token = $auth_data[0]['access_token'];

    // Prepara a resposta de sucesso
    $response['api_url'] = $api_url;
    $response['access_token'] = $access_token;

} catch (\Exception $e) {
    // Prepara a resposta de erro
    http_response_code(500); // Define código de erro HTTP
    $response['error'] = $e->getMessage();
}

// Envia a resposta JSON
echo json_encode($response);

// Finaliza a execução do script AJAX
exit();
?>