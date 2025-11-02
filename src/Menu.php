<?php
// src/Menu.php

namespace GlpiPlugin\Directlabelprinter;

use Session; // Importar Session se for usar no futuro, mas não é estritamente necessário aqui

/**
 * Classe para definir as entradas de menu do plugin.
 * Métodos chamados pelo hook 'menu_toadd' no setup.php
 */
class Menu
{
    /**
     * Define o conteúdo do menu (chamado pelo hook menu_toadd).
     *
     * @return array
     */
    static function getMenuContent(): array
    {
        // Este é o array que define o item de menu [cite: 3966-3972]
        return [
            'title' => __('Direct Label Printer', 'directlabelprinter'), // Título exibido
            'page'  => 'plugins.directlabelprinter.config', // O NOME DA ROTA do ConfigController
            'icon'  => 'fas fa-print', // Ícone Font Awesome
            // '_rank' => 50, // Opcional: ordem no menu
        ];
    }

    /**
     * Define o nome principal do menu (necessário para o GLPI construir o menu)
     *
     * @param integer $nb Número (para pluralização)
     *
     * @return string
     */
    static function getMenuName($nb = 0): string
    {
         return __('Direct Label Printer', 'directlabelprinter');
    }
}