<?php

require_once get_template_directory() . '/vendor/autoload.php';

(new \Coins\App())->boot();

if (defined('WP_CLI') && WP_CLI) {
    \Coins\Console\FetchNbuDataCommand::register();
}