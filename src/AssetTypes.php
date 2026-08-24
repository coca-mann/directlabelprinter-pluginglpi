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

use Glpi\Asset\AssetDefinitionManager;

class AssetTypes
{
    public const WHITELIST = [
        'Computer', 'Monitor', 'NetworkEquipment', 'Printer', 'Phone', 'Peripheral',
        'Rack', 'ConsumableItem',
    ];

    /**
     * WHITELIST alone can't include GLPI 11 custom assets (Configuração > Definições de
     * ativos, e.g. "Nobreak" => Glpi\CustomAsset\NobreakAsset): those classes only exist at
     * runtime (created on demand by GLPI's own autoloader), so they can't be listed in a
     * const array. This merges the fixed WHITELIST with every currently *active* custom
     * asset itemtype — call this instead of reading AssetTypes::WHITELIST directly anywhere
     * that decides which itemtypes get label printing.
     *
     * @return string[]
     */
    public static function getWhitelist(): array
    {
        return array_merge(
            self::WHITELIST,
            AssetDefinitionManager::getInstance()->getCustomObjectClassNames()
        );
    }
}
