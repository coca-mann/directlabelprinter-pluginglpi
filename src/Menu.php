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

class Menu
{
    public static function getMenuContent(): array
    {
        // Uma única entrada em $menu['config']['content'], apontando para o hub
        // front/config.php (que por sua vez linka pra Servidores e Layouts) — em vez de
        // 'is_multi_entries' criando dois itens separados no dropdown de "Configurar".
        // 'options' alimenta o 4º nível do breadcrumb (templates/layout/parts/breadcrumbs.html.twig)
        // quando front/printserver.php e front/layout.php passam $option = 'printserver'/'layout'
        // pra Html::header(), permitindo voltar pro hub a partir das telas de lista/form.
        return [
            'title' => __('Config. Imp. Etiquetas', 'directlabelprinter'),
            'page'  => '/plugins/directlabelprinter/front/config.php',
            'icon'  => 'fas fa-tag',
            'options' => [
                'printserver' => [
                    'title' => PrintServer::getTypeName(2),
                    'page'  => PrintServer::getSearchURL(false),
                    'icon'  => 'fas fa-print',
                    'links' => [
                        'search' => PrintServer::getSearchURL(false),
                        'add'    => PrintServer::getFormURL(false),
                    ],
                ],
                'layout' => [
                    'title' => Layout::getTypeName(2),
                    'page'  => Layout::getSearchURL(false),
                    'icon'  => 'fas fa-tag',
                    'links' => [
                        'search' => Layout::getSearchURL(false),
                        'add'    => Layout::getFormURL(false),
                    ],
                ],
            ],
        ];
    }

    public static function getMenuName($nb = 0): string
    {
        return __('Direct Label Printer', 'directlabelprinter');
    }
}
