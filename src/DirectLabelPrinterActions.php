<?php

namespace GlpiPlugin\Directlabelprinter;

use CommonDBTM;
use Html;
use MassiveAction;
use Session;
use Toolbox;
use DateTime;

/**
 * Classe para lidar com as ações customizadas do plugin DirectLabelPrinter.
 */
class DirectLabelPrinterActions extends CommonDBTM // Estender CommonDBTM é um padrão, embora não usemos muito dele aqui
{
    /**
     * Exibe o sub-formulário para a ação em massa (ou individual).
     * Neste caso, vamos usar JS para abrir um modal em vez de mostrar um formulário HTML direto.
     *
     * @param MassiveAction $massive_action Objeto MassiveAction
     *
     * @return bool True se a ação foi tratada
     */
    static function showMassiveActionsSubForm(MassiveAction $massive_action) {
        $action_key = $massive_action->getAction();
        // Obter o itemtype de forma mais robusta
        $itemtype = null;
        
        // Tentar diferentes abordagens para obter o itemtype
        if (method_exists($massive_action, 'getType')) {
            $itemtype = $massive_action->getType();
        } elseif (method_exists($massive_action, 'getItemtype')) {
            $itemtype = $massive_action->getItemtype(true); // Passa true para incluir namespace da classe
        } elseif (method_exists($massive_action, 'getItemType')) {
            $itemtype = $massive_action->getItemType();
        } elseif (property_exists($massive_action, 'itemtype') && isset($massive_action->itemtype)) {
            $itemtype = $massive_action->itemtype;
        } elseif (method_exists($massive_action, 'getCurrentItemType')) {
            $itemtype = $massive_action->getCurrentItemType();
        } else {
            // Fallback: tentar obter do contexto da requisição
            if (isset($_REQUEST['itemtype'])) {
                $itemtype = $_REQUEST['itemtype'];
            } else {
                // Último recurso: usar Computer como padrão
                $itemtype = 'Computer';
                Toolbox::logInFile("directlabelprinter", "DirectLabelPrinter: Não foi possível determinar o itemtype, usando 'Computer' como padrão");
            }
        }
        $items_raw = $massive_action->getItems(); // Array de ['id' => X]

        switch ($action_key) {
            case 'print_label':
                // Obter layouts (código corrigido)
                global $DB;
                $iterator = $DB->request([
                    'FROM' => 'glpi_plugin_directlabelprinter_layouts'
                ]);
                $layout_options = [];
                $default_layout_id = null;
                foreach ($iterator as $layout) {
                    $layout_options[] = ['id' => $layout['id_api'], 'name' => $layout['nome']];
                    if ($layout['padrao'] == 1) {
                        $default_layout_id = $layout['id_api'];
                    }
                }

                // ---> Preparar dados dos itens para o JS <---
                $items_for_js = [];
                if (!empty($items_raw)) {
                     // Precisamos instanciar o objeto para pegar nome e URL
                     $item_obj = new $itemtype(); // Instancia a classe correta (Computer, Monitor, etc.)
                     foreach($items_raw as $item_info) {
                         if ($item_obj->getFromDB($item_info['id'])) {
                             $items_for_js[] = [
                                 'id' => $item_info['id'],
                                 'name' => $item_obj->fields['name'] ?? ('Item ' . $item_info['id']), // Fallback
                                 'url' => $item_obj->getLinkURL() ?? '' // Pega a URL do item
                             ];
                         } else {
                              // Item não encontrado, talvez logar um aviso
                              Toolbox::logInFile("directlabelprinter", sprintf("Item %s:%d não encontrado para ação de impressão.", $itemtype, $item_info['id']));
                         }
                     }
                }
                // Limpa o objeto após o uso
                unset($item_obj);


                // Incluir JavaScript para abrir o modal (código existente, mas passando $items_for_js)
                echo "<script type='text/javascript'>\n";
                echo "document.addEventListener('DOMContentLoaded', function() {\n";
                echo "   if (typeof window.directLabelPrinter === 'object' && typeof window.directLabelPrinter.openPrintModal === 'function') {\n";
                echo "       window.directLabelPrinter.openPrintModal(" .
                          json_encode($itemtype) . ", " .
                          json_encode($items_for_js) . ", " . // Passa o array com mais dados
                          json_encode($layout_options) . ", " .
                          json_encode($default_layout_id) .
                     ");\n";
                 // ... (restante do código JS para erro) ...
                echo "   }\n";
                echo "});\n";
                echo "</script>\n";

                return true;
        }
        return parent::showMassiveActionsSubForm($massive_action);
    }

