<?php
// front/config.php

include ("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\Config;
use Html;

// Redirecionar da lista para o formulário do item ID 1
$config_id = Config::getOrCreateDefaultConfig();
Html::redirect(Config::getFormURL(['id' => $config_id]));
exit;
?>