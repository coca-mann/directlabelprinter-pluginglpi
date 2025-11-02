<?php
// src/Config.php

namespace GlpiPlugin\Directlabelprinter;

use CommonDBTM;
use Html;
use Session;
use Toolbox;
use Plugin;
use DateTime;
use Glpi\Toolbox\Sanitizer;

class Config extends CommonDBTM
{
    // Usar 'config' como direito (o utilizador deve ter permissão de Configuração para aceder)
    static $rightname = 'config';

    // Define a tabela que esta classe irá gerir
    static function getTable($classname = null)
    {
        return 'glpi_plugin_directlabelprinter_auth';
    }

    // Define o nome que aparece no menu
    static function getTypeName($nb = 0)
    {
        return __('Direct Label Printer', 'directlabelprinter');
    }

    /**
     * Define como este item aparece no menu (hook 'menu_toadd')
     */
    static function getMenuContent()
    {
        // Só temos 1 item de config, ID 1. Link direto para o formulário desse item.
        $config_id = self::getOrCreateDefaultConfig();
        
        return [
            'title' => self::getTypeName(),
            'page'  => self::getFormURL(['id' => $config_id]), // Aponta para front/config.form.php?id=1
            'icon'  => 'fas fa-print',
            // '_rank' => 50,
        ];
    }

    /**
     * Garante que a linha de configuração (ID=1) exista
     */
    static function getOrCreateDefaultConfig()
    {
        global $DB;
        $table = self::getTable();
        $auth_data = $DB->request(['FROM' => $table, 'LIMIT' => 1])->current();

        if (empty($auth_data)) {
            // Cria a linha de configuração placeholder se não existir
            $DB->insert($table, [
                'id' => 1, // Força o ID 1
                'user' => 'default' // Placeholder
            ]);
            return 1;
        }
        return $auth_data['id'];
    }

    /**
     * Sobrescreve o URL do formulário (padrão é front/commondbtm.form.php)
     */
    static function getFormURL($params = []) {
         return Plugin::getWebDir('directlabelprinter', true) . "/front/config.form.php" . Html::paramsToString($params);
    }
    
    /**
     * Sobrescreve o URL da lista (padrão é front/commondbtm.php)
     */
    static function getSearchURL($full = false) {
         return Plugin::getWebDir('directlabelprinter', true) . "/front/config.php";
    }

