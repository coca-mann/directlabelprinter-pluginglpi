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
use GLPIKey;
use Html;

class PrintServer extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return _n('Print Server', 'Print Servers', $nb, 'directlabelprinter');
    }

    public function setApiKey(string $plain_key): void
    {
        $this->fields['api_key'] = $plain_key === ''
            ? ''
            : (new GLPIKey())->encrypt($plain_key);
    }

    public function getDecryptedApiKey(): string
    {
        $encrypted = $this->fields['api_key'] ?? '';
        if ($encrypted === '') {
            return '';
        }
        return (new GLPIKey())->decrypt($encrypted) ?? '';
    }

    public function prepareInputForAdd($input)
    {
        return $this->encryptApiKeyInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->encryptApiKeyInput($input);
    }

    private function encryptApiKeyInput($input)
    {
        // '_api_key_plain' is the plaintext form field (see Task 3); blank means
        // "keep existing key" on update, matching the Django admin's UX.
        if (isset($input['_api_key_plain']) && $input['_api_key_plain'] !== '') {
            $input['api_key'] = (new GLPIKey())->encrypt($input['_api_key_plain']);
        }
        unset($input['_api_key_plain']);
        return $input;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'><td>" . __('Name', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('name', ['value' => $this->fields['name'] ?? '']) . "</td>";
        echo "<td>" . __('URL', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('url', ['value' => $this->fields['url'] ?? '', 'size' => 40]) . "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('API Key', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('_api_key_plain', [
            'type'        => 'password',
            'value'       => '',
            'placeholder' => empty($this->fields['api_key']) ? '' : '********',
        ]) . "</td>";
        echo "<td>" . __('Default printer', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('default_printer_name', ['value' => $this->fields['default_printer_name'] ?? ''])
            . "<br><span id='dlp-printer-fetch-status'></span>";

        if ($this->fields['id']) {
            echo "<button type='button' id='dlp-fetch-printers-btn' data-id='" . (int) $this->fields['id'] . "' class='btn btn-secondary btn-sm mt-1'>"
                . __('Fetch printers', 'directlabelprinter') . "</button>";
        }

        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Comment', 'directlabelprinter') . "</td>";
        echo "<td colspan='3'>" . Html::textarea(['name' => 'comment', 'value' => $this->fields['comment'] ?? '', 'display' => false]) . "</td></tr>";

        if ($this->fields['id']) {
            echo "<tr class='tab_bg_1'><td colspan='4'>";
            echo "<button type='button' id='dlp-test-connection-btn' data-id='" . (int) $this->fields['id'] . "' class='btn btn-secondary btn-sm'>"
                . __('Test Connection', 'directlabelprinter') . "</button> ";
            echo "<span id='dlp-test-connection-status'></span>";
            echo "</td></tr>";
        }

        $this->showFormButtons($options);
        return true;
    }
}
