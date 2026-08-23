<?php

namespace GlpiPlugin\Directlabelprinter;

use CommonDBTM;
use Html;
use MassiveAction;
use Session;
use Toolbox;
use DateTime;
use GlpiPlugin\Directlabelprinter\Layout;
use GlpiPlugin\Directlabelprinter\LayoutItemtype;
use GlpiPlugin\Directlabelprinter\UserPref;

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


                // Incluir JavaScript para abrir o modal (código existente, mas passando $items_for_js)
                echo "<script type='text/javascript'>\n";
                echo "document.addEventListener('DOMContentLoaded', function() {\n";
                echo "   if (typeof window.directLabelPrinter === 'object' && typeof window.directLabelPrinter.openPrintModal === 'function') {\n";
                echo "       window.directLabelPrinter.openPrintModal(" .
                          json_encode($itemtype) . ", " .
                          json_encode($items_for_js) . ", " . // Passa o array com mais dados
                          json_encode($layout_options) . ", " .
                          json_encode($default_layout_id) . ", " .
                          json_encode($server_options) . ", " .
                          json_encode($default_server_id) .
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
     * @param array<int,array{id:int}> $ids Massive-action-style id list, e.g. [['id'=>1], ['id'=>2]]
     * @return array<int,array{id:int,titulo:string,url:string,ref:?string}>
     */
    public static function resolveItemsForPrint(string $itemtype, array $ids): array
    {
        $resolved = [];
        $item_obj = new $itemtype();
        foreach ($ids as $item_info) {
            $id = is_array($item_info) ? $item_info['id'] : $item_info;
            if ($item_obj->getFromDB($id)) {
                $resolved[] = [
                    'id'     => (int) $id,
                    'titulo' => $item_obj->fields['name'] ?? ('Item ' . $id),
                    'url'    => $item_obj->getLinkURL() ?? '',
                    'ref'    => $item_obj->fields['otherserial'] ?? $item_obj->fields['serial'] ?? null,
                ];
            } else {
                \Toolbox::logInFile("directlabelprinter", sprintf("Item %s:%d não encontrado para ação de impressão.", $itemtype, $id));
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