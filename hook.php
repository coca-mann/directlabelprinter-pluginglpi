<?php

use Glpi\Plugin\Hooks;
use Glpi\Application\View\TemplateRenderer;
use Glpi\System\RequirementsManager;
use GlpiPlugin\Directlabelprinter\Config as PluginConfig; // Import your plugin's Config class
use GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions; // Vamos criar esta classe a seguir
use Config as CoreConfig; // Import the core Config class

// Import necessary classes for database operations
use DBConnection;
use Migration;
use MassiveAction;
use Toolbox;

define('PLUGIN_DIRECTLABELPRINTER_VERSION', '0.0.1'); // Make sure this matches your setup.php version

/**
 * Install hook
 * - Creates database tables
 *
 * @return boolean
 */
function plugin_directlabelprinter_install() {
    global $DB;

    // Instantiate migration helper with current plugin version
    $migration = new Migration(PLUGIN_DIRECTLABELPRINTER_VERSION); // [cite: 3226-3227, 3908, 3917]

    $auth_table_name = 'glpi_plugin_directlabelprinter_auth';
    $layouts_table_name = 'glpi_plugin_directlabelprinter_layouts';

    // Get default charset and collation for table creation
    $default_charset = DBConnection::getDefaultCharset(); // [cite: 3907]
    $default_collation = DBConnection::getDefaultCollation(); // [cite: 3907]

    // --- Create Auth Table ---
    // Check if table already exists before creating [cite: 3228-3229, 3909]
    if (!$DB->tableExists($auth_table_name)) {
        $query_auth = "CREATE TABLE `$auth_table_name` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `user` VARCHAR(255) DEFAULT NULL,
                        `password` VARCHAR(255) DEFAULT NULL COMMENT 'Consider encrypting this',
                        `access_token` TEXT DEFAULT NULL,
                        `refresh_token` TEXT DEFAULT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB
                      DEFAULT CHARSET={$default_charset}
                      COLLATE={$default_collation}"; // [cite: 3231-3238, 3910]
        $DB->doQuery($query_auth) or die("Error creating table $auth_table_name"); // [cite: 3910] Using doQuery instead of queryOrDie to avoid potential issues in older GLPI versions
    } else {
         // If table exists, you might add migration steps here for future plugin updates
         // Example: $migration->addField($auth_table_name, 'new_field', 'VARCHAR(255)');
    }


    // --- Create Layouts Table ---
    if (!$DB->tableExists($layouts_table_name)) {
        $query_layouts = "CREATE TABLE `$layouts_table_name` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `id_api` INT UNSIGNED DEFAULT NULL COMMENT 'ID from the external API',
                            `nome` VARCHAR(255) DEFAULT NULL,
                            `descricao` TEXT DEFAULT NULL,
                            `largura_mm` DECIMAL(10,2) DEFAULT NULL,
                            `altura_mm` DECIMAL(10,2) DEFAULT NULL,
                            `altura_titulo_mm` DECIMAL(10,2) DEFAULT NULL,
                            `tamanho_fonte_titulo` INT DEFAULT NULL,
                            `margem_vertical_qr_mm` DECIMAL(10,2) DEFAULT NULL,
                            `nome_fonte` VARCHAR(255) DEFAULT NULL,
                            `padrao` TINYINT(1) NOT NULL DEFAULT '0',
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `idx_id_api` (`id_api`) COMMENT 'Ensure API ID is unique'
                          ) ENGINE=InnoDB
                          DEFAULT CHARSET={$default_charset}
                          COLLATE={$default_collation}"; // [cite: 3231-3238, 3910]
        $DB->doQuery($query_layouts) or die("Error creating table $layouts_table_name"); // [cite: 3910]
    } else {
        // Migration steps for layouts table if needed in future versions
    }

    // --- Set Default Config Values ---
    // Use the core Config class static method to set values [cite: 4099-4100]
    CoreConfig::setConfigurationValues(
        'plugin:directlabelprinter', // Context for your plugin
        PluginConfig::getDefaultValues() // Get defaults from your Config class
    );

    // Execute any pending migrations (like adding fields in updates)
    $migration->executeMigration(); // [cite: 3239-3240, 3911]

    return true; // Indicate successful installation [cite: 3878]
}

/**
 * Uninstall hook
 * - Drops database tables
 *
 * @return boolean
 */
function plugin_directlabelprinter_uninstall() {
    global $DB;

    $tables_to_drop = [
        'glpi_plugin_directlabelprinter_auth',
        'glpi_plugin_directlabelprinter_layouts'
    ]; // [cite: 3297, 3927]

    foreach ($tables_to_drop as $table) {
        if ($DB->tableExists($table)) { // [cite: 3309]
            $DB->doQuery("DROP TABLE `$table`"); // [cite: 3310-3313, 3928]
        }
    }

    // --- Remove Config Values ---
    $config = new CoreConfig();
    // Delete configuration values specific to the plugin context [cite: 4101]
    $config->deleteByCriteria(['context' => 'plugin:directlabelprinter']);

    return true; // Indicate successful uninstallation [cite: 3877]
}


/**
 * Hook para adicionar ações (em massa e/ou individuais) aos itemtypes.
 *
 * @param string $itemtype O tipo de item (ex: 'Computer')
 *
 * @return array Array de ações a serem adicionadas
 */
function plugin_directlabelprinter_MassiveActions($itemtype) {
    // ----> LINHA DE DEBUG <----
    // Escreve no log do GLPI (geralmente files/_log/php-errors.log ou debug.log)
    Toolbox::logInFile("debug", "[DirectLabelPrinter] Hook _MassiveActions chamado para itemtype: " . $itemtype);

    $actions = [];

    // Lista de itemtypes considerados "Ativos"
    $asset_types = [
        'Computer', 'Monitor', 'NetworkEquipment', 'Printer', 'Phone', 'Peripheral',
    ];

    if (in_array($itemtype, $asset_types)) {
        $action_key = 'print_label';
        $action_label = __('Imprimir Etiqueta', 'directlabelprinter');
        // Usar nome completo da classe (FQCN) aqui para garantir
        $action_class = \GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions::class;
        $separator = \MassiveAction::CLASS_ACTION_SEPARATOR; // Usar FQCN aqui também

        // Logar a chave que está sendo gerada
        Toolbox::logInFile("debug", "[DirectLabelPrinter] Gerando chave de ação: " . $action_class . $separator . $action_key);

        $actions[$action_class . $separator . $action_key] = $action_label;
    } else {
         Toolbox::logInFile("debug", "[DirectLabelPrinter] Itemtype " . $itemtype . " não é um ativo, nenhuma ação adicionada.");
    }


    return $actions;
}
?>