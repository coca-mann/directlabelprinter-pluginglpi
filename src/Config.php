<?php

namespace GlpiPlugin\Directlabelprinter;

use CommonGLPI;
use Config as CoreConfig; // Alias the core Config class
use Html;
use Session;
use Glpi\Application\View\TemplateRenderer;
use Plugin; // Needed for getWebDir, although deprecated, might be useful for now

// Import the DbUtils class for database interaction
use Glpi\Toolbox\DbUtils;


class Config extends CoreConfig // Extend the core Config class [cite: 4082-4084]
{
    // Define a right for managing this plugin's config (optional but recommended)
    // For now, we'll use the core 'config' right. You can create a custom one later.
    static $rightname = 'config';

    /**
     * Get the name of the configuration section (used for tab title)
     *
     * @param int $nb Number (unused)
     *
     * @return string
     */
    static function getTypeName($nb = 0) {
        // Translate the plugin name
        return __('Direct Label Printer', 'directlabelprinter'); // [cite: 4085]
    }

    /**
     * Retrieves the current configuration values for this plugin context.
     *
     * @return array
     */
    static function getConfigValues() {
        // Get configuration values specific to this plugin [cite: 4086]
        return CoreConfig::getConfigurationValues('plugin:directlabelprinter');
    }

     /**
      * Defines the tab name to be added to the core Config item.
      *
      * @param CommonGLPI $item         The core Config object
      * @param int|bool   $withtemplate (unused)
      *
      * @return string Tab name or empty string
      */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        // Check if the item is the core Config object
        if ($item->getType() == CoreConfig::class) { // [cite: 4087]
            // Return the tab entry using the core helper method [cite: 4087]
            return self::createTabEntry(self::getTypeName());
        }
        return ''; // Return empty if not the core Config object
    }

    /**
     * Displays the content of the tab for the core Config item.
     *
     * @param CommonGLPI $item         The core Config object
     * @param int        $tabnum       Tab number (unused)
     * @param int|bool   $withtemplate (unused)
     *
     * @return bool True if display is successful
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        // Check if the item is the core Config object
        if ($item->getType() == CoreConfig::class) { // [cite: 4088]
            // Call the specific display function for the config page [cite: 4088]
            return self::showConfigForm($item);
        }
        return true;
    }

    /**
     * Renders the configuration form using a Twig template.
     *
     * @param CoreConfig $config The core Config object (unused but good for context)
     *
     * @return bool False if user cannot view, true otherwise
     */
    static function showConfigForm(CoreConfig $config) {
        global $CFG_GLPI; // Access global config if needed

        // Check if user has rights to view the configuration [cite: 4091]
        if (!Session::haveRight(self::$rightname, READ)) {
            return false;
        }

        // Check if user has rights to edit the configuration [cite: 4092]
        $canedit = Session::haveRight(self::$rightname, UPDATE);

        // Retrieve current plugin configuration values
        $current_config = self::getConfigValues();

        // Retrieve saved layouts from DB to populate dropdown
        $dbu = new DbUtils();
        $layouts_from_db = $dbu->getAllDataFromTable('glpi_plugin_directlabelprinter_layouts');
        $layout_options = [];
        $default_layout_id = null;
        foreach ($layouts_from_db as $layout) {
            $layout_options[$layout['id_api']] = $layout['nome']; // Use id_api as value, nome as label
            if ($layout['padrao'] == 1) {
                $default_layout_id = $layout['id_api'];
            }
        }


        // Render the Twig template, passing necessary variables [cite: 4092-4093]
        TemplateRenderer::getInstance()->display('@directlabelprinter/config.html.twig', [
            'action_url'     => Toolbox::getItemTypeFormURL(CoreConfig::class), // URL for form submission
            'current_config' => $current_config,
            'can_edit'       => $canedit,
            'layouts'        => $layouts_from_db, // Pass all layout data for display
            'layout_options' => $layout_options, // Pass options for dropdown
            'default_layout_id' => $default_layout_id, // Pass the ID of the default layout
            'plugin_name'    => 'directlabelprinter' // Pass plugin name for context
        ]);

        return true;
    }

    /**
     * Define default values for the plugin configuration.
     * Used during installation.
     *
     * @return array
     */
    static function getDefaultValues() {
        return [
            'api_url' => '',
            'api_user' => '',
            'api_password' => '', // Consider security implications
            'default_layout_id_api' => null, // Store the API ID of the default layout
        ];
    }
}