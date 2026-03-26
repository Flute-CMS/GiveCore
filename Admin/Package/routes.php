<?php

use Flute\Core\Router\Router;
use Flute\Modules\GiveCore\Admin\Package\Screens\CustomDriverEditScreen;
use Flute\Modules\GiveCore\Admin\Package\Screens\CustomDriversListScreen;
use Flute\Modules\GiveCore\Admin\Package\Screens\GivePrivilegeScreen;

Router::screen('/admin/givecore', GivePrivilegeScreen::class);
Router::screen('/admin/givecore/custom-drivers', CustomDriversListScreen::class);
Router::screen('/admin/givecore/custom-drivers/add', CustomDriverEditScreen::class);
Router::screen('/admin/givecore/custom-drivers/{alias}/edit', CustomDriverEditScreen::class);
