<?php
// ajax/printserver_fetch_printers.php

include("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\PrintServer;
use GlpiPlugin\Directlabelprinter\PrintServiceClient;

header('Content-Type: application/json');

if (!PrintServer::canView()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => __('Acesso negado.', 'directlabelprinter')]);
    exit;
}

// Mesma lógica de ajax/printserver_test.php: usa os valores atuais do formulário (mesmo
// antes de salvar), recorrendo ao banco só para a chave de API quando o campo em texto
// plano vem vazio (= "manter a chave salva").
$id = (int) ($_POST['id'] ?? 0);
$url = trim((string) ($_POST['url'] ?? ''));
$api_key = (string) ($_POST['api_key_plain'] ?? '');

if ($url === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => __('Informe a URL do servidor.', 'directlabelprinter')]);
    exit;
}

if ($api_key === '' && $id) {
    $server = new PrintServer();
    if ($server->getFromDB($id)) {
        $api_key = $server->getDecryptedApiKey();
    }
}

$client = PrintServiceClient::fromCredentials($url, $api_key);
$result = $client->listPrinters();
echo json_encode([
    'success'  => $result['success'],
    'message'  => $result['message'],
    'printers' => $result['printers'],
]);
