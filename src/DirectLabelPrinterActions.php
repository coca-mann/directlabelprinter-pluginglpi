<?php

namespace GlpiPlugin\Directlabelprinter;

use CommonDBTM;
use Html;
use MassiveAction;
use Session;
use GlpiPlugin\Directlabelprinter\Layout;
use GlpiPlugin\Directlabelprinter\LayoutItemtype;
use GlpiPlugin\Directlabelprinter\UserPref;

/**
 * Classe para lidar com as ações customizadas do plugin DirectLabelPrinter.
 */
class DirectLabelPrinterActions extends CommonDBTM // Estender CommonDBTM é um padrão, embora não usemos muito dele aqui
{
    // Right dedicado ao uso da ação "Imprimir Etiqueta", separado do right
    // 'plugin_directlabelprinter' (que controla apenas as telas de configuração de
    // servidores/layouts em PrintServer/Layout). Registrado em
    // plugin_directlabelprinter_install() via ProfileRight::addProfileRights() e checado em
    // hook.php::plugin_directlabelprinter_MassiveActions() e ajax/print.php.
    public static $rightname = 'plugin_directlabelprinter_print';

    /**
     * Imprimir etiqueta não é uma operação CRUD — só existe "pode usar" ou "não pode", então
     * expomos um único checkbox (bit READ) na matriz de perfis em vez da matriz
     * Criar/Ler/Atualizar/Excluir/Limpar padrão do CommonDBTM.
     */
    public function getRights($interface = 'central')
    {
        return [
            READ => __('Imprimir Etiquetas', 'directlabelprinter'),
        ];
    }

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

        // MassiveAction::getItems() retorna um mapa itemtype => [id => id, ...].
        // Nossa ação é registrada por itemtype (ver plugin_directlabelprinter_MassiveActions()
        // em hook.php), então há sempre exatamente um itemtype presente aqui — as tentativas
        // anteriores de obter o itemtype via getType()/getItemtype()/getItemType() etc. não
        // funcionavam porque MassiveAction não expõe nenhum desses métodos; o resultado sempre
        // caía no fallback 'Computer' e, pior, os IDs (no formato itemtype => [id => id]) eram
        // repassados sem conversão para resolveItemsForPrint(), que espera uma lista simples
        // [['id' => X], ...] — isso lançava um erro (chave 'id' inexistente) que truncava a
        // resposta AJAX, deixando o modal nativo do GLPI vazio.
        $items_by_itemtype = $massive_action->getItems();
        $itemtype = array_key_first($items_by_itemtype) ?: 'Computer';
        $item_ids = array_keys($items_by_itemtype[$itemtype] ?? []);
        $items_raw = array_map(static fn($id) => ['id' => $id], $item_ids);

