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

// Só reaproveita a chave salva quando a URL testada é a MESMA já cadastrada para esse
// servidor — sem isso, um usuário com apenas READ neste right podia informar o id de
// QUALQUER print server já cadastrado junto de uma url própria, e o backend descriptografava
// e enviava a chave real daquele servidor para a url arbitrária do atacante (exfiltração via
// SSRF, contornando por completo o mascaramento de 'secured_fields' na UI).
if ($api_key === '' && $id) {
    $server = new PrintServer();
    if ($server->getFromDB($id) && rtrim($server->fields['url'] ?? '', '/') === rtrim($url, '/')) {
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
