<?php
// front/config.php

include("../../../inc/includes.php");

use GlpiPlugin\Directlabelprinter\Layout;
use GlpiPlugin\Directlabelprinter\Menu;
use GlpiPlugin\Directlabelprinter\PrintServer;

if (!PrintServer::canView()) {
    Html::displayRightError();
}

Html::header(
    Menu::getMenuName(),
    $_SERVER['PHP_SELF'],
    "config",
    strtolower(Menu::class)
);

$sections = [
    [
        'title' => PrintServer::getTypeName(2),
        'icon'  => 'fas fa-print',
        'url'   => PrintServer::getSearchURL(),
    ],
    [
        'title' => Layout::getTypeName(2),
        'icon'  => 'fas fa-tag',
        'url'   => Layout::getSearchURL(),
    ],
];
?>
<div class="d-flex flex-wrap gap-3 justify-content-center mt-4">
   <?php foreach ($sections as $section) { ?>
   <a class="card text-decoration-none text-body" style="width: 220px;" href="<?php echo htmlescape($section['url']); ?>">
      <div class="card-body text-center">
         <i class="<?php echo htmlescape($section['icon']); ?> fa-3x mb-3"></i>
         <div class="fw-bold"><?php echo htmlescape($section['title']); ?></div>
      </div>
   </a>
   <?php } ?>
</div>
<?php

Html::footer();
