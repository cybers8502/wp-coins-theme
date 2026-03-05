<?php

namespace Coins\Console;

use WP_CLI;
use WP_Query;
use WP_Error;
use DOMDocument;
use DOMXPath;
use DOMNode;

class FetchNbuDataCommand
{
    public static function register(): void
    {
        WP_CLI::add_command(
            'nbu parse-souvenir',
            self::class,
            [
                'shortdesc' => 'Fetch souvenir coins from NBU',
                'synopsis'  => [
                    [
                        'type'        => 'assoc',
                        'name'        => 'pages',
                        'optional'    => true,
                        'description' => 'Pages to fetch: all | N | start-end',
                    ],
                    [
                        'type'        => 'assoc',
                        'name'        => 'per-page',
                        'optional'    => true,
                        'description' => 'Items per page: 5|10|25|100',
                    ],
                    [
                        'type'        => 'assoc',
                        'name'        => 'limit',
                        'optional'    => true,
                        'description' => 'Limit number of processed items (e.g. 1)',
                    ],
                    [
                        'type'        => 'flag',
                        'name'        => 'dry-run',
                        'optional'    => true,
                        'description' => 'Do not write to DB, just output what would be processed',
                    ],
                ],
            ]
        );
    }

    const BASE = 'https://bank.gov.ua';
    const LIST_URL  = '/ua/uah/numismatic-products/souvenier-coins';

    // Налаштування (під себе)
    protected $post_type = 'coins'; // CPT
    protected $acf_map = [
        'series'              => 'series',
        'denomination'        => 'denomination',
        'issue_date'          => 'issue_date',          // формат YYYY-MM-DD
        'material'            => 'material',
        'booklet_url'         => 'booklet_url',
        'description_html'    => 'description_html',    // повний HTML опису
        'designers'           => 'designers',
        'mintage_declared'    => 'mintage_declared',
        'mintage_actual'      => 'mintage_actual',
        'diameter_mm'         => 'diameter_mm',
        'quality'             => 'quality',
        'edge'                => 'edge',
        'images_gallery'      => 'images_gallery',      // ACF Gallery (array of attachment IDs)
    ];

    /**
     * Запуск: wp nbu parse-souvenir [--pages=<all|N|start-end>] [--perPage=<5|10|25|100>] [--dry-run]
     *
     * ## Приклади
     *   wp nbu parse-souvenir --pages=all
     *   wp nbu parse-souvenir --pages=1-3 --perPage=100
     *   wp nbu parse-souvenir --pages=1 --dry-run
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        $perPage = isset($assoc_args['per-page']) ? (int)$assoc_args['per-page'] : 100;
        if (!in_array($perPage, [5,10,25,100], true)) $perPage = 100;

        $dry_run = isset($assoc_args['dry-run']);

        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : null;
        if ($limit !== null && $limit < 1) {
            $limit = 1;
        }

        // 1) Дізнаємося total, щоб визначити кількість сторінок (якщо --pages=all)
        $first_html = $this->fetch_ajax_html(1, $perPage);
        if ( is_wp_error($first_html) ) {
            WP_CLI::error( 'Помилка запиту першої сторінки: ' . $first_html->get_error_message() );
        }
        $total = $this->extract_total_count($first_html);
        $total_pages = max(1, (int)ceil($total / $perPage));

        // 2) Обчислюємо які сторінки качати
        $pages_arg = isset($assoc_args['pages']) ? $assoc_args['pages'] : '1';
        $pages = $this->resolve_pages_arg($pages_arg, $total_pages);

        WP_CLI::log( sprintf('Знайдено ~%d результатів, сторінок: %d, качаємо: %s (perPage=%d)%s',
            $total, $total_pages, implode(',', $pages), $perPage, $dry_run ? ' [DRY RUN]' : ''
        ));

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $processed = 0;

        foreach ( $pages as $page ) {

            WP_CLI::log("Сторінка $page ...");
            $html = $page === 1 ? $first_html : $this->fetch_ajax_html($page, $perPage);
            if ( is_wp_error($html) ) {
                WP_CLI::warning("Пропускаю сторінку $page: " . $html->get_error_message());
                continue;
            }

            $items = $this->parse_items($html);
            WP_CLI::log("Знайдено елементів: " . count($items));

            foreach ( $items as $item ) {
                if ($limit !== null && $processed >= $limit) {
                    WP_CLI::log("Ліміт досягнуто ({$limit}). Зупиняюсь.");
                    break 2; // вихід з foreach items + foreach pages
                }

                $processed++;

                $title = $item['title'] ?? '';
                if (!$title) {
                    WP_CLI::warning("Елемент без заголовку — пропуск");
                    continue;
                }

                // Унікальний ключ — title + issue_date (якщо є)
                $unique_key = $title . '|' . ($item['issue_date'] ?? '');
                $post_id = $this->find_existing_post($title, $item['issue_date']);

                if ($dry_run) {
                    WP_CLI::log(" [DRY] {$unique_key}");
                    continue;
                }

                if ($post_id) {
                    WP_CLI::log(" Оновлюю пост #$post_id: $title");
                    $this->update_post_and_meta($post_id, $item);
                } else {
                    $post_id = $this->create_post($title, $item);
                    WP_CLI::log(" Створено пост #$post_id: $title");
                }

                // Картинки → завантажити і скласти в ACF галерею
                if (!empty($item['images'])) {
                    $attachment_ids = $this->download_and_attach_images($item['images'], $post_id);
                    $this->update_acf($post_id, $this->acf_map['images_gallery'], $attachment_ids);
                }

                // Трохи затримки, щоб не лупити сервер
                usleep(200000); // 0.2s
            }

            // невеликий тротл між сторінками
            sleep(1);
        }

        WP_CLI::success("Готово. Опрацьовано елементів: $processed");
    }

    protected function configure(): void
    {
        $this
            ->setName('nbu:fetch-coins')
            ->setDescription('Fetch souvenir coins from NBU')
            ->addOption(
                'limit',
                null,
                \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
                'Limit number of records',
                null
            );
    }

    /** ----------------------- HTTP ----------------------- */

