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
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return _n('Label Layout', 'Label Layouts', $nb, 'directlabelprinter');
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
        // '_itemtypes' (array of itemtype => '1') and '_default_itemtype' (single string)
        // are populated by the form built in Task 6.
        if (!isset($this->input['_itemtypes'])) {
            return;
        }
        $default_itemtype = $this->input['_default_itemtype'] ?? null;
        $map = [];
        foreach (array_keys($this->input['_itemtypes']) as $itemtype) {
            $map[$itemtype] = ($itemtype === $default_itemtype);
        }
        LayoutItemtype::syncForLayout((int) $this->fields['id'], $map);
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'><td>" . __('Name', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('name', ['value' => $this->fields['name'] ?? '']) . "</td>";
        echo "<td>" . __('Font', 'directlabelprinter') . "</td>";
        echo "<td>";
        Dropdown::showFromArray('font_choice', [
            'helvetica'  => __('Helvetica', 'directlabelprinter'),
            'times'      => __('Times', 'directlabelprinter'),
            'courier'    => __('Courier', 'directlabelprinter'),
            'dejavusans' => __('DejaVu Sans (UTF-8 / accents)', 'directlabelprinter'),
            'custom'     => __('Custom (upload .ttf)', 'directlabelprinter'),
        ], ['value' => $this->fields['font_choice'] ?? 'dejavusans']);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Width (mm)', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('width_mm', ['value' => $this->fields['width_mm'] ?? 50, 'type' => 'number']) . "</td>";
        echo "<td>" . __('Height (mm)', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('height_mm', ['value' => $this->fields['height_mm'] ?? 50, 'type' => 'number']) . "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Custom font file', 'directlabelprinter') . "</td>";
        echo "<td colspan='3'>";
        // Deliberately NOT overriding 'name' (default 'filename'): GLPI's upload JS always
        // reports back into a hidden `_filename[]` field regardless of the visible widget
        // name (confirmed: Document::add() reads the literal key `$input['_filename']`,
        // src/Document.php:247) — renaming here would silently break Document::add() below.
        Html::file(['onlyimages' => false]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Asset types', 'directlabelprinter') . "</td><td colspan='3'>";
        $current = $this->isNewID($ID) ? [] : LayoutItemtype::getItemtypesForLayout((int) $ID);
        foreach (AssetTypes::WHITELIST as $itemtype) {
            $checked = isset($current[$itemtype]) ? 'checked' : '';
            $default_checked = ($current[$itemtype] ?? false) ? 'checked' : '';
            echo "<label style='margin-right:1em'>";
            echo "<input type='checkbox' name='_itemtypes[$itemtype]' value='1' $checked> $itemtype ";
            echo "<input type='radio' name='_default_itemtype' value='$itemtype' $default_checked> " . __('default', 'directlabelprinter');
            echo "</label>";
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Elements (JSON)', 'directlabelprinter') . "</td><td colspan='3'>";
        // Placeholder textarea — Task 8 layers the GridStack canvas on top of this same
        // 'elements' field without changing its name, so this keeps working meanwhile.
        echo Html::textarea([
            'name'    => 'elements',
            'value'   => $this->fields['elements'] ?? '[]',
            'rows'    => 10,
            'display' => false,
        ]);
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }
}
