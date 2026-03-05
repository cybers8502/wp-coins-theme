<?php

namespace Coins;

class App
{
    public function boot(): void
    {

        new Assets\AssetManager();

        new Security\CorsService();

        $this->bootAdmin();
        $this->bootRestApi();
    }

    private function bootAdmin(): void
    {
        new Admin\ThemeSetupService();

        new Admin\AdminMenuManager();

        (new Admin\PostTypes\CoinPostTypeRegistrar())->boot();
        (new Admin\PostTypes\DesignerPostTypeRegistrar())->boot();
        (new Admin\PostTypes\CoinPricePostTypeRegistrar())->boot();
        (new Admin\PostTypes\CoinCollectionPostTypeRegistrar())->boot();

        (new Admin\ACFFieldsManager\CoinACFFieldsManager())->boot();
        (new Admin\ACFFieldsManager\DesignerACFFieldsManager())->boot();
        (new Admin\ACFFieldsManager\CoinPriceACFFieldsManager())->boot();
        (new Admin\ACFFieldsManager\CoinCollectionACFFieldsManager())->boot();
    }

    private function bootRestApi(): void
    {
        new Rest\ApiRouter();
    }
}