    protected function fetch_ajax_html(int $page, int $perPage) {
        $url = add_query_arg([
            'page'    => $page,
            'perPage' => $perPage,
            'search'  => '',
            'from'    => '',
            'to'      => '',
            'code'    => '',
        ], self::BASE . self::LIST_URL );

        $resp = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'accept'     => 'text/html, */*;q=0.1',
                'user-agent' => 'WP-CLI Coin Parser/1.0',
                'referer'    => self::BASE . self::LIST_URL,
            ],
        ]);

        if ( is_wp_error($resp) ) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        if ( $code !== 200 ) {
            return new \WP_Error('http', 'HTTP ' . $code);
        }

        $body = wp_remote_retrieve_body($resp);
        if (!is_string($body) || $body === '') {
            return new \WP_Error('empty', 'Порожня відповідь');
        }
        return $body;
    }

    /** ----------------------- PARSE ----------------------- */

    protected function extract_total_count(string $html): int {
        // UА: "знайдено <b>1094 результати</b>"
        if (preg_match('~знайдено\s*<b>\s*([\d\s]+)~u', $html, $m)) {
            return (int)preg_replace('~\D+~', '', $m[1]);
        }
        return 0;
    }

    protected function parse_items(string $html): array {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xp = new \DOMXPath($dom);
        // Кожен результат «всередині» .row.cols.search-result → .col-md-10
        $nodes = $xp->query("//div[contains(@class,'row') and contains(@class,'cols') and contains(@class,'search-result')]//div[contains(@class,'col-md-10')]");

        $items = [];
        foreach ($nodes as $node) {
            $item = [
                'series'        => $this->xp_text($xp, ".//div[contains(@class,'tag')]", $node),
                'title'         => $this->xp_text($xp, ".//div[contains(@class,'title')]", $node),
                'denomination'  => $this->extract_mark($xp, $node, 'Номінал:'),
                'issue_date'    => $this->normalize_date_ua( $this->extract_mark($xp, $node, 'Дата введення в обіг:') ),
                'material'      => $this->extract_mark($xp, $node, 'Матеріал:'),
                'booklet_url'   => $this->abs_url( $this->xp_attr($xp, ".//div[contains(@class,'souvenir-coin__booklet')]//a", "href", $node) ),
                'description_html' => $this->collect_description_html($xp, $node),
                'designers'     => $this->extract_mark($xp, $node, 'Художник:'),
                'mintage_raw'   => $this->extract_mark($xp, $node, 'Тираж (оголошений/фактичний), шт.:'),
                'diameter_mm'   => $this->extract_mark($xp, $node, 'Діаметр, мм:'),
                'quality'       => $this->extract_mark($xp, $node, 'Категорія якості карбування:'),
                'edge'          => $this->extract_mark($xp, $node, 'Гурт:'),
                'nbu_category'  => $this->extract_mark_any($xp, $node, [
                    'Категорія НБУ:',
                    'Категорія:',
                    'Категорія монети:',
                ]),
                'images'        => $this->collect_images($xp, $node),
            ];

            // Розбивка тиражу
            if (!empty($item['mintage_raw'])) {
                if (preg_match('~(\d[\d\s]*)/(\d[\d\s]*)~u', $item['mintage_raw'], $mm)) {
                    $item['mintage_declared'] = (int)preg_replace('~\D+~','',$mm[1]);
                    $item['mintage_actual']   = (int)preg_replace('~\D+~','',$mm[2]);
                } else {
                    $item['mintage_declared'] = null;
                    $item['mintage_actual']   = null;
                }
            }

            // Числове поле діаметра
            if (!empty($item['diameter_mm'])) {
                $item['diameter_mm'] = (float)str_replace(',', '.', preg_replace('~[^\d,\.]+~', '', $item['diameter_mm']));
            }

            $items[] = $item;
        }
        return $items;
    }

    protected function collect_description_html(DOMXPath $xp, DOMNode $ctx): string {
        $paras = $xp->query(".//div[contains(@class,'details')]//div[contains(@class,'description')]//div[contains(@class,'description__text')]", $ctx);
        $html = '';
        foreach ($paras as $p) {
            $html .= $this->node_inner_html($p);
        }
        return trim($html);
    }

    protected function collect_images(DOMXPath $xp, DOMNode $ctx): array {
        $urls = [];
        // Основні великі картинки у href + видимі прев’юшки у img[src]
        foreach (['.//div[contains(@class,"img-container")]//a[contains(@class,"big-image")]', './/div[contains(@class,"img-container")]//img'] as $q) {
            $nodes = $xp->query($q, $ctx);
            foreach ($nodes as $n) {
                $href = $n->attributes->getNamedItem('href');
                $src  = $n->attributes->getNamedItem('src');
                $u = $href ? $href->nodeValue : ($src ? $src->nodeValue : null);
                if ($u) $urls[] = $this->abs_url($u);
            }
        }
        // Унікалізуємо і трішки фільтруємо
        $urls = array_values(array_unique(array_filter($urls)));
        return $urls;
    }

    protected function extract_mark(DOMXPath $xp, DOMNode $ctx, string $label): ?string {
        // Знаходимо span.mark з точним текстом і читаємо сусідній span.mark-text
        $node = $xp->query(".//span[contains(@class,'mark') and normalize-space(text())='{$label}']/following-sibling::span[contains(@class,'mark-text')][1]", $ctx)->item(0);
        return $node ? trim($node->textContent) : null;
    }

    protected function xp_text(DOMXPath $xp, string $q, ?DOMNode $ctx = null): ?string {
        $n = $xp->query($q, $ctx)->item(0);
        return $n ? trim($n->textContent) : null;
    }

    protected function xp_attr(DOMXPath $xp, string $q, string $attr, ?DOMNode $ctx = null): ?string {
        $n = $xp->query($q, $ctx)->item(0);
        if (!$n || !$n->attributes) return null;
        $a = $n->attributes->getNamedItem($attr);
        return $a ? trim($a->nodeValue) : null;
    }

    protected function node_inner_html(DOMNode $node): string {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    protected function abs_url(?string $u): ?string {
        if (!$u) return null;
        if (preg_match('~^https?://~i', $u)) return $u;
        // прибрати подвійні слеші
        return rtrim(self::BASE, '/') . '/' . ltrim($u, '/');
    }

    protected function normalize_date_ua(?string $d): ?string {
        // '22.08.2025' -> '2025-08-22'
        if (!$d) return null;
        if (preg_match('~(\d{2})\.(\d{2})\.(\d{4})~', $d, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return null;
        // (за потреби можна додати парсинг «січня 2025» тощо)
    }

    /** ----------------------- WP CRUD ----------------------- */

    protected function create_post(string $title, array $item): int {
        $postarr = [
            'post_type'   => $this->post_type,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> $item['description_html'] ?? '',
        ];
        $post_id = wp_insert_post($postarr);
        if (is_wp_error($post_id)) {
            WP_CLI::error('Не вдалось створити пост: ' . $post_id->get_error_message());
        }
        $this->fill_meta_acf($post_id, $item);
        return (int)$post_id;
    }

    protected function update_post_and_meta(int $post_id, array $item): void {
        wp_update_post([
            'ID'           => $post_id,
            'post_title'   => $item['title'] ?? get_the_title($post_id),
            'post_content' => $item['description_html'] ?? get_post_field('post_content', $post_id),
        ]);
        $this->fill_meta_acf($post_id, $item);
    }

    protected function fill_meta_acf(int $post_id, array $item): void {
        // ✅ 1) Facets → taxonomies
        $this->assign_taxonomy_single($post_id, 'coin_series',       $item['series'] ?? null);
        $this->assign_taxonomy_single($post_id, 'coin_denomination', $item['denomination'] ?? null);
        $this->assign_taxonomy_single($post_id, 'coin_material',     $item['material'] ?? null);
        $this->assign_taxonomy_single($post_id, 'coin_quality',      $item['quality'] ?? null);
        $this->assign_taxonomy_single($post_id, 'coin_edge',         $item['edge'] ?? null);
        $this->assign_taxonomy_single($post_id, 'coin_diameter',         $item['diameter'] ?? null);
        $this->assign_taxonomy_single($post_id, 'coin_mintage_declared', $item['mintage_declared'] ?? null);
        $this->assign_taxonomy_single($post_id, 'coin_mintage_actual',   $item['mintage_actual'] ?? null);

        // NBU category (ієрархія або просто строка)
        $this->assign_taxonomy_hierarchical($post_id, 'coin_nbu_category', $item['nbu_category'] ?? null);

        // ✅ 2) Designers -> separate post type
        $designer_names = $this->parse_designers($item['designers'] ?? null);
        $designer_ids   = $this->upsert_designer_posts($designer_names);

        update_post_meta($post_id, 'designers', $designer_ids);

        // ✅ 3) meta лишається тільки для "даних", а не фасетів
        update_post_meta($post_id, 'issue_date', $item['issue_date'] ?? '');
        update_post_meta($post_id, 'booklet_url', $item['booklet_url'] ?? '');

        if (isset($item['mintage_declared'])) update_post_meta($post_id, 'mintage_declared', $item['mintage_declared']);
        if (isset($item['mintage_actual']))   update_post_meta($post_id, 'mintage_actual', $item['mintage_actual']);
        if (isset($item['diameter_mm']))      update_post_meta($post_id, 'diameter_mm', $item['diameter_mm']);

        // (необов’язково) якщо хочеш лишити дубль для дебагу — можеш лишити, але для фільтрів вже не треба:
         update_post_meta($post_id, 'quality', $item['quality'] ?? '');
         update_post_meta($post_id, 'edge',    $item['edge'] ?? '');

        // ✅ 4) ACF (якщо є)
        if (function_exists('update_field')) {
            $this->update_acf($post_id, $this->acf_map['issue_date'], $item['issue_date'] ?? '');
            $this->update_acf($post_id, $this->acf_map['booklet_url'], $item['booklet_url'] ?? '');
            $this->update_acf($post_id, $this->acf_map['description_html'], $item['description_html'] ?? '');
            $this->update_acf($post_id, $this->acf_map['designers'], $designer_ids);

            if (isset($item['mintage_declared'])) $this->update_acf($post_id, $this->acf_map['mintage_declared'], $item['mintage_declared']);
            if (isset($item['mintage_actual']))   $this->update_acf($post_id, $this->acf_map['mintage_actual'],   $item['mintage_actual']);
            if (isset($item['diameter_mm']))      $this->update_acf($post_id, $this->acf_map['diameter_mm'],      $item['diameter_mm']);
        }
    }

    protected function update_acf(int $post_id, string $field_key_or_name, $value): void {
        if (!$field_key_or_name) return;
        try {
            update_field($field_key_or_name, $value, $post_id);
        } catch (\Throwable $e) {
            // якщо ACF не активний або поле не існує
            WP_CLI::warning("ACF update_field {$field_key_or_name}: " . $e->getMessage());
        }
    }

    protected function find_existing_post(string $title, ?string $issue_date): ?int {
        // простий пошук по title + (опційно) meta issue_date
        $args = [
            'post_type'      => $this->post_type,
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'title'          => $title,
            'fields'         => 'ids',
        ];
        if ($issue_date) {
            $args['meta_query'] = [[
                'key'   => 'issue_date',
                'value' => $issue_date,
            ]];
        }
        $q = new WP_Query($args);
        if (!empty($q->posts)) return (int)$q->posts[0];

        // fallback: шукати тільки по заголовку
        $q = new WP_Query([
            'post_type'      => $this->post_type,
            'posts_per_page' => 1,
            'post_status'    => 'any',
            's'              => $title,
            'fields'         => 'ids',
        ]);
        return !empty($q->posts) ? (int)$q->posts[0] : null;
    }

    protected function download_and_attach_images(array $urls, int $post_id): array {
        $ids = [];
        foreach ($urls as $u) {
            // media_sideload_image повертає HTML, але через помилку — attachment ID в $_id
            $tmp = download_url($u, 20);
            if (is_wp_error($tmp)) { WP_CLI::warning("  IMG skip: $u (" . $tmp->get_error_message() . ")"); continue; }

            $file_array = [
                'name'     => basename(parse_url($u, PHP_URL_PATH)),
                'tmp_name' => $tmp,
            ];

            $id = media_handle_sideload($file_array, $post_id, null);
            if (is_wp_error($id)) {
                @unlink($tmp);
                WP_CLI::warning("  IMG attach fail: $u (" . $id->get_error_message() . ")");
                continue;
            }
            $ids[] = (int)$id;
        }
        // унікалізація
        $ids = array_values(array_unique(array_filter($ids)));
        return $ids;
    }

    /** ----------------------- UTILS ----------------------- */

    protected function resolve_pages_arg(string $pages_arg, int $total_pages): array {
        $pages = [];
        if ($pages_arg === 'all') {
            $pages = range(1, $total_pages);
        } elseif (preg_match('~^(\d+)-(\d+)$~', $pages_arg, $m)) {
            $start = max(1, (int)$m[1]);
            $end   = min($total_pages, (int)$m[2]);
            if ($start > $end) [$start, $end] = [$end, $start];
            $pages = range($start, $end);
        } else {
            $p = max(1, (int)$pages_arg);
            $pages = [$p];
        }
        return $pages;
    }

    protected function extract_mark_any(DOMXPath $xp, DOMNode $ctx, array $labels): ?string
    {
        foreach ($labels as $label) {
            $val = $this->extract_mark($xp, $ctx, $label);
            if ($val) return $val;
        }
        return null;
    }

    protected function parse_designers(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') return [];

        // Найчастіше там один або кілька через кому/крапку з комою
        $parts = preg_split('~\s*[,;]\s*~u', $raw);
        $parts = array_values(array_filter(array_map('trim', $parts)));

        // прибрати дублікати
        return array_values(array_unique($parts));
    }

    protected function upsert_designer_posts(array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            if ($name === '') continue;

            $q = new \WP_Query([
                'post_type'      => 'designer',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                's'              => $name,
                'fields'         => 'ids',
            ]);

            $id = !empty($q->posts) ? (int) $q->posts[0] : 0;

            if (!$id) {
                $created = wp_insert_post([
                    'post_type'   => 'designer',
                    'post_status' => 'publish',
                    'post_title'  => $name,
                ]);

                if (is_wp_error($created)) {
                    WP_CLI::warning("Designer create failed '{$name}': " . $created->get_error_message());
                    continue;
                }
                $id = (int) $created;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    protected function ensure_term(string $taxonomy, string $name, int $parent = 0): int
    {
        $name = trim($name);
        if ($name === '') return 0;

        $exists = term_exists($name, $taxonomy, $parent);

        if (is_array($exists) && isset($exists['term_id'])) return (int) $exists['term_id'];
        if (is_int($exists)) return (int) $exists;

        $created = wp_insert_term($name, $taxonomy, ['parent' => $parent]);
        if (is_wp_error($created)) {
            WP_CLI::warning("Failed creating term '{$name}' in {$taxonomy}: " . $created->get_error_message());
            return 0;
        }
        return (int) $created['term_id'];
    }

    protected function assign_taxonomy_single(int $post_id, string $taxonomy, ?string $value): void
    {
        $value = trim((string)$value);
        if ($value === '') return;

        $term_id = $this->ensure_term($taxonomy, $value);
        if ($term_id) wp_set_post_terms($post_id, [$term_id], $taxonomy, false);
    }

    protected function assign_taxonomy_hierarchical(int $post_id, string $taxonomy, ?string $path): void
    {
        $path = trim((string)$path);
        if ($path === '') return;

        $path = str_replace(['→','>','|','\\'], '/', $path);
        $parts = preg_split('~\s*/\s*~u', $path);
        $parts = array_values(array_filter(array_map('trim', (array)$parts)));
        if (!$parts) return;

        $parent = 0;
        $last = 0;
        foreach ($parts as $p) {
            $id = $this->ensure_term($taxonomy, $p, $parent);
            if (!$id) break;
            $parent = $id;
            $last = $id;
        }

        if ($last) wp_set_post_terms($post_id, [$last], $taxonomy, false);
    }
}
