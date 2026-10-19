<?php

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Rode com wp eval-file.\n");
    exit(1);
}

function acervo_dump($entity) {
    fwrite(STDERR, get_class($entity) . PHP_EOL);
    fwrite(STDERR, print_r($entity->get_errors(), true));
    exit(1);
}

function acervo_save($entity, $repository) {
    if (!$entity->validate()) {
        acervo_dump($entity);
    }
    return $repository->insert($entity);
}

function acervo_find_collection() {
    $posts = get_posts([
        'post_type' => 'tainacan-collection',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'title' => 'Gravuras do Século XX',
    ]);
    return $posts ? (int) $posts[0]->ID : 0;
}

function acervo_meta($collection, $name, $type, $options, $dc_field) {
    $meta = new \Tainacan\Entities\Metadatum();
    $meta->set_name($name);
    $meta->set_status('publish');
    $meta->set_collection($collection);
    $meta->set_metadata_type($type);
    $meta->set_required('no');
    $meta->set_multiple('no');
    $meta->set_display('yes');
    if ($options) {
        $meta->set_metadata_type_options($options);
    }
    if ($dc_field) {
        $meta->set_exposer_mapping(['dublin-core' => $dc_field]);
    }
    return acervo_save($meta, \Tainacan\Repositories\Metadata::get_instance());
}

function acervo_filter($collection, $metadatum, $name, $type) {
    $filter = new \Tainacan\Entities\Filter();
    $filter->set_name($name);
    $filter->set_status('publish');
    $filter->set_collection($collection);
    $filter->set_metadatum($metadatum);
    $filter->set_filter_type($type);
    $filter->set_begin_with_filter_collapsed('no');
    return acervo_save($filter, \Tainacan\Repositories\Filters::get_instance());
}

function acervo_terms($name, $description, $term_names) {
    $tax = new \Tainacan\Entities\Taxonomy();
    $tax->set_name($name);
    $tax->set_description($description);
    $tax->set_status('publish');
    $tax->set_allow_insert('yes');
    $tax->set_hierarchical('no');
    $tax = acervo_save($tax, \Tainacan\Repositories\Taxonomies::get_instance());

    $ids = [];
    $terms = \Tainacan\Repositories\Terms::get_instance();
    foreach ($term_names as $term_name) {
        $term = new \Tainacan\Entities\Term();
        $term->set_name($term_name);
        $term->set_description('');
        $term->set_parent(0);
        $term->set_taxonomy($tax->get_db_identifier());
        $term = acervo_save($term, $terms);
        $ids[$term_name] = (int) $term->get_id();
    }
    return [$tax, $ids];
}

function acervo_item($collection, $title, $description, $values) {
    $item = new \Tainacan\Entities\Item();
    $item->set_title($title);
    $item->set_description($description);
    $item->set_status('publish');
    $item->set_collection($collection);
    $item = acervo_save($item, \Tainacan\Repositories\Items::get_instance());

    $rows = \Tainacan\Repositories\Item_Metadata::get_instance();
    foreach ($values as $pair) {
        $metadatum = $pair[0];
        $value = $pair[1];
        $row = new \Tainacan\Entities\Item_Metadata_Entity($item, $metadatum);
        $row->set_value($value);
        if (!$row->validate()) {
            fwrite(STDERR, "Item {$title}, metadado {$metadatum->get_name()}\n");
            acervo_dump($row);
        }
        $rows->insert($row);
    }
}

function acervo_map_core($collection) {
    $repo = \Tainacan\Repositories\Metadata::get_instance();
    $metadata = $repo->fetch_by_collection($collection, []);
    $map = [
        'Tainacan\Metadata_Types\Core_Title' => 'title',
        'Tainacan\Metadata_Types\Core_Description' => 'description',
    ];
    foreach ($metadata as $meta) {
        if ((string) $meta->get_collection_id() !== (string) $collection->get_id()) {
            continue;
        }
        $field = $map[$meta->get_metadata_type()] ?? null;
        if (!$field) {
            continue;
        }
        $meta->set_exposer_mapping(['dublin-core' => $field]);
        acervo_save($meta, $repo);
    }
}

