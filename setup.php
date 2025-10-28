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
// use GlpiPlugin\Directlabelprinter\Config as PluginConfig; // Import the new Config class with alias
use Config as CoreConfig; // Import the core Config class
use Plugin; // Import the Plugin class
use Toolbox; // Import Toolbox for database operations

/**
 * Init hooks of the plugin.
 * REQUIRED
 */
function plugin_init_directlabelprinter() {
    global $PLUGIN_HOOKS, $CFG_GLPI;

    // ... (keep existing hooks like csrf_compliant) ...
    $PLUGIN_HOOKS['csrf_compliant']['directlabelprinter'] = true;

    $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['directlabelprinter'] = true;

    // $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['directlabelprinter'] = [
    //    'js/directlabelprinter.js'
    // ];
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array{
 *      name: string,
 *      version: string,
 *      author: string,
 *      license: string,
 *      homepage: string,
 *      requirements: array{
 *          glpi: array{
 *              min: string,
 *              max: string,
 *          }
 *      }
 * }
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
                'min' => '11.0.0' // Ajuste conforme necessário
            ]
        ],
        // ---> ADICIONAR ESTA FUNÇÃO ANÔNIMA <---
        // Define a URL acessada pelo ícone de chave inglesa
        'get_config_page_url' => function() {
            return Plugin::getWebDir('directlabelprinter', true) . "/front/config.php";
        }
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONAL
 */
function plugin_directlabelprinter_check_prerequisites(): bool
{
    return true;
}

/**
 * Check configuration process for plugin
 * Can display a message only if failure and $verbose is true
 * @param boolean $verbose Enable verbosity. Default to false
 * @return boolean
 */
function plugin_directlabelprinter_check_config($verbose = false) {
    global $DB; // Acesso à variável global do banco de dados

    // Apenas verificar se a tabela principal de autenticação existe.
    // A configuração real (URL, etc.) será feita na página de configuração dedicada.
    $auth_table = 'glpi_plugin_directlabelprinter_auth';

    if ($DB->tableExists($auth_table)) {
        // Se a tabela existe, consideramos o plugin "configurável" e permitimos a ativação.
        return true;
    } else {
        // Se a tabela não existe, algo deu errado na instalação.
        if ($verbose) {
            echo __('Tabela de autenticação do plugin não encontrada. Reinstale o plugin.', 'directlabelprinter');
        }
        return false; // Impede a ativação se a estrutura básica não estiver presente.
    }
}
