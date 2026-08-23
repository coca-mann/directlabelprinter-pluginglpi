<?php

use Glpi\Plugin\Hooks;
use Glpi\Application\View\TemplateRenderer;
use Glpi\System\RequirementsManager;
use GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions; // Vamos criar esta classe a seguir

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

    $migration = new Migration(PLUGIN_DIRECTLABELPRINTER_VERSION);

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();

    $printservers_table = 'glpi_plugin_directlabelprinter_printservers';
    if (!$DB->tableExists($printservers_table)) {
        $DB->doQuery("CREATE TABLE `$printservers_table` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `name` VARCHAR(100) NOT NULL,
                        `url` VARCHAR(255) NOT NULL,
                        `api_key` TEXT DEFAULT NULL COMMENT 'Encrypted via GLPIKey',
                        `default_printer_name` VARCHAR(255) DEFAULT NULL,
                        `comment` TEXT DEFAULT NULL,
                        `date_creation` TIMESTAMP NULL DEFAULT NULL,
                        `date_mod` TIMESTAMP NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`name`)
                      ) ENGINE=InnoDB
                      DEFAULT CHARSET={$default_charset}
                      COLLATE={$default_collation}") or die("Error creating table $printservers_table");
    }

    $layouts_table = 'glpi_plugin_directlabelprinter_layouts';
    if (!$DB->tableExists($layouts_table)) {
        $DB->doQuery("CREATE TABLE `$layouts_table` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `name` VARCHAR(100) NOT NULL,
                        `comment` TEXT DEFAULT NULL,
                        `width_mm` DECIMAL(10,2) NOT NULL DEFAULT '50.00',
                        `height_mm` DECIMAL(10,2) NOT NULL DEFAULT '50.00',
                        `font_choice` VARCHAR(50) NOT NULL DEFAULT 'dejavusans',
                        `custom_font_documents_id` INT UNSIGNED DEFAULT NULL,
                        `elements` LONGTEXT DEFAULT NULL COMMENT 'JSON array of layout elements',
                        `date_creation` TIMESTAMP NULL DEFAULT NULL,
                        `date_mod` TIMESTAMP NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`name`),
                        KEY `custom_font_documents_id` (`custom_font_documents_id`)
                      ) ENGINE=InnoDB
                      DEFAULT CHARSET={$default_charset}
                      COLLATE={$default_collation}") or die("Error creating table $layouts_table");
    } elseif (!$DB->fieldExists($layouts_table, 'elements')) {
        // Upgrading from the pre-redesign schema: old columns (id_api, nome, descricao,
        // largura_mm, altura_mm, altura_titulo_mm, tamanho_fonte_titulo,
        // margem_vertical_qr_mm, nome_fonte, padrao) are incompatible with the new shape.
        // This plugin has no external installs yet, so we drop and recreate rather than migrate data.
        $migration->dropTable($layouts_table);
        $DB->doQuery("CREATE TABLE `$layouts_table` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `name` VARCHAR(100) NOT NULL,
                        `comment` TEXT DEFAULT NULL,
                        `width_mm` DECIMAL(10,2) NOT NULL DEFAULT '50.00',
                        `height_mm` DECIMAL(10,2) NOT NULL DEFAULT '50.00',
                        `font_choice` VARCHAR(50) NOT NULL DEFAULT 'dejavusans',
                        `custom_font_documents_id` INT UNSIGNED DEFAULT NULL,
                        `elements` LONGTEXT DEFAULT NULL COMMENT 'JSON array of layout elements',
                        `date_creation` TIMESTAMP NULL DEFAULT NULL,
                        `date_mod` TIMESTAMP NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`name`),
                        KEY `custom_font_documents_id` (`custom_font_documents_id`)
                      ) ENGINE=InnoDB
                      DEFAULT CHARSET={$default_charset}
                      COLLATE={$default_collation}") or die("Error recreating table $layouts_table");
    }

    $layout_itemtype_table = 'glpi_plugin_directlabelprinter_layout_itemtype';
    if (!$DB->tableExists($layout_itemtype_table)) {
        $DB->doQuery("CREATE TABLE `$layout_itemtype_table` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `plugin_directlabelprinter_layouts_id` INT UNSIGNED NOT NULL,
                        `itemtype` VARCHAR(100) NOT NULL,
                        `is_default` TINYINT NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`plugin_directlabelprinter_layouts_id`, `itemtype`),
                        KEY `itemtype` (`itemtype`)
                      ) ENGINE=InnoDB
                      DEFAULT CHARSET={$default_charset}
                      COLLATE={$default_collation}") or die("Error creating table $layout_itemtype_table");
    }

    $userprefs_table = 'glpi_plugin_directlabelprinter_userprefs';
    if (!$DB->tableExists($userprefs_table)) {
        $DB->doQuery("CREATE TABLE `$userprefs_table` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `users_id` INT UNSIGNED NOT NULL,
                        `plugin_directlabelprinter_printservers_id` INT UNSIGNED NOT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`users_id`)
                      ) ENGINE=InnoDB
                      DEFAULT CHARSET={$default_charset}
                      COLLATE={$default_collation}") or die("Error creating table $userprefs_table");
    }

    // Legacy pre-redesign table, no longer used.
    $legacy_auth_table = 'glpi_plugin_directlabelprinter_auth';
    if ($DB->tableExists($legacy_auth_table)) {
        $migration->dropTable($legacy_auth_table);
    }

    $migration->executeMigration();

    return true;
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
        'glpi_plugin_directlabelprinter_printservers',
        'glpi_plugin_directlabelprinter_layouts',
        'glpi_plugin_directlabelprinter_layout_itemtype',
        'glpi_plugin_directlabelprinter_userprefs',
        // Legacy, dropped defensively in case install() never ran on this DB.
        'glpi_plugin_directlabelprinter_auth',
    ];

    foreach ($tables_to_drop as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    return true;
}


