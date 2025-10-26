<?php
// ajax/fetch_layouts.php
define('DO_NOT_CHECK_HTTP_REFERER', 1);

include ("../../../inc/includes.php");

header('Content-Type: application/json');
Session::checkLoginUser();
// Session::checkRight('config', UPDATE); // Ou direito específico

use Glpi\Toolbox\DbUtils;
use GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions; // Para usar a função auxiliar

$response = ['success' => false, 'message' => ''];

try {
    // 1. Chamar a API usando a função auxiliar
    $layouts_from_api = DirectLabelPrinterActions::makeAuthenticatedApiRequest('GET', '/api/layouts/');

    // 2. Processar a resposta e salvar no BD
    if (!is_array($layouts_from_api)) {
        throw new \Exception("Resposta inesperada da API de layouts.");
    }

    global $DB;
    $layout_table = 'glpi_plugin_directlabelprinter_layouts';

    // Limpar tabela atual antes de inserir novos (ou fazer UPSERT se preferir)
    // CUIDADO: Isso remove layouts que podem ter sido removidos da API
    $DB->query("TRUNCATE TABLE `$layout_table`"); // Simples, mas pode ser destrutivo

    $default_found_in_api = false;
    foreach ($layouts_from_api as $layout_data) {
        // Mapear dados da API para colunas do BD
        $data_to_insert = [
            'id_api' => $layout_data['id'] ?? null,
            'nome' => $layout_data['nome'] ?? null,
            'descricao' => $layout_data['descricao'] ?? null,
            'largura_mm' => $layout_data['largura_mm'] ?? null,
            'altura_mm' => $layout_data['altura_mm'] ?? null,
            'altura_titulo_mm' => $layout_data['altura_titulo_mm'] ?? null,
            'tamanho_fonte_titulo' => $layout_data['tamanho_fonte_titulo'] ?? null,
            'margem_vertical_qr_mm' => $layout_data['margem_vertical_qr_mm'] ?? null,
            // Cuidado com o caminho do arquivo, talvez só o nome seja relevante
            'nome_fonte' => $layout_data['nome_fonte_reportlab'] ?? basename($layout_data['arquivo_fonte'] ?? ''),
            'padrao' => ($layout_data['padrao'] ?? false) ? 1 : 0
        ];

        if ($data_to_insert['padrao'] == 1) {
             $default_found_in_api = true;
        }

        // Inserir no BD
        if ($data_to_insert['id_api'] !== null) { // Só insere se tiver ID da API
             $DB->insert($layout_table, $data_to_insert);
        } else {
             Toolbox::logWarning("Layout da API sem ID recebido: " . json_encode($layout_data));
        }
    }

    // Se a API não retornou um padrão, ou se limpamos a tabela,
    // talvez marcar o primeiro layout como padrão no GLPI? Ou deixar sem padrão?
    // Vamos deixar sem padrão por enquanto, o usuário pode definir na config.


    $response['success'] = true;
    $response['message'] = __('Layouts buscados e atualizados com sucesso!', 'directlabelprinter');

} catch (\Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit();
?>