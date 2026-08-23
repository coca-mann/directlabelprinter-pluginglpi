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
    // 'config' (GLPI's core Setup right) só existe como cabeçalho de seção na matriz de
    // perfis — não é um right CRUD real (sem CREATE/UPDATE/PURGE atribuíveis), então
    // canCreate()/canUpdate() nunca retornam true com ele. Precisamos de um right próprio,
    // registrado em plugin_directlabelprinter_install() via ProfileRight::addProfileRights().
    public static $rightname = 'plugin_directlabelprinter';

    public static function getTypeName($nb = 0)
    {
        return _n('Servidor de Impressão', 'Servidores de Impressão', $nb, 'directlabelprinter');
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

        $current_id = (int) ($this->fields['id'] ?? 0);

        // Nome/URL/Chave numa coluna, Comentário ocupando as 3 linhas ao lado — Testar
        // Conexão e Buscar Impressoras funcionam a partir dos valores digitados aqui, então
        // os campos url/_api_key_plain levam id's fixos para o JS ler o valor atual (mesmo
        // antes de salvar; ver ajax/printserver_test.php e ajax/printserver_fetch_printers.php).
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Nome', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('name', ['value' => $this->fields['name'] ?? '']) . "</td>";
        echo "<td rowspan='3'>" . __('Comentário', 'directlabelprinter') . "</td>";
        echo "<td rowspan='3'>" . Html::textarea([
            'name'    => 'comment',
            'value'   => $this->fields['comment'] ?? '',
            'display' => false,
            'rows'    => 6,
        ]) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('URL', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('url', ['value' => $this->fields['url'] ?? '', 'size' => 40, 'id' => 'dlp-url-input']) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Chave de API', 'directlabelprinter') . "</td>";
        echo "<td>" . Html::input('_api_key_plain', [
            'type'        => 'password',
            'value'       => '',
            'placeholder' => empty($this->fields['api_key']) ? '' : '********',
            'id'          => 'dlp-api-key-input',
        ]) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Impressora padrão', 'directlabelprinter') . "</td>";
        echo "<td colspan='3'>";
        $current_printer = $this->fields['default_printer_name'] ?? '';
        echo "<select name='default_printer_name' id='dlp-default-printer-select' class='form-select d-inline-block w-auto'>";
        if ($current_printer !== '') {
            echo "<option value='" . htmlspecialchars($current_printer) . "' selected>" . htmlspecialchars($current_printer) . "</option>";
        } else {
            echo "<option value=''>" . __('Nenhuma selecionada', 'directlabelprinter') . "</option>";
        }
        echo "</select>";
        echo "&nbsp;<span id='dlp-printer-fetch-status'></span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4'>";
        echo "<button type='button' id='dlp-test-connection-btn' data-id='$current_id' class='btn btn-secondary btn-sm'>"
            . __('Testar Conexão', 'directlabelprinter') . "</button> ";
        echo "<span id='dlp-test-connection-status'></span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4'>";
        echo "<button type='button' id='dlp-fetch-printers-btn' data-id='$current_id' class='btn btn-secondary btn-sm'>"
            . __('Buscar impressoras', 'directlabelprinter') . "</button>";
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);
        return true;
    }
}
