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
}
