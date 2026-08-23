<?php
// front/layout.php

include("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\Layout;

if (!Layout::canView()) {
    Html::displayRightError();
}

Html::header(
    Layout::getTypeName(2),
    $_SERVER['PHP_SELF'],
    "config",
    "directlabelprinter",
    "layout"
);

Search::show(Layout::class);

Html::footer();
