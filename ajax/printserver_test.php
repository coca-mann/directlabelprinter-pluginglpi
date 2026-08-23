<?php
// ajax/printserver_test.php

include("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\PrintServer;
use GlpiPlugin\Directlabelprinter\PrintServiceClient;

header('Content-Type: application/json');

if (!PrintServer::canView()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => __('Access denied.', 'directlabelprinter')]);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$server = new PrintServer();
if (!$id || !$server->getFromDB($id)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => __('Print server not found.', 'directlabelprinter')]);
    exit;
}

$client = new PrintServiceClient($server);
$result = $client->test();
echo json_encode(['success' => $result['success'], 'message' => $result['message'] ?: __('Connection successful.', 'directlabelprinter')]);
