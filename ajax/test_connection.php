<?php
// ajax/test_connection.php
define('DO_NOT_CHECK_HTTP_REFERER', 1); // Simplificação, considere CSRF

include ("../../../inc/includes.php");

header('Content-Type: application/json');
Session::checkLoginUser(); // Garante que o usuário GLPI está logado
// Session::checkRight('config', UPDATE); // Verifica se tem permissão para configurar

use Glpi\Toolbox\DbUtils;
use Glpi\Toolbox\Sanitizer;

$response = ['success' => false, 'message' => ''];

try {
    // Obter dados do POST (enviados pelo JavaScript)
    // Usar Sanitizer para pegar os dados de forma segura
    $input = Sanitizer::getPostVariable('config'); // Espera um array 'config'
    if (empty($input) || !is_array($input) || empty($input['api_url']) || empty($input['api_user']) || empty($input['api_password'])) {
        throw new \Exception(__('Dados de autenticação incompletos.', 'directlabelprinter'));
    }

    $api_url = rtrim($input['api_url'], '/'); // Remove barra final se houver
    $username = $input['api_user'];
    $password = $input['api_password'];

    $token_endpoint = $api_url . '/api/auth/token/';

    // Usar GuzzleHttp (se disponível no GLPI) ou cURL para a chamada HTTP
    // Exemplo com cURL (mais comum em ambientes variados)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $username, 'password' => $password]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    // Adicionar opções para timeout, verificar SSL, etc.
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout de 15 segundos
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Descomente APENAS para testes em ambiente não seguro

    $api_response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new \Exception("Erro na comunicação cURL: " . $curl_error);
    }

    if ($http_code != 200) {
        // Tenta decodificar a resposta para obter mais detalhes do erro
        $error_details = json_decode($api_response_body, true);
        $error_message = isset($error_details['detail']) ? $error_details['detail'] : "Erro na API ($http_code)";
        throw new \Exception($error_message);
    }

    $tokens = json_decode($api_response_body, true);
    if (empty($tokens['access']) || empty($tokens['refresh'])) {
        throw new \Exception('Resposta da API inválida: tokens não encontrados.');
    }

    // Calcular expiração do Access Token (60 minutos = 3600 segundos)
    $expires_timestamp = time() + 3600; // Tempo atual + 60 minutos
    $expires_datetime = date('Y-m-d H:i:s', $expires_timestamp);

    // Salvar/Atualizar no banco de dados (assumindo uma única linha de config)
    $dbu = new DbUtils();
    $auth_table = 'glpi_plugin_directlabelprinter_auth';
    $existing = $dbu->getAllDataFromTable($auth_table, ['LIMIT' => 1]);

    $data_to_save = [
        'user' => $username,
        // NÃO salvar a senha em plain text após obter o token é mais seguro
        // 'password' => $password, // REMOVIDO por segurança
        'access_token' => $tokens['access'],
        'refresh_token' => $tokens['refresh'],
        'access_token_expires' => $expires_datetime
    ];

    global $DB; // Acesso direto ao DB para insert/update simples
    if (empty($existing)) {
        // Inserir
        $DB->insert($auth_table, $data_to_save);
    } else {
        // Atualizar
        $DB->update($auth_table, $data_to_save, ['id' => $existing[0]['id']]);
    }

    // Salvar URL, User na config principal também (sem a senha)
    $core_config_data = [
        'api_url' => $api_url,
        'api_user' => $username,
        // 'api_password' => '' // Limpa a senha da config principal após sucesso
    ];
    CoreConfig::setConfigurationValues('plugin:directlabelprinter', $core_config_data);


    $response['success'] = true;
    $response['message'] = __('Autenticação bem-sucedida! Tokens salvos.', 'directlabelprinter');

} catch (\Exception $e) {
    http_response_code(400); // Bad Request ou erro interno
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit();
?>