<?php

/**
 * -------------------------------------------------------------------------
 * directlabelprinter plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by the directlabelprinter plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/directlabelprinter
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Directlabelprinter;

use CommonDBTM;
use Html;
use Dropdown;

class Layout extends CommonDBTM
{
    // Ver o comentário equivalente em PrintServer::$rightname: 'config' não é um right
    // CRUD real no GLPI 11, é só um cabeçalho de seção na matriz de perfis.
    public static $rightname = 'plugin_directlabelprinter';

    public static function getTypeName($nb = 0)
    {
        return _n('Layout de Etiqueta', 'Layouts de Etiqueta', $nb, 'directlabelprinter');
    }

    public function getElements(): array
    {
        $raw = $this->fields['elements'] ?? '';
        if ($raw === '' || $raw === null) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setElements(array $elements): void
    {
        $this->fields['elements'] = json_encode(array_values($elements));
    }

    public function prepareInputForAdd($input)
    {
        return $this->attachCustomFontIfUploaded($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->attachCustomFontIfUploaded($input);
    }

    /**
     * When a .ttf was uploaded via the Html::file() widget in showForm(), GLPI's upload JS
     * has already moved it into GLPI_TMP_DIR and populated $input['_filename'] (array).
     * Document::add() consumes that same convention to finalize storage — see
     * src/Document.php:247 (`if (!empty($input["_filename"])) { self::moveDocument(...) }`).
     */
    private function attachCustomFontIfUploaded(array $input): array
    {
        if (($input['font_choice'] ?? '') !== 'custom' || empty($input['_filename'])) {
            return $input;
        }

        $document = new \Document();
        $document_input = [
            'name'       => $input['_filename'][0] ?? 'custom-font.ttf',
            'entities_id' => 0,
            '_filename'  => $input['_filename'],
            '_prefix_filename' => $input['_prefix_filename'] ?? [],
        ];
        $document_id = $document->add($document_input);
        if ($document_id) {
            $input['custom_font_documents_id'] = $document_id;
        }

        return $input;
    }

    public function post_addItem()
    {
        $this->syncItemtypesFromInput();
        parent::post_addItem();
    }

    public function post_updateItem($history = true)
    {
        $this->syncItemtypesFromInput();
        parent::post_updateItem($history);
    }

    private function syncItemtypesFromInput(): void
    {
        // '_itemtypes' (array of itemtype => '1') and '_default_itemtypes' (array of
        // itemtype => '1', one independent default flag per itemtype) are populated by
        // the form built in showForm().
        if (!isset($this->input['_itemtypes'])) {
            return;
        }
        $default_itemtypes = $this->input['_default_itemtypes'] ?? [];
        $map = [];
        foreach (array_keys($this->input['_itemtypes']) as $itemtype) {
            $map[$itemtype] = isset($default_itemtypes[$itemtype]);
        }
        LayoutItemtype::syncForLayout((int) $this->fields['id'], $map);
    }

    public function showForm($ID, array $options = [])
    {
        global $CFG_GLPI;
        echo Html::script($CFG_GLPI['root_doc'] . '/lib/gridstack.min.js');
        echo Html::css($CFG_GLPI['root_doc'] . '/lib/gridstack.min.css');
        // GridStack não vem com nenhum estilo visível pronto para o item/canvas — sem isso o
        // editor existe e funciona (confirmado via GridStack.init() e widgets sendo criados),
        // mas nada aparece: nem borda no canvas, nem contorno nos elementos arrastáveis.
        echo Html::css($CFG_GLPI['root_doc'] . '/plugins/directlabelprinter/css/layout_editor.css', ['version' => PLUGIN_DIRECTLABELPRINTER_VERSION]);
        // 'version' explícito: sem isso, Html::script() usa GLPI_VERSION (constante do core,
        // nunca muda com edições deste plugin) como cache-busting ?v=... — e como esse
        // servidor fica atrás do Cloudflare, a URL nunca muda, o Cloudflare guarda a resposta
        // em cache de borda por até 30 dias (Cache-Control: public, max-age=2592000) e nem
        // hard refresh no navegador resolve, porque isso só ignora o cache local. Usando a
        // versão do PRÓPRIO plugin aqui, o ?v=... muda a cada bump de versão.
        echo Html::script($CFG_GLPI['root_doc'] . '/plugins/directlabelprinter/js/layout_editor.js', ['version' => PLUGIN_DIRECTLABELPRINTER_VERSION]);

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Nome/Fonte numa linha, Largura/Altura na coluna 1 das duas linhas seguintes com o
        // upload de fonte customizada ocupando as duas (rowspan) ao lado — depois o editor de
        // arrastar-e-soltar, e só então a lista de tipos de ativo.
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Nome', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('name', ['value' => $this->fields['name'] ?? '']) . "</td>";
        echo "<td>" . __('Fonte', 'directlabelprinter') . "</td>";
        echo "<td id='dlp-font-choice-cell'>";
        Dropdown::showFromArray('font_choice', [
            'helvetica'  => __('Helvetica', 'directlabelprinter'),
            'times'      => __('Times', 'directlabelprinter'),
            'courier'    => __('Courier', 'directlabelprinter'),
            'dejavusans' => __('DejaVu Sans (UTF-8 / acentos)', 'directlabelprinter'),
            'custom'     => __('Personalizada (enviar .ttf)', 'directlabelprinter'),
        ], ['value' => $this->fields['font_choice'] ?: 'dejavusans']);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Largura (mm)', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('width_mm', ['value' => $this->fields['width_mm'] ?: 50, 'type' => 'number']) . "</td>";
        // As duas células (label + upload) são escondidas/mostradas juntas via JS
        // (layout_editor.js), conforme a fonte escolhida acima seja ou não 'custom'.
        echo "<td rowspan='2' class='dlp-font-upload-cell'>" . __('Arquivo de fonte personalizada', 'directlabelprinter') . "</td>";
        echo "<td rowspan='2' class='dlp-font-upload-cell'>";
        // Deliberately NOT overriding 'name' (default 'filename'): GLPI's upload JS always
        // reports back into a hidden `_filename[]` field regardless of the visible widget
        // name (confirmed: Document::add() reads the literal key `$input['_filename']`,
        // src/Document.php:247) — renaming here would silently break Document::add() below.
        Html::file(['onlyimages' => false]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Altura (mm)', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('height_mm', ['value' => $this->fields['height_mm'] ?: 50, 'type' => 'number']) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'><td colspan='4'>";
        // ?: em vez de ?? — CommonDBTM::getEmpty() (chamado por initForm() em itens novos)
        // preenche $this->fields com string vazia para colunas numéricas, não com o DEFAULT
        // do schema; a chave já existe, então ?? nunca disparava e o editor recebia
        // data-width-mm="0"/data-height-mm="0", fazendo GridStack.init() calcular 0 colunas
        // (--gs-column-width: Infinity% no CSS gerado) e a grade não aparecer.
        echo "<div id='dlp-layout-editor' data-width-mm='" . (float) ($this->fields['width_mm'] ?: 50)
            . "' data-height-mm='" . (float) ($this->fields['height_mm'] ?: 50) . "'>";
        echo "<div class='dlp-editor-toolbar'>";
        echo "<button type='button' id='dlp-add-text-btn' class='btn btn-secondary btn-sm'>" . __('Adicionar Texto', 'directlabelprinter') . "</button> ";
        echo "<button type='button' id='dlp-add-qr-btn' class='btn btn-secondary btn-sm'>" . __('Adicionar QR Code', 'directlabelprinter') . "</button>";
        echo "</div>";
        echo "<div id='dlp-grid' class='grid-stack'></div>";
        echo "<div id='dlp-property-panel' style='display:none'>";
        echo "<select id='dlp-prop-data-source'>";
        echo "<option value='titulo'>" . __('Título', 'directlabelprinter') . "</option>";
        echo "<option value='url'>" . __('URL', 'directlabelprinter') . "</option>";
        echo "<option value='ref'>" . __('Referência', 'directlabelprinter') . "</option>";
        echo "<option value='custom'>" . __('Texto fixo', 'directlabelprinter') . "</option>";
        echo "</select>";
        echo "<input type='text' id='dlp-prop-custom-text' placeholder='" . __('Texto fixo', 'directlabelprinter') . "'>";
        echo "<input type='number' id='dlp-prop-font-size' placeholder='" . __('Tamanho da fonte', 'directlabelprinter') . "'>";
        echo "<label>" . Html::getCheckbox(['id' => 'dlp-prop-font-weight', 'zero_on_empty' => false]) . " " . __('Negrito', 'directlabelprinter') . "</label>";
        echo "<label>" . Html::getCheckbox(['id' => 'dlp-prop-wrap', 'zero_on_empty' => false]) . " " . __('Quebrar texto', 'directlabelprinter') . "</label>";
        echo "<select id='dlp-prop-align'><option value='left'>" . __('Esquerda', 'directlabelprinter') . "</option><option value='center'>" . __('Centro', 'directlabelprinter') . "</option><option value='right'>" . __('Direita', 'directlabelprinter') . "</option></select>";
        echo "<select id='dlp-prop-valign'><option value='top'>" . __('Topo', 'directlabelprinter') . "</option><option value='middle'>" . __('Meio', 'directlabelprinter') . "</option><option value='bottom'>" . __('Base', 'directlabelprinter') . "</option></select>";
        echo "<label>" . Html::getCheckbox(['id' => 'dlp-prop-background', 'zero_on_empty' => false]) . " " . __('Fundo preto', 'directlabelprinter') . "</label>";
        echo "<button type='button' id='dlp-remove-element-btn' class='btn btn-danger btn-sm'>" . __('Remover elemento', 'directlabelprinter') . "</button>";
        echo "</div>";
        echo "</div>";
        echo Html::hidden('elements', ['value' => $this->fields['elements'] ?? '[]', 'id' => 'dlp-elements-input']);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Tipos de ativo', 'directlabelprinter') . "</td><td colspan='3'>";
        $current = $this->isNewID($ID) ? [] : LayoutItemtype::getItemtypesForLayout((int) $ID);
        foreach (AssetTypes::WHITELIST as $itemtype) {
            echo "<div style='margin-bottom:0.5em'>";
            echo Html::getCheckbox([
                'name'          => "_itemtypes[$itemtype]",
                'checked'       => isset($current[$itemtype]),
                'zero_on_empty' => false,
            ]) . " $itemtype &nbsp;";
            echo Html::getCheckbox([
                'name'          => "_default_itemtypes[$itemtype]",
                'checked'       => $current[$itemtype] ?? false,
                'zero_on_empty' => false,
            ]) . " " . __('padrão', 'directlabelprinter');
            echo "</div>";
        }
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }
}
