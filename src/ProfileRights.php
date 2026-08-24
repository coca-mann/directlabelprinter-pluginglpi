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

use CommonGLPI;
use Html;
use Profile;
use Session;

/**
 * Adds a "Direct Label Printer" tab to Administração > Perfis (Profile::showForm()), the only
 * place GLPI lets an admin see/edit plugin-defined rights — GLPI's own rights matrix
 * (Profile::getRightsForForm()) only covers core rights, plugins must register their own tab
 * via Plugin::registerClass(..., ['addtabon' => Profile::class]) (see setup.php). Without this
 * tab, 'plugin_directlabelprinter' and 'plugin_directlabelprinter_print' (see PrintServer,
 * Layout, DirectLabelPrinterActions) would only be adjustable by editing glpi_profilerights
 * directly in the database.
 */
class ProfileRights extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Direct Label Printer', 'directlabelprinter');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            self::showForProfile($item);
        }
        return true;
    }

    private static function showForProfile(Profile $profile): void
    {
        // Mesma checagem que os blocos nativos do Profile (showFormAsset() etc.) usam pra
        // decidir se mostram o form de edição ou só a matriz somente-leitura.
        $canedit = Session::haveRightsOr(Profile::$rightname, [UPDATE, CREATE, PURGE]);

        echo "<div class='spaced'>";
        if ($canedit) {
            echo "<form method='post' action='" . htmlescape($profile->getFormURL()) . "' data-track-changes='true'>";
        }

        $profile->displayRightsChoiceMatrix([
            [
                'itemtype' => PrintServer::class,
                'label'    => __('Configurar servidores de impressão e layouts', 'directlabelprinter'),
                'field'    => 'plugin_directlabelprinter',
            ],
            [
                'itemtype' => DirectLabelPrinterActions::class,
                'label'    => __('Imprimir etiquetas', 'directlabelprinter'),
                'field'    => 'plugin_directlabelprinter_print',
            ],
        ], [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('Direct Label Printer', 'directlabelprinter'),
        ]);

        if ($canedit) {
            echo "<div class='center'>";
            echo "<input type='hidden' name='id' value='" . (int) $profile->fields['id'] . "'>";
            echo Html::submit(_sx('button', 'Save'), [
                'class' => 'btn btn-primary mt-2',
                'name'  => 'update',
                'icon'  => 'fas fa-save',
            ]);
            echo "</div>";
            Html::closeForm();
        }
        echo "</div>";
    }
}