    /**
     * Processa a ação para um tipo de item (geralmente usado para salvar dados no GLPI).
     * No nosso caso, a chamada real para a API de impressão será feita via AJAX/JavaScript.
     * Esta função pode ser usada para registrar logs ou confirmar que a ação foi iniciada.
     *
     * @param MassiveAction $massive_action Objeto MassiveAction
     * @param CommonDBTM    $item           Instância do tipo de item
     * @param array         $ids            Array de IDs dos itens selecionados
     *
     * @return void
     */
    static function processMassiveActionsForOneItemtype(MassiveAction $massive_action, CommonDBTM $item, array $ids) {
        $action_key = $massive_action->getAction();

        switch ($action_key) {
            case 'print_label':
                // A lógica principal está no JavaScript (modal e chamada AJAX).
                // Aqui podemos apenas marcar os itens como "processados" para o feedback do GLPI, se necessário.
                foreach ($ids as $id) {
                     // Não há necessidade real de getFromDB aqui, a menos que precisemos de dados específicos
                     // O JS já terá os dados necessários ou fará buscas adicionais.

                    // Marca o item como OK no resumo da ação em massa do GLPI
                    $massive_action->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                }
                // Adiciona uma mensagem geral (opcional)
                // $massive_action->addMessage("Ação 'Imprimir Etiqueta' iniciada.");
                return; // Importante retornar aqui para não executar o processamento pai
        }

        // Se não for nossa ação, chama o processamento padrão do GLPI
        parent::processMassiveActionsForOneItemtype($massive_action, $item, $ids); // [cite: 4159]
    }

