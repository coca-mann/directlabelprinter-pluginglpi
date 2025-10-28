<?php

namespace GlpiPlugin\Directlabelprinter;

use CommonDBTM;
use Toolbox;

/**
 * Classe para gerenciar configurações do plugin DirectLabelPrinter.
 */
class Config extends CommonDBTM
{
    /**
     * Nome da tabela de configuração
     */
    const CONFIG_TABLE = 'glpi_plugin_directlabelprinter_auth';

    /**
     * Obtém os valores de configuração do plugin.
     * 
     * @return array Array com as configurações do plugin
     */
    public static function getConfigValues(): array
    {
        global $DB;
        
        $config = [];
        
        // Buscar configurações na tabela de autenticação
        $result = $DB->request([
            'FROM' => self::CONFIG_TABLE,
            'LIMIT' => 1
        ]);
        
        $auth_data = $result->current();
        
        if ($auth_data) {
            $config['api_url'] = $auth_data['api_url'] ?? '';
            $config['user'] = $auth_data['user'] ?? '';
            // Não retornamos a senha por segurança
        }
        
        return $config;
    }

    /**
     * Salva uma configuração específica.
     * 
     * @param string $key Chave da configuração
     * @param mixed $value Valor da configuração
     * @return bool True se salvou com sucesso
     */
    public static function setConfigValue(string $key, $value): bool
    {
        global $DB;
        
        // Verificar se já existe um registro
        $result = $DB->request([
            'FROM' => self::CONFIG_TABLE,
            'LIMIT' => 1
        ]);
        
        $existing = $result->current();
        
        if ($existing) {
            // Atualizar registro existente
            return $DB->update(
                self::CONFIG_TABLE,
                [$key => $value],
                ['id' => $existing['id']]
            );
        } else {
            // Criar novo registro
            return $DB->insert(
                self::CONFIG_TABLE,
                [$key => $value]
            );
        }
    }

    /**
     * Obtém uma configuração específica.
     * 
     * @param string $key Chave da configuração
     * @param mixed $default Valor padrão se não encontrar
     * @return mixed Valor da configuração ou valor padrão
     */
    public static function getConfigValue(string $key, $default = null)
    {
        $config = self::getConfigValues();
        return $config[$key] ?? $default;
    }

    /**
     * Verifica se o plugin está configurado corretamente.
     * 
     * @return bool True se está configurado
     */
    public static function isConfigured(): bool
    {
        $config = self::getConfigValues();
        return !empty($config['api_url']);
    }
}
