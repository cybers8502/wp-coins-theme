<?php

require_once get_template_directory() . '/inc/App.php';

require_once get_template_directory() . '/inc/Admin/AdminMenuManager.php';
require_once get_template_directory() . '/inc/Admin/ThemeSetupService.php';

require_once get_template_directory() . '/inc/Admin/ACFFieldsManager/CoinACFFieldsManager.php';
require_once get_template_directory() . '/inc/Admin/ACFFieldsManager/DesignerACFFieldsManager.php';
require_once get_template_directory() . '/inc/Admin/ACFFieldsManager/CoinPriceACFFieldsManager.php';
require_once get_template_directory() . '/inc/Admin/ACFFieldsManager/CoinCollectionACFFieldsManager.php';

require_once get_template_directory() . '/inc/Admin/PostTypes/CoinPostTypeRegistrar.php';
require_once get_template_directory() . '/inc/Admin/PostTypes/DesignerPostTypeRegistrar.php';
require_once get_template_directory() . '/inc/Admin/PostTypes/CoinPricePostTypeRegistrar.php';
require_once get_template_directory() . '/inc/Admin/PostTypes/CoinCollectionPostTypeRegistrar.php';

require_once get_template_directory() . '/inc/Assets/AssetManager.php';

require_once get_template_directory() . '/inc/Rest/Controllers/CoinPriceController.php';
require_once get_template_directory() . '/inc/Rest/Controllers/CoinCollectionController.php';
require_once get_template_directory() . '/inc/Rest/ApiRouter.php';

require_once get_template_directory() . '/inc/Security/CorsService.php';


(new \Coins\App())->boot();

if (defined('WP_CLI') && WP_CLI) {
    require_once get_template_directory() . '/inc/Console/FetchNbuDataCommand.php';

    \Coins\Console\FetchNbuDataCommand::register();
}