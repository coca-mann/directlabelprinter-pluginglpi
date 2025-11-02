<?php
// src/Menu.php

namespace GlpiPlugin\Directlabelprinter;

/**
 * Classe para definir as entradas de menu do plugin.
 */
class Menu
{
    /**
     * Define o conteúdo do menu (chamado pelo hook menu_toadd).
     *
     * @return array
     */
    static function getMenuContent()
    {
        // Este é o array que estávamos a tentar colocar no setup.php
        return [
            'title' => __('Direct Label Printer', 'directlabelprinter'), // Título exibido
            'page'  => 'plugins.directlabelprinter.config', // O NOME DA ROTA do Controller
            'icon'  => 'fas fa-print', // Ícone
            // '_rank' => 50, // Opcional: ordem no menu
        ];
    }

    /**
     * Define o nome principal do menu (necessário se houver sub-itens,
     * mas é boa prática ter).
     *
     * @param integer $nb Número (para pluralização)
     *
     * @return string
     */
    static function getMenuName($nb = 0)
    {
         return __('Direct Label Printer', 'directlabelprinter');
    }
}