function acervo_roteiro() {
    if (get_page_by_path('roteiro')) {
        return;
    }
    wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_name' => 'roteiro',
        'post_title' => 'Roteiro de prática',
        'post_content' => <<<'HTML'
<p>Acervo fictício para treinar catalogação no Tainacan. Artistas e obras não existem. O site sai do ar das 18h à meia-noite, horário de Brasília, porque a VM divide memória com outro serviço.</p>
<ol>
<li>Abra a coleção <strong>Gravuras do Século XX</strong> no site público e use os filtros de técnica, criador, data e estado de conservação.</li>
<li>No painel, edite um item e anexe uma imagem. O documento do item é a imagem da obra; miniatura é outra coisa.</li>
<li>Na taxonomia <strong>Técnica</strong>, crie um termo novo (por exemplo, "Água-tinta") e use esse termo num item.</li>
<li>Baixe a planilha <strong>Planilha de gravuras para importar</strong> na Biblioteca de mídia e importe os quatro itens na mesma coleção. O separador do arquivo é vírgula. Confira se a técnica entrou como termo, e não como texto solto.</li>
<li>Crie uma coleção vazia, do zero, com metadados Dublin Core: título, criador, data, tipo, formato, direitos. Mapeie cada um no expositor Dublin Core e ligue o filtro dos que a busca pública deve mostrar.</li>
</ol>
<p>Estado de conservação fica de fora do Dublin Core de propósito. É metadado de museu, não do padrão. Licença mapeia em <em>rights</em>, técnica em <em>type</em>, dimensões em <em>format</em>.</p>
HTML
    ]);
}

update_option('blogdescription', 'Laboratório de catalogação. Fora do ar das 18h à meia-noite.');
acervo_roteiro();

if (acervo_find_collection()) {
    fwrite(STDOUT, "Coleção já existe.\n");
    acervo_publish_sala();
    return;
}

$collections = \Tainacan\Repositories\Collections::get_instance();
$collection = new \Tainacan\Entities\Collection();
$collection->set_name('Gravuras do Século XX');
$collection->set_status('publish');
$collection->set_description('Conjunto didático de gravuras fictícias. Serve para praticar metadado, taxonomia, filtro facetado e anexo de imagem. Nenhuma obra ou artista desta coleção é real.');
$collection = acervo_save($collection, $collections);
acervo_map_core($collection);

list($tecnica, $tecnicas) = acervo_terms(
    'Técnica',
    'Vocabulário controlado das técnicas de gravura usadas neste acervo de prática.',
    ['Água-forte', 'Litografia', 'Xilogravura', 'Serigrafia', 'Ponta-seca']
);

$criador = acervo_meta($collection, 'Criador', 'Tainacan\Metadata_Types\Text', null, 'creator');
$data = acervo_meta($collection, 'Data', 'Tainacan\Metadata_Types\Date', null, 'date');
$tecnica_meta = acervo_meta(
    $collection,
    'Técnica',
    'Tainacan\Metadata_Types\Taxonomy',
    [
        'taxonomy_id' => $tecnica->get_id(),
        'allow_new_terms' => 'yes',
        'input_type' => 'tainacan-taxonomy-radio',
    ],
    'type'
);
$dimensoes = acervo_meta($collection, 'Dimensões', 'Tainacan\Metadata_Types\Text', null, 'format');
$estado = acervo_meta(
    $collection,
    'Estado de conservação',
    'Tainacan\Metadata_Types\Selectbox',
    [
        'options' => ['Estável', 'Atenção', 'Crítico'],
        'options_separator' => ',',
        'input_type' => 'tainacan-selectbox',
    ],
    null
);
$licenca = acervo_meta(
    $collection,
    'Licença',
    'Tainacan\Metadata_Types\Selectbox',
    [
        'options' => ['Domínio público', 'CC BY', 'CC BY-SA', 'Todos os direitos reservados'],
        'options_separator' => ',',
        'input_type' => 'tainacan-selectbox',
    ],
    'rights'
);

acervo_filter($collection, $criador, 'Criador', 'Tainacan\Filter_Types\Autocomplete');
acervo_filter($collection, $data, 'Data', 'Tainacan\Filter_Types\Date_Interval');
acervo_filter($collection, $tecnica_meta, 'Técnica', 'Tainacan\Filter_Types\TaxonomyCheckbox');
acervo_filter($collection, $estado, 'Estado de conservação', 'Tainacan\Filter_Types\Selectbox');
acervo_filter($collection, $licenca, 'Licença', 'Tainacan\Filter_Types\Selectbox');