    /**
     * Exibe o formulário de configuração (sem Twig)
     */
    public function showForm($ID, $options = [])
    {
        global $CFG_GLPI;

        // ID será sempre 1 (ou o ID da nossa config)
        $this->initForm($ID, $options);

        // Obter layouts para o dropdown
        global $DB;
        $layouts_table = 'glpi_plugin_directlabelprinter_layouts';
        $layouts_result = $DB->request(['FROM' => $layouts_table]);
        $layouts_from_db = []; foreach ($layouts_result as $layout) { $layouts_from_db[] = $layout; }
        $layout_options = []; $default_layout_id_api = null;
        foreach ($layouts_from_db as $layout) {
            if (isset($layout['id_api']) && isset($layout['nome'])) $layout_options[$layout['id_api']] = $layout['nome'];
            if (isset($layout['padrao']) && $layout['padrao'] == 1) $default_layout_id_api = $layout['id_api'] ?? null;
        }

        // Formulário aponta para config.form.php (padrão do CommonDBTM)
        echo "<form name='config_form_directlabelprinter' action='" . self::getFormURL() . "' method='POST' class='glpi_form' autocomplete='off'>";
        // O CommonDBTM::showFormHeader() ou initForm() pode já gerar o token,
        // mas vamos garantir que ele está aqui com o nome correto para o POST
        // Usar o token CSRF padrão gerado pelo GLPI para este formulário
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken($this->getCsrfTokenName('config'))]);
        echo Html::hidden('id', ['value' => $this->fields['id']]); // Envia o ID para a atualização

        // --- Authentication Section ---
        echo "<div class='card card-flush shadow-sm m-6'>";
        echo "  <div class='card-header'><h3 class='card-title fw-bold'>" . __('Authentication', 'directlabelprinter') . "</h3></div>";
        echo "  <div class='card-body'>";
        echo "      <table class='tab_cadre_noborder' width='100%'>";
        echo "          <tr><td width='30%'>" . __('API URL', 'directlabelprinter') . "</td>";
        echo "              <td>" . Html::input('api_url', ['value' => $this->fields['api_url'] ?? '', 'size' => 60]) . "</td></tr>";
        echo "          <tr><td>" . __('User', 'directlabelprinter') . "</td>";
        echo "              <td>" . Html::input('api_user', ['value' => $this->fields['user'] ?? '']) . "</td></tr>";
        echo "          <tr><td>" . __('Password', 'directlabelprinter') . "</td>";
        echo "              <td>" . Html::input('api_password', ['value' => '', 'type' => 'password']) . "</td></tr>";
        echo "          <tr><td></td><td>";
        // Botão de Teste agora é um botão de submit com nome 'test_connection'
        echo "              <button type='submit' name='test_connection' value='1' class='btn btn-secondary btn-sm'>" . __('Test Connection', 'directlabelprinter') . "</button>";
        echo "          </td></tr>";
        if (!empty($this->fields['access_token'])) {
            echo "      <tr><td colspan='2'><div class='alert alert-info mt-3' role='alert'>";
            echo            __('Token de Acesso válido até:', 'directlabelprinter') . ' ' . Html::convDateTime($this->fields['access_token_expires']);
            echo "      </div></td></tr>";
        }
        echo "      </table>";
        echo "  </div>";
        echo "</div>";

        // --- Layouts Section ---
        echo "<div class='card card-flush shadow-sm m-6'>";
        echo "  <div class='card-header'><h3 class='card-title fw-bold'>" . __('Layouts', 'directlabelprinter') . "</h3></div>";
        echo "  <div class='card-body'>";
        // Botão Fetch agora é um botão de submit com nome 'fetch_layouts'
        echo "      <button type='submit' name='fetch_layouts' value='1' class='btn btn-secondary btn-sm'>" . __('Fetch Layouts', 'directlabelprinter') . "</button>";
        echo "      <div class='mt-4'>";
        echo            __('Default Layout', 'directlabelprinter') . "&nbsp;";
        Dropdown::showFromArray("default_layout_id_api", $layout_options, ['value' => $default_layout_id_api]);
        echo "      </div>";

        echo "      <h4 class='mt-5'>" . __('Layout Details', 'directlabelprinter') . "</h4>";
        if (!empty($layouts_from_db)) {
            // ... (Tabela HTML dos layouts, como no ficheiro anterior) ...
        } else {
            echo "  <p>" . __('No layouts fetched yet.', 'directlabelprinter') . "</p>";
        }
        echo "  </div>";
        echo "  <div class='card-footer d-flex justify-content-end py-6 px-9'>";
        // O botão Salvar agora é o botão 'update' padrão do CommonDBTM
        echo "      <button type='submit' name='update_defaults' value='" . _x('button', 'Save') . "' class='btn btn-primary'>";
        echo "          <i class='ti ti-device-floppy me-2'></i> " . _x('button', 'Save');
        echo "      </button>";
        echo "  </div>";
        echo "</div>";

        echo "</form>";
        Html::footer();
        return true;
    }

    /**
     * Processa a lógica POST personalizada antes da atualização padrão do CommonDBTM
     */
    function post_update()
    {
        global $DB;
        $input = $this->input;
        $auth_table = self::getTable();
        $layouts_table = 'glpi_plugin_directlabelprinter_layouts';

        // --- AÇÃO: Testar Conexão ---
        if (isset($input['test_connection'])) {
            Toolbox::logInFile("debug", "[Config Class] Test Connection POST received.");
            try {
                $api_url = rtrim($input['api_url'], '/');
                $username = $input['api_user'];
                $password = $input['api_password'];
                if (empty($api_url) || empty($username) || empty($password)) throw new \Exception(/*...*/);
                $token_endpoint = $api_url . '/api/auth/token/';
                // cURL...
                $ch = curl_init(/*...*/); /*...*/ $api_response_body = curl_exec($ch); /*...*/ curl_close($ch);
                if ($curl_error || $http_code != 200) throw new \Exception(/*...*/);
                $tokens = json_decode($api_response_body, true); if (empty($tokens['access'])) throw new \Exception(/*...*/);
                
                $expires_datetime = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');
                // Preparar dados para salvar
                $data_to_save = [
                    'id'                   => $this->fields['id'], // Manter o ID
                    'user'                 => $username,
                    'api_url'              => $api_url,
                    'access_token'         => $tokens['access'],
                    'refresh_token'        => $tokens['refresh'],
                    'access_token_expires' => $expires_datetime
                ];
                // Atualiza a linha de config
                $this->update($data_to_save);
                Session::addMessageAfterRedirect(__('Autenticação bem-sucedida! Tokens salvos.', 'directlabelprinter'), false, INFO);

            } catch (\Exception $e) {
                Session::addMessageAfterRedirect(__('Erro na conexão: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
            }
            // Não continua para o 'update_defaults'
            return;
        }

        // --- AÇÃO: Buscar Layouts ---
        if (isset($input['fetch_layouts'])) {
             Toolbox::logInFile("debug", "[Config Class] Fetch Layouts POST received.");
             try {
                if (!class_exists(DirectLabelPrinterActions::class) || !method_exists(DirectLabelPrinterActions::class, 'makeAuthenticatedApiRequest')) throw new \Exception(/*...*/);
                $layouts_from_api = DirectLabelPrinterActions::makeAuthenticatedApiRequest('GET', '/api/layouts/');
                if (!is_array($layouts_from_api)) throw new \Exception(/*...*/);
                $DB->query("TRUNCATE TABLE `$layouts_table`");
                foreach ($layouts_from_api as $layout_data) { $data_to_insert = [/*...*/]; $DB->insert($layouts_table, $data_to_insert); }
                Session::addMessageAfterRedirect(__('Layouts buscados e atualizados!', 'directlabelprinter'), false, INFO);
             } catch (\Exception $e) {
                Session::addMessageAfterRedirect(__('Erro ao buscar layouts: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
             }
             return; // Não continua
        }

        // --- AÇÃO: Salvar (Padrão) ---
        if (isset($input['update_defaults'])) {
            Toolbox::logInFile("debug", "[Config Class] Update Defaults POST received.");
            try {
                $selected_default_id_api = $input['default_layout_id_api'];
                $DB->update($layouts_table, ['padrao' => 0], ['padrao' => 1]);
                if ($selected_default_id_api !== null && $selected_default_id_api !== '') {
                     $DB->update($layouts_table, ['padrao' => 1], ['id_api' => $selected_default_id_api]);
                     if (class_exists(DirectLabelPrinterActions::class) /*...*/) { DirectLabelPrinterActions::makeAuthenticatedApiRequest('POST', "/api/layouts/{$selected_default_id_api}/selecionar-padrao/"); }
                }
                Session::addMessageAfterRedirect(__('Layout padrão atualizado!', 'directlabelprinter'), false, INFO);
            } catch (\Exception $e) {
                Session::addMessageAfterRedirect(__('Erro ao atualizar layout padrão: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
            }
            // Deixar o CommonDBTM fazer o update padrão (embora não tenhamos campos padrão para salvar)
            // ou apenas retornar.
            // parent::post_update(); // Opcional
            return;
        }
    }
}
?>