/**
 * Hook para adicionar ações (em massa e/ou individuais) aos itemtypes.
 *
 * @param string $itemtype O tipo de item (ex: 'Computer')
 *
 * @return array Array de ações a serem adicionadas
 */
function plugin_directlabelprinter_MassiveActions($itemtype) {
    // Linha de log (opcional, remova se não for mais necessária)
    // Toolbox::logInFile("debug", "[DirectLabelPrinter] Hook _MassiveActions chamado para itemtype: " . $itemtype);

    $actions = [];

    $asset_types = \GlpiPlugin\Directlabelprinter\AssetTypes::WHITELIST;

    if (in_array($itemtype, $asset_types)) {
        $action_key = 'print_label';
        $action_label = "<i class='fas fa-print'></i> " . __('Imprimir Etiqueta', 'directlabelprinter');
        // ---> Use FQCN (Nome Completo da Classe) <---
        $action_class = \GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions::class;
        $separator = \MassiveAction::CLASS_ACTION_SEPARATOR; // ---> Use FQCN <---

        $actions[$action_class . $separator . $action_key] = $action_label;
    }

    return $actions;
}

/**
 * post_item_form hook — renders the "Imprimir Etiqueta" button on an asset's form.
 *
 * @param array $params ['item' => CommonDBTM, 'options' => array]
 */
function plugin_directlabelprinter_display_print_button(array $params) {
    $item = $params['item'];
    if ($item->isNewItem()) {
        return;
    }

    $itemtype = $item::class;
    $layout_id = \GlpiPlugin\Directlabelprinter\LayoutItemtype::getDefaultLayoutId($itemtype);
    if (!$layout_id) {
        return; // no layout configured for this itemtype yet
    }

    global $DB;
    $layout_options = [];
    foreach (\GlpiPlugin\Directlabelprinter\LayoutItemtype::getLayoutsForItemtype($itemtype) as $layouts_id) {
        $layout = new \GlpiPlugin\Directlabelprinter\Layout();
        if ($layout->getFromDB($layouts_id)) {
            $layout_options[] = ['id' => $layouts_id, 'name' => $layout->fields['name']];
        }
    }

    $server_options = [];
    foreach ($DB->request(['FROM' => 'glpi_plugin_directlabelprinter_printservers']) as $row) {
        $server_options[] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
    $default_server_id = \GlpiPlugin\Directlabelprinter\UserPref::getPreferredServerId(\Session::getLoginUserID());

    $single_item = \GlpiPlugin\Directlabelprinter\DirectLabelPrinterActions::resolveItemsForPrint($itemtype, [['id' => $item->getID()]]);

    echo "<tr class='tab_bg_1'><td colspan='2'>";
    echo "<button type='button' class='btn btn-secondary' id='dlp-print-single-btn'>";
    echo "<i class='fas fa-print'></i> " . __('Imprimir Etiqueta', 'directlabelprinter');
    echo "</button>";
    $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    echo "<script type='text/javascript'>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('dlp-print-single-btn');
            if (btn) {
                btn.addEventListener('click', function() {
                    window.directLabelPrinter.openPrintModal(" .
                        json_encode($itemtype, $json_flags) . ", " .
                        json_encode($single_item, $json_flags) . ", " .
                        json_encode($layout_options, $json_flags) . ", " .
                        json_encode($layout_id, $json_flags) . ", " .
                        json_encode($server_options, $json_flags) . ", " .
                        json_encode($default_server_id, $json_flags) .
                    ");
                });
            }
        });
    </script>";
    echo "</td></tr>";
}

?>