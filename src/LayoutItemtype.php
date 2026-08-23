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

class LayoutItemtype
{
    private const TABLE = 'glpi_plugin_directlabelprinter_layout_itemtype';

    public static function getItemtypesForLayout(int $layouts_id): array
    {
        global $DB;
        $result = [];
        $iterator = $DB->request([
            'FROM'  => self::TABLE,
            'WHERE' => ['plugin_directlabelprinter_layouts_id' => $layouts_id],
        ]);
        foreach ($iterator as $row) {
            $result[$row['itemtype']] = (bool) $row['is_default'];
        }
        return $result;
    }

    public static function getDefaultLayoutId(string $itemtype): ?int
    {
        global $DB;
        $row = $DB->request([
            'FROM'  => self::TABLE,
            'WHERE' => ['itemtype' => $itemtype, 'is_default' => 1],
            'LIMIT' => 1,
        ])->current();
        return $row ? (int) $row['plugin_directlabelprinter_layouts_id'] : null;
    }

    public static function getLayoutsForItemtype(string $itemtype): array
    {
        global $DB;
        $ids = [];
        $iterator = $DB->request([
            'FROM'  => self::TABLE,
            'WHERE' => ['itemtype' => $itemtype],
        ]);
        foreach ($iterator as $row) {
            $ids[] = (int) $row['plugin_directlabelprinter_layouts_id'];
        }
        return $ids;
    }

    /**
     * Replaces all itemtype associations for a layout in one call.
     *
     * @param array<string,bool> $itemtype_to_is_default e.g. ['Computer' => true, 'Monitor' => false]
     */
    public static function syncForLayout(int $layouts_id, array $itemtype_to_is_default): void
    {
        global $DB;

        $DB->delete(self::TABLE, ['plugin_directlabelprinter_layouts_id' => $layouts_id]);

        foreach ($itemtype_to_is_default as $itemtype => $is_default) {
            if (!in_array($itemtype, AssetTypes::WHITELIST, true)) {
                continue;
            }
            if ($is_default) {
                // Only one default per itemtype, across all layouts.
                $DB->update(self::TABLE, ['is_default' => 0], ['itemtype' => $itemtype]);
            }
            $DB->insert(self::TABLE, [
                'plugin_directlabelprinter_layouts_id' => $layouts_id,
                'itemtype'                              => $itemtype,
                'is_default'                             => $is_default ? 1 : 0,
            ]);
        }
    }
}
