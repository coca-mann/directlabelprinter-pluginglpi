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

class PrintServiceClient
{
    private string $base_url;
    private string $api_key;

    public function __construct(PrintServer $server)
    {
        $this->base_url = rtrim($server->fields['url'], '/');
        $this->api_key  = $server->getDecryptedApiKey();
    }

    public function test(): array
    {
        return $this->getJson('/api/test', 5);
    }

    public function listPrinters(): array
    {
        $result = $this->getJson('/api/printers', 10);
        if ($result['success'] && isset($result['data']['impressoras'])) {
            $result['printers'] = $result['data']['impressoras'];
        } else {
            $result['printers'] = [];
        }
        return $result;
    }

    public function printPdf(string $pdf_bytes, string $printer_name): array
    {
        $tmp_file = tempnam(sys_get_temp_dir(), 'dlp_');
        file_put_contents($tmp_file, $pdf_bytes);

        try {
            $ch = curl_init($this->base_url . '/api/print');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'printer_name' => $printer_name,
                'pdf_file'     => new \CURLFile($tmp_file, 'application/pdf', 'etiqueta.pdf'),
            ]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $this->api_key]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $body       = curl_exec($ch);
            $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            return $this->interpretResponse($body, $http_code, $curl_error);
        } finally {
            unlink($tmp_file);
        }
    }

    private function getJson(string $path, int $timeout): array
    {
        $ch = curl_init($this->base_url . $path);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $this->api_key]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $body       = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $result = $this->interpretResponse($body, $http_code, $curl_error);
        $result['data'] = json_decode((string) $body, true) ?? [];
        return $result;
    }

    private function interpretResponse(?string $body, int $http_code, string $curl_error): array
    {
        if ($curl_error !== '') {
            return ['success' => false, 'message' => __('Connection error:', 'directlabelprinter') . ' ' . $curl_error];
        }
        $decoded = json_decode((string) $body, true);
        if ($http_code < 200 || $http_code >= 300) {
            $message = $decoded['mensagem'] ?? $decoded['erro'] ?? "HTTP $http_code";
            return ['success' => false, 'message' => $message];
        }
        return ['success' => true, 'message' => $decoded['mensagem'] ?? ''];
    }
}
