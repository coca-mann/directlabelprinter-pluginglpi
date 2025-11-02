<?php
// src/Config.php

namespace GlpiPlugin\Directlabelprinter;

use CommonDBTM;
use Html;
use Session;
use Toolbox;
use Plugin; // Ainda pode ser necessário para outras coisas
use DateTime;
use Glpi\Toolbox\Sanitizer;

class Config extends CommonDBTM
{
    static $rightname = 'config'; // Permissão necessária

    static function getTable($classname = null) {
        return 'glpi_plugin_directlabelprinter_auth';
    }

    static function getTypeName($nb = 0) {
        return __('Direct Label Printer', 'directlabelprinter');
    }

    /**
     * Define como este item aparece no menu (hook 'menu_toadd')
     */
    static function getMenuContent() {
        // --- LOG DE EXECUÇÃO DO MÉTODO ---
        // Este log confirma que o GLPI não só carregou a classe, mas também chamou o método
        \Toolbox::logInFile("debug", "[Config Class] getMenuContent() EXECUTADO.");

        // Garante que a linha ID 1 exista
        $config_id = self::getOrCreateDefaultConfig();

        return [
            'title' => self::getTypeName(),
            'page'  => self::getFormURL(['id' => $config_id]), // Aponta para o formulário
            'icon'  => 'fas fa-print',
        ];
    }

    /**
     * Garante que a linha de configuração (ID 1) exista
     */
    static function getOrCreateDefaultConfig() {
        global $DB;
        $table = self::getTable();
        // Usar $DB->request() e ->current()
        $auth_data = $DB->request(['FROM' => $table, 'LIMIT' => 1])->current();

        if (empty($auth_data)) {
            // Criar linha placeholder
            $DB->insert($table, [
                'id' => 1, // Força ID 1
                'user' => 'default'
            ]);
            return 1;
        }
        return $auth_data['id'];
    }

    /**
     * Sobrescreve o URL do formulário (sem Plugin::getWebDir)
     */
    static function getFormURL($params = []) {
         global $CFG_GLPI; // Aceder à config global
         $url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/directlabelprinter/front/config.form.php";
         return $url . Html::paramsToString($params);
    }
    
    /**
     * Sobrescreve o URL da lista (sem Plugin::getWebDir)
     */
    static function getSearchURL($full = false) {
         global $CFG_GLPI; // Aceder à config global
         $url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/directlabelprinter/front/config.php";
         // Parâmetro $full é ignorado aqui, mas mantido pela assinatura
         return $url;
    }

