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
use GlpiPlugin\Directlabelprinter\Config as PluginConfig; // Import the new Config class with alias
use Config as CoreConfig; // Import the core Config class
use Plugin; // Import the Plugin class

/**
 * Init hooks of the plugin.
 * REQUIRED
 */
function plugin_init_directlabelprinter() {
    global $PLUGIN_HOOKS, $CFG_GLPI;

    // ... (keep existing hooks like csrf_compliant) ...
    $PLUGIN_HOOKS['csrf_compliant']['directlabelprinter'] = true;

    // Register the Config class to add a tab on the core Config page [cite: 4098-4099]
    Plugin::registerClass(PluginConfig::class, ['addtabon' => CoreConfig::class]); // Use the core Config class

    $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['directlabelprinter'] = true;

    $relative_build_path = 'public/build/plugins/directlabelprinter/js/directlabelprinter.js';
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['directlabelprinter'] = [
        'directlabelprinter.js' // Caminho relativo à raiz do plugin
    ];

    // You might add other class registrations or hooks here later
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
function plugin_version_directlabelprinter(): array
{
    return [
        'name'           => 'Direct Label Printer',
        'version'        => PLUGIN_DIRECTLABELPRINTER_VERSION,
        'author'         => '<a href="https://github.com/coca-mann">Juliano Ostroski\'</a>',
        'license'        => '',
        'homepage'       => 'https://github.com/coca-mann/directlabelprinter-pluginglpi',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_DIRECTLABELPRINTER_MIN_GLPI_VERSION,
                'max' => PLUGIN_DIRECTLABELPRINTER_MAX_GLPI_VERSION,
            ],
        ],
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
 * Check configuration process
 * OPTIONAL
 *
 * @param bool $verbose Whether to display message on failure. Defaults to false.
 */
function plugin_directlabelprinter_check_config(bool $verbose = false): bool
{
    // Your configuration check
    return true;

    // Example:
    // if ($verbose) {
    //    echo __('Installed / not configured', 'directlabelprinter');
    // }
    // return false;
}
