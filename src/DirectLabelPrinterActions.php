<?php

namespace GlpiPlugin\Directlabelprinter;

use CommonDBTM;
use Html;
use MassiveAction;
use Session;

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
        $action_key = $massive_action->getAction(); // Obtém a chave da ação ('print_label')
        $itemtype = $massive_action->getItemtype(); // Obtém o tipo de item (ex: 'Computer')
        $items = $massive_action->getItems();       // Obtém os IDs dos itens selecionados (array)

        switch ($action_key) {
            case 'print_label':
                // Em vez de echo HTML, vamos preparar dados e disparar JavaScript

                // Obter layouts do banco de dados (necessário para o dropdown no modal)
                $dbu = new \Glpi\Toolbox\DbUtils();
                $layouts = $dbu->getAllDataFromTable('glpi_plugin_directlabelprinter_layouts');
                $layout_options = [];
                $default_layout_id = null;
                foreach ($layouts as $layout) {
                    $layout_options[] = ['id' => $layout['id_api'], 'name' => $layout['nome']]; // Formato para JS
                    if ($layout['padrao'] == 1) {
                        $default_layout_id = $layout['id_api'];
                    }
                }

                // Obter dados do item (ou do primeiro item, se for ação individual)
                $item_data = [];
                if (!empty($items) && count($items) === 1) { // Ação individual
                    $item_id = $items[0]['id'];
                    $item = new $itemtype(); // Instancia o tipo de item correto
                    if ($item->getFromDB($item_id)) {
                        $item_data = [
                            'id' => $item_id,
                            'name' => $item->fields['name'] ?? 'N/A', // Ou outro campo relevante
                            'url' => $item->getLinkURL() ?? '' // URL do item no GLPI
                        ];
                    }
                } else if (!empty($items)) { // Ação em massa (preparar múltiplos itens)
                   // A lógica para múltiplos itens será tratada principalmente no JS
                   // Mas podemos passar os IDs para o JS
                   $item_ids = array_column($items, 'id');
                   // Talvez buscar nomes se necessário, mas pode ficar lento
                }


                // Incluir JavaScript para abrir o modal
                echo "<script type='text/javascript'>\n";
                // Passar dados para o JavaScript de forma segura
                echo "document.addEventListener('DOMContentLoaded', function() {\n"; // Garante que o DOM está pronto
                echo "   if (typeof window.directLabelPrinter === 'object' && typeof window.directLabelPrinter.openPrintModal === 'function') {\n";
                echo "       window.directLabelPrinter.openPrintModal(" .
                          json_encode($itemtype) . ", " .
                          json_encode($items) . ", " . // Passa todos os itens selecionados
                          json_encode($layout_options) . ", " .
                          json_encode($default_layout_id) .
                     ");\n";
                echo "   } else { \n";
                echo "       console.error('DirectLabelPrinter JS object not found or openPrintModal function missing.');\n";
                echo "       alert('" . addslashes(__('Erro ao inicializar o modal de impressão.', 'directlabelprinter')) . "');\n"; //addslashes para segurança
                 echo "       // Opcional: Redirecionar de volta ou mostrar erro inline\n";
                 echo "       // window.history.back(); \n";
                echo "   }\n";
                echo "});\n";
                echo "</script>\n";

                // Retorna true para indicar que a ação foi tratada
                return true;
        }

        // Se a ação não for a nossa, deixa o GLPI continuar (chama o método pai)
        return parent::showMassiveActionsSubForm($massive_action); // [cite: 4155]
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
                    $massive_action->itemDone($item->getType(), $id, MassiveAction::ACTION_OK); // [cite: 4157]
                }
                // Adiciona uma mensagem geral (opcional)
                // $massive_action->addMessage("Ação 'Imprimir Etiqueta' iniciada."); // [cite: 4158]
                return; // Importante retornar aqui para não executar o processamento pai
        }

        // Se não for nossa ação, chama o processamento padrão do GLPI
        parent::processMassiveActionsForOneItemtype($massive_action, $item, $ids); // [cite: 4159]
    }
}