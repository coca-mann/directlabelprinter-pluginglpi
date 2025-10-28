<?php

namespace GlpiPlugin\Directlabelprinter\Controller;

use Glpi\Controller\AbstractController; // Classe base para Controllers
use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\Sanitizer;
use GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions; // Para makeAuthenticatedApiRequest
use Config as CoreConfig;
use Html; // Para Redirect e talvez mensagens
use Session;
use Toolbox;
use Plugin;
use Symfony\Component\HttpFoundation\Request; // Para lidar com a requisição
use Symfony\Component\HttpFoundation\Response; // Tipo de retorno esperado
use Symfony\Component\HttpFoundation\RedirectResponse; // Para redirecionamentos
use Symfony\Component\Routing\Attribute\Route; // Para definir a rota

class ConfigController extends AbstractController {

    /**
     * Manipula requisições GET e POST para a página de configuração.
     */
    #[Route(
        '/plugins/directlabelprinter/config', // A URL da página de configuração
        name: 'plugins.directlabelprinter.config', // Nome único da rota
        methods: ['GET', 'POST'] // Permite GET (exibir) e POST (salvar/testar/buscar)
    )]
    public function displayOrProcessConfig(Request $request): Response {
        global $DB, $CFG_GLPI; // Acesso global necessário

        Toolbox::logInFile("debug", "[ConfigController] Acessado. Método: " . $request->getMethod());

        // --- Verificação de Permissões ---
        // A verificação é feita aqui dentro do controller
        Session::checkLoginUser(); // Garante login
        if (!Session::haveRight('config', UPDATE)) { // Usar haveRight para poder retornar erro
            Toolbox::logInFile("error", "[ConfigController] Acesso negado. Falta permissão config->UPDATE.");
            // Retorna uma resposta de erro (pode ser uma página Twig de erro)
            // Por agora, vamos usar Html::displayRightError() que encerra o script
            return new Response(Html::displayRightError(false), 403); // Retorna HTML de erro com status 403
        }
        Toolbox::logInFile("debug", "[ConfigController] Permissão OK.");

        // --- Variáveis ---
        $auth_table = 'glpi_plugin_directlabelprinter_auth';
        $layouts_table = 'glpi_plugin_directlabelprinter_layouts';
        // Gera a URL da própria rota para action do formulário e redirecionamentos
        $config_page_url = $this->generateURL('plugins.directlabelprinter.config');

        // --- Lógica de Processamento POST ---
        if ($request->isMethod('POST')) {
            Toolbox::logInFile("debug", "[ConfigController] Processando POST.");
            // Verificar CSRF Token (Nome deve coincidir com o gerado para o Twig)
            $csrf_name = 'plugin_directlabelprinter_config';
             if (!Session::checkCSRF($csrf_name, $request->request->get('_glpi_csrf_token', ''))) {
                  Toolbox::logInFile("error", "[ConfigController] Falha na verificação CSRF.");
                  Session::addMessageAfterRedirect(__('Ação inválida ou expirada.'), true, ERROR);
                  return new RedirectResponse($config_page_url);
             }
             Toolbox::logInFile("debug", "[ConfigController] Verificação CSRF OK.");

            try {
                // Identificar a Ação pelos botões de submit (usando $request->request->has())
                if ($request->request->has('test_connection')) {
                    Toolbox::logInFile("debug", "[ConfigController] Ação Test Connection.");
                    // Obter dados do POST usando o objeto Request
                    $api_url = rtrim($request->request->get('api_url', ''), '/');
                    $username = $request->request->get('api_user', '');
                    $password = $request->request->get('api_password', ''); // Senha vem do POST

                    if (empty($api_url) || empty($username) || empty($password)) {
                        throw new \Exception(__('Preencha URL, Usuário e Senha.', 'directlabelprinter'));
                    }

                    // Lógica cURL para /api/auth/token/
                    $token_endpoint = $api_url . '/api/auth/token/';
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $token_endpoint);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                        'username' => $username,
                        'password' => $password
                    ]));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    
                    $api_response_body = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    
                    if ($curl_error || $http_code != 200) {
                        $error_details = json_decode($api_response_body, true);
                        $error_message = $error_details['detail'] ?? "Erro na autenticação ($http_code)";
                        throw new \Exception($error_message);
                    }
                    
                    $tokens = json_decode($api_response_body, true);
                    if (empty($tokens['access']) || empty($tokens['refresh'])) {
                        throw new \Exception(__('Resposta de autenticação inválida.', 'directlabelprinter'));
                    }

                    // Calcular expiração e salvar/atualizar na tabela _auth
                    $expires_timestamp = time() + 3600; 
                    $expires_datetime = date('Y-m-d H:i:s', $expires_timestamp);
                    $data_to_save = [
                        'api_url' => $api_url,
                        'user' => $username,
                        'password' => $password, // Considerar criptografar
                        'access_token' => $tokens['access'],
                        'refresh_token' => $tokens['refresh'],
                        'access_token_expires' => $expires_datetime
                    ];
                    $existing_result = $DB->request(['FROM' => $auth_table, 'LIMIT' => 1]); 
                    $existing = $existing_result->current();
                    if (empty($existing)) { 
                        $DB->insert($auth_table, $data_to_save); 
                    } else { 
                        $DB->update($auth_table, $data_to_save, ['id' => $existing['id']]); 
                    }

                    Session::addMessageAfterRedirect(__('Autenticação bem-sucedida! Tokens salvos.', 'directlabelprinter'), false, INFO); // Usar 'false' para não mostrar duplicado

                } else if ($request->request->has('fetch_layouts')) {
                    Toolbox::logInFile("debug", "[ConfigController] Ação Fetch Layouts.");
                    // Lógica para chamar makeAuthenticatedApiRequest('GET', '/api/layouts/')
                    $layouts_from_api = DirectLabelPrinterActions::makeAuthenticatedApiRequest('GET', '/api/layouts/');
                    if (!is_array($layouts_from_api)) {
                        throw new \Exception(__('Resposta inválida da API de layouts.', 'directlabelprinter'));
                    }

                    // Limpar tabela _layouts e inserir novos
                     $DB->query("TRUNCATE TABLE `$layouts_table`");
                     foreach ($layouts_from_api as $layout_data) {
                         $layout_to_insert = [
                             'id_api' => $layout_data['id'] ?? null,
                             'nome' => $layout_data['nome'] ?? '',
                             'descricao' => $layout_data['descricao'] ?? '',
                             'largura_mm' => $layout_data['largura_mm'] ?? null,
                             'altura_mm' => $layout_data['altura_mm'] ?? null,
                             'altura_titulo_mm' => $layout_data['altura_titulo_mm'] ?? null,
                             'tamanho_fonte_titulo' => $layout_data['tamanho_fonte_titulo'] ?? null,
                             'margem_vertical_qr_mm' => $layout_data['margem_vertical_qr_mm'] ?? null,
                             'nome_fonte' => $layout_data['nome_fonte'] ?? '',
                             'padrao' => $layout_data['padrao'] ?? 0
                         ];
                         $DB->insert($layouts_table, $layout_to_insert);
                     }

                    Session::addMessageAfterRedirect(__('Layouts buscados e atualizados!', 'directlabelprinter'), false, INFO);

                } else if ($request->request->has('update_defaults')) {
                    Toolbox::logInFile("debug", "[ConfigController] Ação Update Defaults.");
                    // Lógica para pegar 'default_layout_id_api', resetar DB, setar novo, chamar API /selecionar-padrao/
                     $selected_default_id_api = $request->request->get('default_layout_id_api');
                     $DB->update($layouts_table, ['padrao' => 0], ['padrao' => 1]);
                     if ($selected_default_id_api !== null && $selected_default_id_api !== '') {
                          $DB->update($layouts_table, ['padrao' => 1], ['id_api' => $selected_default_id_api]);
                          DirectLabelPrinterActions::makeAuthenticatedApiRequest('POST', "/api/layouts/{$selected_default_id_api}/selecionar-padrao/");
                     }

                    Session::addMessageAfterRedirect(__('Layout padrão atualizado!', 'directlabelprinter'), false, INFO);
                } else {
                     Toolbox::logInFile("warning", "[ConfigController] Ação POST não reconhecida.");
                     Session::addMessageAfterRedirect(__('Ação não reconhecida.'), true, WARNING);
                }

            } catch (\Exception $e) {
                Toolbox::logInFile("error", "[ConfigController] Erro no processamento POST: " . $e->getMessage());
                // Adiciona a mensagem de erro para ser exibida após o redirecionamento
                Session::addMessageAfterRedirect(__('Erro: ', 'directlabelprinter') . $e->getMessage(), true, ERROR);
            }

            // Redirecionar de volta para a página de configuração (método GET) após processar POST
            return new RedirectResponse($config_page_url);
        }

        // --- Lógica de Exibição GET ---
        Toolbox::logInFile("debug", "[ConfigController] Preparando dados para exibição GET.");
        // Obter dados atuais do DB (IDÊNTICO ao front/config.php anterior)
        $current_auth_result = $DB->request(['FROM' => $auth_table, 'LIMIT' => 1]);
        $current_auth = $current_auth_result->current() ?? [];
        $layouts_result = $DB->request(['FROM' => $layouts_table]);
        $layouts_from_db = []; 
        foreach ($layouts_result as $layout) { 
            $layouts_from_db[] = $layout; 
        }
        $layout_options = []; 
        $default_layout_id_api = null;
        foreach ($layouts_from_db as $layout) {
            $layout_options[] = [
                'id' => $layout['id_api'],
                'name' => $layout['nome']
            ];
            if ($layout['padrao'] == 1) {
                $default_layout_id_api = $layout['id_api'];
            }
        }

        // Preparar dados para o Twig (IDÊNTICO, gerar novo CSRF para o form GET)
        $csrf_token_name = 'plugin_directlabelprinter_config';
        $csrf_token_value = Session::getNewCSRFToken($csrf_token_name);
        $twig_data = [
             'plugin_name'           => 'directlabelprinter',
             'config_page_url'       => $config_page_url, // Passa a URL da rota para o action do form
             'current_auth'          => $current_auth,
             'layouts'               => $layouts_from_db,
             'layout_options'        => $layout_options,
             'default_layout_id_api' => $default_layout_id_api,
             'can_edit'              => true, // Já verificamos permissão acima
             'csrf_token'            => $csrf_token_value // Passa o token para o form
        ];
        Toolbox::logInFile("debug", "[ConfigController] Dados Twig preparados. Renderizando template...");

        // Renderizar o template Twig usando o helper do AbstractController [cite: 1816]
        try {
            // Usar o template que contém apenas o formulário
            return $this->render('@directlabelprinter/config_form_content.html.twig', $twig_data);
        } catch (\Exception $e) {
             Toolbox::logInFile("error", "[ConfigController] Erro ao renderizar Twig: " . $e->getMessage());
             // Retorna uma resposta de erro
             return new Response("Erro ao renderizar template: " . $e->getMessage(), 500);
        }
    } // Fim do método displayOrProcessConfig
} // Fim da classe ConfigController