    /**
     * Função auxiliar centralizada para fazer chamadas à API DirectLabelPrinter,
     * lidando com autenticação e renovação de token.
     *
     * @param string $method Método HTTP (GET, POST, PATCH, etc.)
     * @param string $endpoint Caminho do endpoint (ex: '/layouts/', '/imprimir/')
     * @param array|null $payload Dados para enviar no corpo (para POST, PATCH, etc.)
     *
     * @return array Resposta decodificada da API
     * @throws \Exception Em caso de erro de autenticação ou API
     */
    public static function makeAuthenticatedApiRequest(string $method, string $endpoint, ?array $payload = null): array {
                global $DB; // Acesso global $DB

        // 1. Obter configuração e dados de autenticação
        $plugin_config = \GlpiPlugin\Directlabelprinter\Config::getConfigValues();
        $api_url = rtrim($plugin_config['api_url'] ?? '', '/');

        if (empty($api_url)) {
            throw new \Exception(__('URL da API não configurada.', 'directlabelprinter'));
        }

        $auth_table = 'glpi_plugin_directlabelprinter_auth';
        $auth_result = $DB->request([
            'FROM' => $auth_table,
            'LIMIT' => 1
        ]);
        $auth_data = $auth_result->current();

        if (empty($auth_data) || empty($auth_data['access_token']) || empty($auth_data['refresh_token'])) {
            throw new \Exception(__('Autenticação necessária. Use o "Testar Conexão" na configuração.', 'directlabelprinter'));
        }

        $auth_info = $auth_data;
        $access_token = $auth_info['access_token'];
        $refresh_token = $auth_info['refresh_token'];
        $expiry_str = $auth_info['access_token_expires'];
        $auth_id = $auth_info['id']; // ID da linha para update

        // 2. Verificar expiração do token de acesso
        $is_expired = true; // Assume expirado se não houver data
        if ($expiry_str) {
            try {
                // Adiciona uma margem de segurança (ex: 60 segundos)
                $expiry_time = new DateTime($expiry_str);
                $now = new DateTime('-60 seconds');
                if ($expiry_time > $now) {
                    $is_expired = false;
                }
            } catch (\Exception $e) {
                // Erro ao parsear data, assume expirado
                Toolbox::logWarning("Erro ao parsear data de expiração do token: " . $e->getMessage());
                $is_expired = true;
            }
        }

        // 3. Renovar token se expirado
        if ($is_expired) {
            Toolbox::logInFile("directlabelprinter", "Token de acesso expirado ou inválido, tentando renovar...");
            $refresh_endpoint = $api_url . '/api/auth/token/refresh/';
            $ch_refresh = curl_init();
            curl_setopt($ch_refresh, CURLOPT_URL, $refresh_endpoint);
            curl_setopt($ch_refresh, CURLOPT_POST, 1);
            curl_setopt($ch_refresh, CURLOPT_POSTFIELDS, json_encode(['refresh' => $refresh_token]));
            curl_setopt($ch_refresh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_refresh, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch_refresh, CURLOPT_TIMEOUT, 15);

            $refresh_response_body = curl_exec($ch_refresh);
            $refresh_http_code = curl_getinfo($ch_refresh, CURLINFO_HTTP_CODE);
            $refresh_curl_error = curl_error($ch_refresh);
            curl_close($ch_refresh);

            if ($refresh_curl_error || $refresh_http_code != 200) {
                 $refresh_error_details = json_decode($refresh_response_body, true);
                 $refresh_error_message = $refresh_error_details['detail'] ?? "Erro ao renovar token ($refresh_http_code)";
                 Toolbox::logInFile("error", "Falha ao renovar token: " . $refresh_error_message);
                 // Limpar tokens antigos pode ser uma opção aqui
                 // $DB->update($auth_table, ['access_token' => null, 'refresh_token' => null, 'access_token_expires' => null], ['id' => $auth_id]);
                throw new \Exception(__('Falha ao renovar token de acesso. Tente "Testar Conexão" novamente.', 'directlabelprinter'));
            }

            $new_tokens = json_decode($refresh_response_body, true);
            if (empty($new_tokens['access'])) {
                 Toolbox::logInFile("error", "Resposta de renovação de token inválida.");
                throw new \Exception(__('Resposta de renovação de token inválida.', 'directlabelprinter'));
            }

            $access_token = $new_tokens['access']; // Usa o novo token de acesso
            // Refresh token pode ou não ser retornado aqui, dependendo da config da API (rotação)
            $refresh_token = $new_tokens['refresh'] ?? $refresh_token; // Atualiza se um novo foi enviado

            // Calcular nova expiração e salvar
            $new_expires_timestamp = time() + 3600; // Assumindo 60 minutos
            $new_expires_datetime = date('Y-m-d H:i:s', $new_expires_timestamp);

            $DB->update($auth_table, [
                'access_token' => $access_token,
                'refresh_token' => $refresh_token, // Salva o novo refresh token se houver
                'access_token_expires' => $new_expires_datetime
            ], ['id' => $auth_id]);
            Toolbox::logInFile("directlabelprinter", "Token de acesso renovado com sucesso.");

        } // Fim da renovação

        // 4. Fazer a chamada à API desejada
        $target_url = $api_url . $endpoint;
        $ch_api = curl_init();
        curl_setopt($ch_api, CURLOPT_URL, $target_url);
        curl_setopt($ch_api, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_api, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token // Usa o token (novo ou antigo válido)
        ]);
        curl_setopt($ch_api, CURLOPT_TIMEOUT, 30); // Timeout maior para ações como imprimir

        // Configurar método e payload
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch_api, CURLOPT_POST, 1);
                if ($payload !== null) {
                    curl_setopt($ch_api, CURLOPT_POSTFIELDS, json_encode($payload));
                }
                break;
            case 'GET':
                // GET é o padrão
                break;
            case 'PATCH':
                 curl_setopt($ch_api, CURLOPT_CUSTOMREQUEST, 'PATCH');
                 if ($payload !== null) {
                     curl_setopt($ch_api, CURLOPT_POSTFIELDS, json_encode($payload));
                 }
                 break;
            // Adicionar PUT, DELETE se necessário
        }

        $api_response_body = curl_exec($ch_api);
        $api_http_code = curl_getinfo($ch_api, CURLINFO_HTTP_CODE);
        $api_curl_error = curl_error($ch_api);
        curl_close($ch_api);

        if ($api_curl_error) {
            throw new \Exception("Erro na chamada cURL para $endpoint: " . $api_curl_error);
        }

        $decoded_response = json_decode($api_response_body, true);

        // Verifica códigos de erro HTTP comuns após decodificar
        if ($api_http_code < 200 || $api_http_code >= 300) {
            $api_error_message = $decoded_response['detail'] ?? $decoded_response['message'] ?? "Erro na API $endpoint ($api_http_code)";
            throw new \Exception($api_error_message);
        }

        // Retorna a resposta decodificada (pode ser null se a resposta for vazia mas OK)
        return $decoded_response ?? []; // Retorna array vazio se a resposta for nula/vazia

    } // Fim de makeAuthenticatedApiRequest
}