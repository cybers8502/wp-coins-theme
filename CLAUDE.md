# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress theme called **brutmaps** for a Ukrainian numismatic (coins) catalog site. It functions primarily as a headless backend — registering custom post types, taxonomies, ACF fields, a REST API, and a WP-CLI importer. There is minimal frontend (index.php, page.php, single.php are nearly empty shells).

## WP-CLI Commands

```bash
# Import souvenir coins from the National Bank of Ukraine (NBU) website
wp nbu parse-souvenir --pages=all
wp nbu parse-souvenir --pages=1-3 --per-page=100
wp nbu parse-souvenir --pages=1 --per-page=5 --limit=1
wp nbu parse-souvenir --pages=1 --dry-run   # preview without writing to DB
```

The `FetchNbuDataCommand` is only registered when `WP_CLI` is defined (see `functions.php`).

## Architecture

**Namespace:** `Coins\` → maps to `inc/` via PSR-4 (composer.json).

**Bootstrap:** `functions.php` manually requires all class files (no autoloader at runtime — composer autoload is not loaded via `vendor/autoload.php`), then calls `(new \Coins\App())->boot()`.

**`App::boot()` wires up:**
- `Assets\AssetManager` — enqueue scripts/styles
- `Security\CorsService` — CORS headers for REST API (allowed origins: brutmaps.com, brutmapsdev.cybers.pro, localhost:3033)
- `Admin\ThemeSetupService` — theme support (post-thumbnails)
- `Admin\AdminMenuManager` — WP admin menu customization
- `Admin\PostTypes\CoinPostTypeRegistrar` — registers `coins` CPT + coin taxonomies
- `Admin\PostTypes\DesignerPostTypeRegistrar` — registers `designer` CPT
- `Admin\ACFFieldsManager\CoinACFFieldsManager` — ACF fields for `coins`
- `Admin\ACFFieldsManager\DesignerACFFieldsManager` — ACF fields for `designer`
- `Rest\ApiRouter` — registers REST routes via `rest_api_init`

## Data Model

**CPT `coins`** with taxonomies:
- `coin_denomination`, `coin_quality`, `coin_material`, `coin_series`, `coin_edge`, `coin_diameter`, `coin_mintage_declared`, `coin_mintage_actual`
- `coin_nbu_category` (hierarchical, registered in `FetchNbuDataCommand`)

**ACF fields on `coins`:** `issue_date`, `diameter_mm`, `quality`, `edge`, `designers` (relationship to `designer` CPT), `mintage_declared`, `mintage_actual`, `booklet_url`, `description_html`, `images_gallery`

**CPT `designer`** — linked from coins via ACF relationship field (`designers`). ACF fields: `full_name`, `note`.

## Adding New REST Endpoints

Register routes inside `ApiRouter::registerRoutes()` using `register_rest_route()`. The method is currently empty — all new endpoints go here.

If a new controller class is needed, add it to `inc/Rest/Controllers/` under namespace `Coins\Rest\Controllers\` and require it manually in `functions.php`.

## Adding New Post Types or ACF Fields

1. Create a registrar in `inc/Admin/PostTypes/` extending the pattern of existing registrars (implement `boot()` with `add_action('init', ...)`).
2. Create an ACF manager in `inc/Admin/ACFFieldsManager/` using `acf_add_local_field_group()` on `acf/init`.
3. Instantiate both in `App::bootAdmin()`.
4. Add `require_once` calls for both files in `functions.php`.

## Plugin Dependencies

- **ACF Pro** — all custom fields use `acf_add_local_field_group()` / `update_field()`
- **WP-CLI** — required for the NBU importer command