<?php
// ajax/print.php

include("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\AssetTypes;
use GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions;
use GlpiPlugin\Directlabelprinter\Layout;
use GlpiPlugin\Directlabelprinter\LayoutItemtype;
use GlpiPlugin\Directlabelprinter\Pdf\LabelPdfBuilder;
use GlpiPlugin\Directlabelprinter\PrintServer;
use GlpiPlugin\Directlabelprinter\PrintServiceClient;
use GlpiPlugin\Directlabelprinter\UserPref;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$itemtype = $input['itemtype'] ?? '';
$ids = $input['items'] ?? [];
$layout_id = isset($input['layout_id']) ? (int) $input['layout_id'] : null;
$printserver_id = isset($input['printserver_id']) ? (int) $input['printserver_id'] : null;
$remember_server = (bool) ($input['remember_server'] ?? false);

if (!in_array($itemtype, AssetTypes::WHITELIST, true) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => __('Requisição inválida.', 'directlabelprinter')]);
    exit;
}

if (!class_exists($itemtype) || !(new $itemtype())->can(0, READ)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => __('Acesso negado.', 'directlabelprinter')]);
    exit;
}

$layout_id = $layout_id ?: LayoutItemtype::getDefaultLayoutId($itemtype);
$layout = new Layout();
if (!$layout_id || !$layout->getFromDB($layout_id)) {
    echo json_encode(['success' => false, 'message' => __('Nenhum layout de etiqueta disponível para este tipo de ativo.', 'directlabelprinter')]);
    exit;
}

$printserver_id = $printserver_id ?: UserPref::getPreferredServerId(\Session::getLoginUserID());
$server = new PrintServer();
if (!$printserver_id || !$server->getFromDB($printserver_id)) {
    echo json_encode(['success' => false, 'message' => __('Nenhum servidor de impressão selecionado.', 'directlabelprinter')]);
    exit;
}
if (empty($server->fields['default_printer_name'])) {
    echo json_encode(['success' => false, 'message' => __('O servidor de impressão selecionado não possui uma impressora padrão configurada.', 'directlabelprinter')]);
    exit;
}

$items = DirectLabelPrinterActions::resolveItemsForPrint($itemtype, $ids);
if (empty($items)) {
    echo json_encode(['success' => false, 'message' => __('Nenhum item válido para impressão.', 'directlabelprinter')]);
    exit;
}

try {
    $pdf_bytes = (new LabelPdfBuilder($layout))->render($items);
    $result = (new PrintServiceClient($server))->printPdf($pdf_bytes, $server->fields['default_printer_name']);
} catch (\Throwable $e) {
    \Toolbox::logInFile('directlabelprinter', 'Print dispatch failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => __('Ocorreu um erro inesperado ao gerar ou enviar a etiqueta.', 'directlabelprinter')]);
    exit;
}

if ($result['success'] && $remember_server) {
    UserPref::setPreferredServer(\Session::getLoginUserID(), (int) $server->fields['id']);
}

echo json_encode([
    'success' => $result['success'],
    'message' => $result['message'] ?: __('Etiqueta(s) enviada(s) para impressão com sucesso.', 'directlabelprinter'),
]);