$obras = [
    ['Rua do Ouvidor', 'Helena Vasques', '1928-06-12', 'Água-forte', '22 × 16 cm', 'Estável', 'Domínio público', 'Obra fictícia. Vista inventada da rua, traço fino em papel vergé.'],
    ['Mangue', 'Lúcio Paredes', '1934-03-03', 'Xilogravura', '34 × 26 cm', 'Estável', 'Domínio público', 'Obra fictícia. Casas sobre palafitas, contraste alto de preto.'],
    ['Varanda em Santa Teresa', 'Helena Vasques', '1951-11-20', 'Litografia', '30 × 22 cm', 'Atenção', 'CC BY', 'Obra fictícia. Grade de ferro e telhado. Margem com mancha de umidade.'],
    ['Operários na Lapa', 'Irene Caldas', '1962-08-01', 'Serigrafia', '50 × 35 cm', 'Estável', 'CC BY-SA', 'Obra fictícia. Três figuras e uma chaminé, tinta chapada.'],
    ['Estação da Luz', 'Lúcio Paredes', '1978-01-15', 'Ponta-seca', '18 × 13 cm', 'Crítico', 'Todos os direitos reservados', 'Obra fictícia. Papel quebradiço na borda inferior. Não manusear sem suporte.'],
    ['Azulejos do Brás', 'Irene Caldas', '1986-05-09', 'Serigrafia', '40 × 40 cm', 'Estável', 'CC BY', 'Obra fictícia. Padrão de azulejo repetido em quatro cores.'],
];

foreach ($obras as $obra) {
    acervo_item($collection, $obra[0], $obra[7], [
        [$criador, $obra[1]],
        [$data, $obra[2]],
        [$tecnica_meta, $tecnicas[$obra[3]]],
        [$dimensoes, $obra[4]],
        [$estado, $obra[5]],
        [$licenca, $obra[6]],
    ]);
}

fwrite(STDOUT, "Coleção Gravuras do Século XX criada.\n");
acervo_publish_sala();

function acervo_publish_sala() {
    $collection_id = acervo_find_collection();
    if (!$collection_id) {
        return;
    }

    $collection = new \Tainacan\Entities\Collection($collection_id);
    $collection->set_default_view_mode('masonry');
    $collection->set_enabled_view_modes(['masonry', 'cards', 'table']);
    if ($collection->validate()) {
        \Tainacan\Repositories\Collections::get_instance()->insert($collection);
    } else {
        fwrite(STDERR, "Não ajustei o modo de exibição.\n");
        fwrite(STDERR, print_r($collection->get_errors(), true));
    }

    acervo_attach_plates($collection);

    $page = get_page_by_path('sala');
    $content = '<p>Obras fictícias para treino de catalogação. Cada ficha abre o registro completo.</p>' . "\n" . '[acervo_sala]';
    if (!$page) {
        $page_id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_name' => 'sala',
            'post_title' => 'Sala de gravura',
            'post_content' => $content,
        ]);
    } else {
        $page_id = $page->ID;
        wp_update_post([
            'ID' => $page_id,
            'post_title' => 'Sala de gravura',
            'post_content' => $content,
        ]);
    }

    update_option('show_on_front', 'page');
    update_option('page_on_front', (int) $page_id);

    $menu = wp_get_nav_menu_object('Principal');
    $menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu('Principal');
    $existing = wp_get_nav_menu_items($menu_id) ?: [];
    foreach ($existing as $item) {
        wp_delete_post($item->ID, true);
    }

    $links = [
        'Sala' => home_url('/'),
        'Busca com filtros' => get_permalink($collection_id),
        'Roteiro' => home_url('/roteiro/'),
    ];
    foreach ($links as $title => $url) {
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => $title,
            'menu-item-url' => $url,
            'menu-item-status' => 'publish',
            'menu-item-type' => 'custom',
        ]);
    }
    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) {
        $locations = [];
    }
    $locations['navMenubelowHeader'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
    fwrite(STDOUT, "Sala publicada.\n");
}

function acervo_attach_plates($collection) {
    $posts = get_posts([
        'post_type' => $collection->get_db_identifier(),
        'post_status' => 'publish',
        'posts_per_page' => -1,
    ]);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $items = \Tainacan\Repositories\Items::get_instance();
    foreach ($posts as $post) {
        $item = new \Tainacan\Entities\Item($post);
        if ($item->get_document_type() === 'attachment' && $item->get_document()) {
            continue;
        }
        $path = '/seed/placas/' . $post->post_name . '.png';
        if (!is_file($path)) {
            fwrite(STDERR, "Sem placa para {$post->post_name}.\n");
            continue;
        }
        $tmp = wp_tempnam($post->post_name . '.png');
        copy($path, $tmp);
        $attachment_id = media_handle_sideload([
            'name' => $post->post_name . '.png',
            'tmp_name' => $tmp,
        ], $item->get_id());
        if (is_wp_error($attachment_id)) {
            fwrite(STDERR, $attachment_id->get_error_message() . "\n");
            continue;
        }
        $item->set_document((string) $attachment_id);
        $item->set_document_type('attachment');
        set_post_thumbnail($item->get_id(), $attachment_id);
        if (!$item->validate()) {
            fwrite(STDERR, "Placa não entrou em {$post->post_name}.\n");
            fwrite(STDERR, print_r($item->get_errors(), true));
            continue;
        }
        $items->insert($item);
    }
}
