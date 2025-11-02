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

/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define('PLUGIN_DIRECTLABELPRINTER_VERSION', '0.0.1');

// Minimal GLPI version, inclusive
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_DIRECTLABELPRINTER_MIN_GLPI_VERSION", "11.0.0");

// Maximum GLPI version, exclusive
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_DIRECTLABELPRINTER_MAX_GLPI_VERSION", "11.0.99");

use Glpi\Plugin\Hooks;
use Plugin;
use Toolbox;
use Session;
use GlpiPlugin\Directlabelprinter\Menu; // <-- ADICIONE ESTE USE

/**
 * Init hooks of the plugin.
 */
function plugin_init_directlabelprinter() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['directlabelprinter'] = true;
    $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['directlabelprinter'] = true;

    $plugin = new Plugin();
    if (
        $plugin->isInstalled('directlabelprinter')
        && $plugin->isActivated('directlabelprinter')
    ) {
        // --- REMOVER O IF DE VERIFICAÇÃO DE PERMISSÃO DAQUI ---
        // A segurança é tratada pelo menu 'setup' pai e pelo Controller
        $PLUGIN_HOOKS['menu_toadd']['directlabelprinter'] = [
            // Aponta para a classe Menu que define os detalhes
            'setup' => Menu::class
        ];
    }
}

/**
 * Get the name and the version of the plugin
 */
function plugin_version_directlabelprinter() {
    return [
        'name'           => __('Direct Label Printer', 'directlabelprinter'),
        'version'        => PLUGIN_DIRECTLABELPRINTER_VERSION,
        'author'         => 'Juliano Ostroski',
        'license'        => 'GPLv2+',
        'homepage'       => 'github.com/coca-mann',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0'
            ]
        ],
        // Garantir que 'get_config_page_url' está removido
    ];
}

/**
 * Check configuration process for plugin
 */
function plugin_check_config($verbose = false) {
    global $DB;
    if ($DB->tableExists('glpi_plugin_directlabelprinter_auth')) {
        return true;
    }
    if ($verbose) {
        echo __('Tabela de autenticação não encontrada. Reinstale o plugin.', 'directlabelprinter');
    }
    return false;
}

/**
 * Check pre-requisites before install
 */
function plugin_directlabelprinter_check_prerequisites(): bool {
    return true;
}

?>