    /**
     * Exibe o formulário de configuração (sem Twig)
     */
    public function showForm($ID, $options = []) {
        global $DB, $CFG_GLPI;
        $this->initForm($ID, $options); // Carrega os dados de $this->fields

        // Obter layouts
        $layouts_table = 'glpi_plugin_directlabelprinter_layouts';
        $layouts_result = $DB->request(['FROM' => $layouts_table]);
        $layouts_from_db = []; foreach ($layouts_result as $layout) { $layouts_from_db[] = $layout; }
        $layout_options = []; $default_layout_id_api = null;
        foreach ($layouts_from_db as $layout) {
            if (isset($layout['id_api']) && isset($layout['nome'])) $layout_options[$layout['id_api']] = $layout['nome'];
            if (isset($layout['padrao']) && $layout['padrao'] == 1) $default_layout_id_api = $layout['id_api'] ?? null;
        }

        // Formulário aponta para o URL correto
        echo "<form name='config_form_directlabelprinter' action='" . self::getFormURL() . "' method='POST' class='glpi_form' autocomplete='off'>";
        
        // Gerar Token CSRF
        // Usar o método da classe base CommonDBTM para obter o nome do token
        $csrf_token_name = $this->getCsrfTokenName('config');
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken($csrf_token_name)]);
        echo Html::hidden('id', ['value' => $this->fields['id']]);

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
        echo "      <button type='submit' name='fetch_layouts' value='1' class='btn btn-secondary btn-sm'>" . __('Fetch Layouts', 'directlabelprinter') . "</button>";
        echo "      <div class='mt-4'>";
        echo            __('Default Layout', 'directlabelprinter') . "&nbsp;";
        Dropdown::showFromArray("default_layout_id_api", $layout_options, ['value' => $default_layout_id_api]);
        echo "      </div>";

        echo "      <h4 class='mt-5'>" . __('Layout Details', 'directlabelprinter') . "</h4>";
        if (!empty($layouts_from_db)) {
            // ... (Tabela HTML dos layouts) ...
        } else {
            echo "  <p>" . __('No layouts fetched yet.', 'directlabelprinter') . "</p>";
        }
        echo "  </div>";
        echo "  <div class='card-footer d-flex justify-content-end py-6 px-9'>";
        // Nome 'update' é o padrão que o CommonDBTM procura
        echo "      <button type='submit' name='update' value='" . _x('button', 'Save') . "' class='btn btn-primary'>";
        echo "          <i class='ti ti-device-floppy me-2'></i> " . _x('button', 'Save');
        echo "      </button>";
        echo "  </div>";
        echo "</div>";

        echo "</form>";
        Html::footer(); // CommonDBTM::showForm não chama Html::footer()
        return true;
    }

    /**
     * Processa a lógica POST personalizada antes da atualização padrão
     */
    function post_update()
    {
        global $DB;
        $input = $this->input; // $this->input é preenchido pelo CommonDBTM::update()
        $auth_table = self::getTable();
        $layouts_table = 'glpi_plugin_directlabelprinter_layouts';
        
        // Flag para saber se já tratamos a ação
        $action_done = false;

        // --- AÇÃO: Testar Conexão ---
        if (isset($input['test_connection'])) {
            Toolbox::logInFile("debug", "[Config Class] Test Connection POST received.");
            try {
                // ... (Lógica cURL para /api/auth/token/) ...
                $api_url = rtrim($input['api_url'], '/'); $username = $input['api_user']; $password = $input['api_password'];
                if (empty($api_url) || empty($username) || empty($password)) throw new \Exception(/*...*/);
                $token_endpoint = $api_url . '/api/auth/token/';
                $ch = curl_init(/*...*/); /*...*/ $api_response_body = curl_exec($ch); /*...*/ curl_close($ch);
                if ($curl_error || $http_code != 200) throw new \Exception(/*...*/);
                $tokens = json_decode($api_response_body, true); if (empty($tokens['access'])) throw new \Exception(/*...*/);
                
                $expires_datetime = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');
                $data_to_save = [
                    'id'                   => $this->fields['id'], // Manter o ID
                    'user'                 => $username, 'api_url' => $api_url,
                    'access_token'         => $tokens['access'], 'refresh_token' => $tokens['refresh'],
                    'access_token_expires' => $expires_datetime
                ];
                // Atualiza a linha de config (usando o método da classe base)
                $this->update($data_to_save); 
                Session::addMessageAfterRedirect(__('Autenticação bem-sucedida! Tokens salvos.', 'directlabelprinter'), false, INFO);
            } catch (\Exception $e) {
                Session::addMessageAfterRedirect(__('Erro na conexão: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
            }
            $action_done = true;
        }

        // --- AÇÃO: Buscar Layouts ---
        if (isset($input['fetch_layouts'])) {
             Toolbox::logInFile("debug", "[Config Class] Fetch Layouts POST received.");
             try {
                if (!class_exists(DirectLabelPrinterActions::class) /*...*/) throw new \Exception(/*...*/);
                $layouts_from_api = DirectLabelPrinterActions::makeAuthenticatedApiRequest('GET', '/api/layouts/');
                if (!is_array($layouts_from_api)) throw new \Exception(/*...*/);
                $DB->query("TRUNCATE TABLE `$layouts_table`");
                foreach ($layouts_from_api as $layout_data) { $data_to_insert = [/*...*/]; $DB->insert($layouts_table, $data_to_insert); }
                Session::addMessageAfterRedirect(__('Layouts buscados e atualizados!', 'directlabelprinter'), false, INFO);
             } catch (\Exception $e) {
                Session::addMessageAfterRedirect(__('Erro ao buscar layouts: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
             }
             $action_done = true;
        }

        // --- AÇÃO: Salvar (Padrão) ---
        // Renomear o botão Salvar no Twig para 'update' (padrão do CommonDBTM)
        // O CommonDBTM::update() trata o 'update' por padrão
        // Mas o nosso botão chama-se 'update_defaults'
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
            $action_done = true;
        }
        
        // Se nenhuma ação customizada foi feita, deixa o CommonDBTM tentar
        if (!$action_done) {
            parent::post_update();
        }
    }
}
?>