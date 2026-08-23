<?php
// front/printserver.php

include("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\PrintServer;

if (!PrintServer::canView()) {
    Html::displayRightError();
}

Html::header(
    PrintServer::getTypeName(2),
    $_SERVER['PHP_SELF'],
    "config",
    "directlabelprinter",
    "printserver"
);

Search::show(PrintServer::class);

Html::footer();
