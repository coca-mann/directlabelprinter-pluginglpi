<?php
// front/printserver.form.php

include("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\Menu;
use GlpiPlugin\Directlabelprinter\PrintServer;

$item = new PrintServer();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    $newid = $item->add($_POST);
    Html::redirect(PrintServer::getFormURLWithID($newid));
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, 1);
    $item->redirectToList();
} else {
    Html::header(
        PrintServer::getTypeName(1),
        $_SERVER['PHP_SELF'],
        "config",
        strtolower(Menu::class),
        "printserver"
    );
    $item->display(['id' => $_GET['id'] ?? -1]);
    Html::footer();
}
