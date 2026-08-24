<?php
// front/layout.php

include("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\Layout;
use GlpiPlugin\Directlabelprinter\Menu;

if (!Layout::canView()) {
    Html::displayRightError();
}

Html::header(
    Layout::getTypeName(2),
    $_SERVER['PHP_SELF'],
    "config",
    strtolower(Menu::class),
    "layout"
);

Search::show(Layout::class);

Html::footer();
