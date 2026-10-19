<?php

add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('RobotoFonts');
    wp_deregister_style('RobotoFonts');
}, 20);

add_shortcode('acervo_sala', 'acervo_render_sala');

function acervo_render_sala() {
    if (!class_exists('\Tainacan\Repositories\Collections')) {
        return '';
    }

    $collections = \Tainacan\Repositories\Collections::get_instance()->fetch([
        'posts_per_page' => 20,
        'post_status' => 'publish',
    ], 'OBJECT');

    if (!is_array($collections) || !$collections) {
        return '<p>Nenhuma coleção publicada.</p>';
    }

    ob_start();
    echo '<div class="acervo-sala">';
    foreach ($collections as $collection) {
        if (!is_object($collection) || !method_exists($collection, 'get_id')) {
            continue;
        }
        $post_type = $collection->get_db_identifier();
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 24,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $count = count($posts);
        $url = get_permalink($collection->get_id());
        echo '<section class="acervo-sala__collection">';
        echo '<header class="acervo-sala__header">';
        echo '<p class="acervo-sala__kicker">' . esc_html($count === 1 ? '1 obra em exposição' : $count . ' obras em exposição') . '</p>';
        echo '<p class="acervo-sala__lede">' . esc_html(wp_strip_all_tags($collection->get_description())) . '</p>';
        if ($url) {
            echo '<a class="acervo-sala__filters" href="' . esc_url($url) . '">Abrir a busca com filtros</a>';
        }
        echo '</header>';
        echo '<div class="acervo-sala__grid">';
        foreach ($posts as $post) {
            $item = new \Tainacan\Entities\Item($post);
            $link = get_permalink($post);
            $thumb = get_the_post_thumbnail_url($post, 'medium');
            if (!$thumb && $item->get_document_type() === 'attachment') {
                $thumb = wp_get_attachment_image_url((int) $item->get_document(), 'medium');
            }
            $meta = acervo_item_meta_map($item);
            $creator = $meta['Criador'] ?? '';
            $date = $meta['Data'] ?? '';
            $technique = $meta['Técnica'] ?? '';
            $year = preg_match('/^\d{4}/', $date, $m) ? $m[0] : $date;
            $line = implode(' · ', array_filter([$creator, $technique, $year]));
            echo '<a class="acervo-card" href="' . esc_url($link) . '">';
            if ($thumb) {
                echo '<img src="' . esc_url($thumb) . '" alt="" loading="lazy" width="480" height="640">';
            } else {
                echo '<span class="acervo-card__empty">sem imagem</span>';
            }
            echo '<span class="acervo-card__title">' . esc_html(get_the_title($post)) . '</span>';
            if ($line !== '') {
                echo '<span class="acervo-card__meta">' . esc_html($line) . '</span>';
            }
            echo '</a>';
        }
        echo '</div></section>';
    }
    echo '</div>';
    return ob_get_clean();
}

function acervo_item_meta_map($item) {
    $rows = \Tainacan\Repositories\Item_Metadata::get_instance()->fetch($item, 'OBJECT');
    $map = [];
    if (!is_array($rows)) {
        return $map;
    }
    foreach ($rows as $row) {
        $meta = $row->get_metadatum();
        if (!$meta) {
            continue;
        }
        $map[$meta->get_name()] = acervo_meta_text($row->get_value());
    }
    return $map;
}

function acervo_meta_text($value) {
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $part) {
            $text = acervo_meta_text($part);
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return implode(', ', $parts);
    }
    if (is_object($value) && method_exists($value, 'get_name')) {
        return (string) $value->get_name();
    }
    if (is_numeric($value)) {
        $term = get_term((int) $value);
        if ($term && !is_wp_error($term)) {
            return $term->name;
        }
    }
    return is_scalar($value) ? trim((string) $value) : '';
}

add_action('save_post', 'acervo_flush_page_cache');
add_action('deleted_post', 'acervo_flush_page_cache');
add_action('tainacan-insert', 'acervo_flush_page_cache');

function acervo_flush_page_cache() {
    $dir = WP_CONTENT_DIR . '/cache/acervo';
    foreach (glob($dir . '/*.html') ?: [] as $file) {
        unlink($file);
    }
}

add_action('wp_head', function () {
    echo '<style>
.acervo-sala__kicker{letter-spacing:.14em;text-transform:uppercase;font-size:12px;margin:0 0 8px}
.acervo-sala__lede{max-width:42rem;margin:0 0 12px}
.acervo-sala__filters{display:inline-block;margin-bottom:28px}
.acervo-sala__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:22px}
.acervo-card{display:flex;flex-direction:column;color:inherit;text-decoration:none}
.acervo-card img,.acervo-card__empty{width:100%;aspect-ratio:3/4;object-fit:cover;background:#efeae2;border:1px solid #d7d1c7}
.acervo-card__empty{display:flex;align-items:flex-end;padding:12px;font-size:13px}
.acervo-card__title{margin-top:10px;font-size:18px;line-height:1.3}
.acervo-card__meta{font-size:13px;opacity:.75}
</style>';
}, 30);
