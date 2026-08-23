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
}