        switch ($action_key) {
            case 'print_label':
                // Obter layouts associados ao itemtype (Task 5: Layout / LayoutItemtype)
                $layout_options = [];
                $default_layout_id = LayoutItemtype::getDefaultLayoutId($itemtype);
                foreach (LayoutItemtype::getLayoutsForItemtype($itemtype) as $layouts_id) {
                    $layout = new Layout();
                    if ($layout->getFromDB($layouts_id)) {
                        $layout_options[] = ['id' => $layouts_id, 'name' => $layout->fields['name']];
                    }
                }

                // Obter servidores de impressão e a preferência salva do usuário (Task 11)
                global $DB;
                $server_options = [];
                foreach ($DB->request(['FROM' => 'glpi_plugin_directlabelprinter_printservers']) as $row) {
                    $server_options[] = ['id' => (int) $row['id'], 'name' => $row['name']];
                }
                $default_server_id = UserPref::getPreferredServerId(\Session::getLoginUserID());

                // ---> Preparar dados dos itens para o JS <---
                $items_for_js = self::resolveItemsForPrint($itemtype, $items_raw);


                // Renderiza os campos DIRETAMENTE dentro do modal nativo de ação em massa do
                // GLPI (em vez de construir um modal próprio via JS) — GLPI já abre e gerencia
                // um modal para exibir o retorno deste método; um segundo modal construído via
                // JS ficava sobreposto ao nativo (vazio, na frente) e causava dois modais na tela.
                echo "<div class='dlp-inline-print-form'>";
                echo "<p><label for='dlp-layout-select'>" . __('Layout', 'directlabelprinter') . ":</label><br>";
                echo "<select id='dlp-layout-select' class='form-select'>";
                foreach ($layout_options as $opt) {
                    $selected = ((int) $opt['id'] === (int) $default_layout_id) ? ' selected' : '';
                    echo "<option value='" . (int) $opt['id'] . "'$selected>" . htmlspecialchars($opt['name']) . "</option>";
                }
                echo "</select></p>";

                echo "<p><label for='dlp-server-select'>" . __('Servidor de Impressão', 'directlabelprinter') . ":</label><br>";
                echo "<select id='dlp-server-select' class='form-select'>";
                if (empty($server_options)) {
                    echo "<option value='' disabled selected>" . __('Nenhum servidor de impressão configurado', 'directlabelprinter') . "</option>";
                }
                foreach ($server_options as $opt) {
                    $selected = ((int) $opt['id'] === (int) $default_server_id) ? ' selected' : '';
                    echo "<option value='" . (int) $opt['id'] . "'$selected>" . htmlspecialchars($opt['name']) . "</option>";
                }
                echo "</select></p>";

                echo "<p><label><input type='checkbox' id='dlp-remember-server'> " . __('Lembrar este servidor', 'directlabelprinter') . "</label></p>";
                echo "<div id='dlp-status-message' style='margin-top:10px;padding:10px;border-radius:4px;display:none;'></div>";
                echo "<p><button type='button' id='dlp-send-btn' class='btn btn-primary'>" . __('Enviar', 'directlabelprinter') . "</button></p>";
                echo "</div>";

                // Nota: este bloco é injetado via AJAX numa página já carregada (o sub-formulário
                // de ação em massa do GLPI), então NÃO deve esperar por 'DOMContentLoaded' — esse
                // evento já disparou muito antes deste <script> ser inserido no DOM.
                $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
                echo "<script type='text/javascript'>\n";
                echo "   if (typeof window.directLabelPrinter === 'object' && typeof window.directLabelPrinter.initInlinePrintForm === 'function') {\n";
                echo "       window.directLabelPrinter.initInlinePrintForm(" .
                          json_encode($itemtype, $json_flags) . ", " .
                          json_encode($items_for_js, $json_flags) .
                     ");\n";
                echo "   }\n";
                echo "</script>\n";

                return true;
        }
        return parent::showMassiveActionsSubForm($massive_action);
    }

    /**
     * @param array<int,array{id:int}> $ids Massive-action-style id list, e.g. [['id'=>1], ['id'=>2]]
     * @return array<int,array{id:int,titulo:string,url:string,ref:?string}>
     */
    public static function resolveItemsForPrint(string $itemtype, array $ids): array
    {
        global $CFG_GLPI;
        // CommonDBTM::getLinkURL() returns a root-relative path (e.g.
        // "/front/computer.form.php?id=1"), no scheme/host — a QR code encoding just that
        // isn't a usable link when scanned from a phone camera outside the browser session.
        $base_url = rtrim($CFG_GLPI['url_base'] ?? '', '/');

        $resolved = [];
        $item_obj = new $itemtype();
        foreach ($ids as $item_info) {
            $id = is_array($item_info) ? $item_info['id'] : $item_info;
            if ($item_obj->getFromDB($id) && $item_obj->can($id, READ)) {
                $link = $item_obj->getLinkURL() ?? '';
                $resolved[] = [
                    'id'     => (int) $id,
                    'titulo' => $item_obj->fields['name'] ?? ('Item ' . $id),
                    'url'    => $link !== '' ? $base_url . $link : '',
                    'ref'    => $item_obj->fields['otherserial'] ?? $item_obj->fields['serial'] ?? null,
                ];
            } else {
                \Toolbox::logInFile("directlabelprinter", sprintf("Item %s:%d não encontrado ou sem permissão para ação de impressão.", $itemtype, $id));
            }
        }
        return $resolved;
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

}