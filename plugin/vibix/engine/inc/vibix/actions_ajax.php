<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 0);

include(ENGINE_DIR . '/data/vibix.config.php');
include(ENGINE_DIR . '/data/vibix.settings_config.php');
include(ENGINE_DIR . '/data/vibix.xfields_config.php');
include(ENGINE_DIR . '/data/vibix.category_config.php');
require_once(ENGINE_DIR . '/inc/vibix/functions.php');

$action = $_POST['action_ajax'] ?? $_GET['action_ajax'] ?? '';
$Res = ['ERROR' => 'Unknown action'];

if (empty($action)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'action_ajax not specified']);
    exit;
}

if ($action == "validate_token") {
    $token = $_POST['token'] ?? $_GET['token'] ?? '';

    if (empty($token)) {
        $Res = ['status' => 'error', 'message' => 'Токен не передан'];
    } else {
        if (!isset($vibix_config) || empty($vibix_config['api_server'])) {
            $Res = ['status' => 'error', 'message' => 'Не удалось загрузить конфиг Vibix'];
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $vibix_config['api_server'] . "/token/validate?token=" . $token);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                $Res = ['status' => 'error', 'message' => 'Ошибка соединения: ' . $curl_error];
            } else {
                $Res = json_decode($response, true);
                if (!is_array($Res)) {
                    $Res = ['status' => 'error', 'message' => 'Неверный ответ от сервера'];
                } else {
                    $Res['http_code'] = $http_code;
                }
            }
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

if ($action == "delete_all_tasks") {
    global $db;
    $db->query("TRUNCATE TABLE " . PREFIX . "_vibix_tasks");
    foreach (glob(ENGINE_DIR . '/cache/vibix_task_*.json') as $f) @unlink($f);
    foreach (glob(ENGINE_DIR . '/cache/vibix_task_*.json.tmp') as $f) @unlink($f);
    $Res = ['success' => true];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

if ($action == "redirect_statistics_getFilters") {
    $Res = getApi('/statistics/getFilters', []);
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action == "redirect_statistics_columnsTable") {
    $Res = getApi('/statistics/columnsTable', []);
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action == "redirect_statistics_data") {
    $Res = getApi('/statistics/data', $_POST);
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action == "redirect_requests_getFilters") {
    $Res = getApi('/video-request/getFilters', []);
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action == "redirect_requests_columnsTable") {
    $Res = getApi('/video-request/columnsTable', []);
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action == "redirect_requests_data") {
    $Res = getApi('/video-request/data', $_POST);
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action == "redirect_getFilters") {
    $Res = getApi('/publisher/catalog/getFilters', []);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action === 'DB_LINK_UPDATE') {
    global $db, $vibix_xfields_config, $member_id;

    $kp_field   = array_search('kp_id', $vibix_xfields_config);
    $imdb_field = array_search('imdb_id', $vibix_xfields_config);

    if ($kp_field === false)   $kp_field = '';
    if ($imdb_field === false) $imdb_field = '';

    if ($kp_field === '' && $imdb_field === '') {
        $Res = ['status' => 400, 'message' => 'В настройках не сопоставлено ни kp_id, ни imdb_id'];
        echo json_encode($Res);
        exit();
    }

    $where = [];
    if ($kp_field !== '')   $where[] = "xfields LIKE '%" . $db->safesql($kp_field) . "|%'";
    if ($imdb_field !== '') $where[] = "xfields LIKE '%" . $db->safesql($imdb_field) . "|%'";
    $where_sql = implode(' OR ', $where);

    $res = $db->query("SELECT xfields FROM " . PREFIX . "_post WHERE " . $where_sql);

    $kp_ids   = [];
    $imdb_ids = [];

    while ($row = $db->get_row($res)) {
        $xf = vibix_xfieldsdataload($row['xfields']);

        $has_kp = false;

        if ($kp_field !== '' && !empty($xf[$kp_field])) {
            $id = trim($xf[$kp_field]);
            if ($id !== '' && $id !== '0') {
                $kp_ids[$id] = true;
                $has_kp = true; 
            }
        }

        if (!$has_kp && $imdb_field !== '' && !empty($xf[$imdb_field])) {
            $id = trim($xf[$imdb_field]);
            if ($id !== '' && $id !== '0') {
                $imdb_ids[$id] = true;
            }
        }
    }
    $db->free($res);

    $total = count($kp_ids) + count($imdb_ids);

    if ($total === 0) {
        $Res = ['status' => 400, 'message' => 'В базе не найдено ни kp_id, ни imdb_id'];
        echo json_encode($Res);
        exit();
    }

    $filter_json = $db->safesql(json_encode([]));
    $user_id = (int)($member_id['user_id'] ?? 0);

    $db->query("INSERT INTO " . PREFIX . "_vibix_tasks
                (user_id, type, total, state, filter, date, processed, progress)
                VALUES ('{$user_id}', 'DB_LINK_UPDATE', '{$total}', 'PROCESS', '{$filter_json}', NOW(), 0, 0)");

    $task_id = $db->insert_id();

    // Очередь
    $all_ids = [];
    $seen = [];
    foreach (array_keys($kp_ids) as $id) {
        $key = 'kp:' . $id;
        if (!isset($seen[$key])) { $seen[$key] = true; $all_ids[] = ['type' => 'kp', 'value' => $id]; }
    }
    foreach (array_keys($imdb_ids) as $id) {
        $key = 'imdb:' . $id;
        if (!isset($seen[$key])) { $seen[$key] = true; $all_ids[] = ['type' => 'imdb', 'value' => $id]; }
    }

    if (!is_dir(ENGINE_DIR . '/cache/') || !is_writable(ENGINE_DIR . '/cache/')) {
        $db->query("DELETE FROM " . PREFIX . "_vibix_tasks WHERE id = '" . (int)$task_id . "'");
        $Res = ['status' => 500, 'message' => 'Каталог engine/cache/ недоступен для записи'];
        echo json_encode($Res);
        exit();
    }

    $cacheFile = ENGINE_DIR . '/cache/vibix_task_' . $task_id . '.json';
    $tmpFile   = $cacheFile . '.tmp';

    $payload = json_encode([
        'total'     => $total,
        'processed' => 0,
        'ids'       => $all_ids,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        $db->query("DELETE FROM " . PREFIX . "_vibix_tasks WHERE id = '" . (int)$task_id . "'");
        $Res = ['status' => 500, 'message' => 'Ошибка сериализации очереди: ' . json_last_error_msg()];
        echo json_encode($Res);
        exit();
    }

    if (file_put_contents($tmpFile, $payload) === false) {
        @unlink($tmpFile);
        $db->query("DELETE FROM " . PREFIX . "_vibix_tasks WHERE id = '" . (int)$task_id . "'");
        $Res = ['status' => 500, 'message' => 'Не удалось создать файл очереди задания (проверьте права на engine/cache/)'];
        echo json_encode($Res);
        exit();
    }

    if (!@rename($tmpFile, $cacheFile)) {
        @unlink($tmpFile);
        $db->query("DELETE FROM " . PREFIX . "_vibix_tasks WHERE id = '" . (int)$task_id . "'");
        $Res = ['status' => 500, 'message' => 'Не удалось сохранить файл очереди задания'];
        echo json_encode($Res);
        exit();
    }

    $Res = ['status' => 200, 'message' => 'Задание создано', 'task_id' => $task_id, 'total' => $total];
    echo json_encode($Res);
    exit();
}

if ($action == "redirect_data") {
    global $db;

    $Res = getApi('/publisher/catalog/data', $_POST);
    if (!($Res) || empty($Res['data'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Нет данных']);
        exit;
    }

    $file = 'dlenews';
    $news = $db->query("SELECT xfields FROM " . PREFIX . "_post");
    $posts['records'] = $news->num_rows;
    $posts['id'] = [];

    if (!file_exists(ENGINE_DIR . '/cache/system/' . $file . '.php')) {
        $current['records'] = 0;
        $current['id'] = [];
        set_vars($file, $current);
    }
    $current = get_vars($file);

    if ($current['records'] <> $posts['records']) {
        $xfield_vibix_to_dle = [];

        foreach ($vibix_xfields_config as $dle_field => $vibix_field) {
            $xfield_vibix_to_dle[$vibix_field] = $dle_field;
        }

        while ($post = $db->get_row($news)) {
            $xfieldsdata = vibix_xfieldsdataload($post['xfields']);
            if (!empty($xfield_vibix_to_dle['kp_id']) && !empty($xfieldsdata[$xfield_vibix_to_dle['kp_id']])) {
                $posts['id'][] = $xfieldsdata[$xfield_vibix_to_dle['kp_id']];
            }
        }

        set_vars($file, $posts);
        $current = $posts;
    }

    $counter = 0;
    foreach ($Res['data'] as $movie) {
        if (!empty($movie['kp_id']) && in_array($movie['kp_id'], $current['id'])) {
            $Res['data'][$counter]['exists'] = 1;
        } else {
            $Res['data'][$counter]['exists'] = 0;
        }
        $counter++;
    }
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action == "SAVE_FILTER") {
    $filters = $_REQUEST['filter'] ?? [];

    if (empty($filters)) {
        $Res = ['error' => 'Нет фильтров для сохранения'];
    } else {
        include(ENGINE_DIR . '/data/vibix.settings_config.php');
        $vibix_settings_config['saved_filters'] = $filters;

        $config_content = "<?php\n\$vibix_settings_config = " . var_export($vibix_settings_config, true) . ";\n?>";
        $config_content = preg_replace('/array\s+\(/', 'array(', $config_content);

        if (file_put_contents(ENGINE_DIR . '/data/vibix.settings_config.php', $config_content)) {
            $Res = ['ok' => true];
        } else {
            $Res = ['error' => 'Не удалось записать конфиг'];
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

if ($action == "delete_task") {
    $task_id = (int)$_REQUEST['id'];
    $db->query("DELETE FROM " . PREFIX . "_vibix_tasks WHERE id = $task_id");
    @unlink(ENGINE_DIR . '/cache/vibix_task_' . $task_id . '.json');
    @unlink(ENGINE_DIR . '/cache/vibix_task_' . $task_id . '.json.tmp');
    $Res = ['OK' => true];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

if (in_array($action, ["CREATE", "CREATE_UPDATE", "ONLY_LINK_UPDATE"])) {
    global $db, $member_id;

    $type = $action;
    $add_type = $_REQUEST['add_type'] ?? 'selected';

    if (!in_array($add_type, ['filter', 'selected'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Неверный тип добавления']);
        exit;
    }

    if ($add_type == 'filter') {
        if (!is_array($_REQUEST['filter'] ?? null)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 400,
                'message' => 'Фильтр не передан'
            ]);
            exit;
        }
        $filter = $_REQUEST['filter'];
    } else {
        if (empty($_REQUEST['vibix_basket']) || !is_array($_REQUEST['vibix_basket'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 400,
                'EMPTY'   => true,
                'message' => 'Ничего не выбрано. Отметьте записи в каталоге или выберите «Со всей выборкой по фильтру».'
            ]);
            exit;
        }
        $filter = ['id' => $_REQUEST['vibix_basket']];
    }

    $res = getApi('/publisher/catalog/data', [
        'draw'    => 1,
        'start'   => 0,
        'length'  => 150,
        'columns' => [['data' => '', 'name' => '', 'searchable' => true, 'orderable' => true]],
        'order'   => [['column' => 0, 'dir' => 'desc']],
        'filter'  => $filter
    ]);

    if (empty($res['recordsTotal'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Нет записей для обработки']);
        exit;
    }

    $filter_json = $db->safesql(json_encode($filter));
    $total = (int)$res['recordsTotal'];
    $user_id = $member_id['user_id'] ?? 0;

    $db->query("INSERT INTO " . PREFIX . "_vibix_tasks 
        (user_id, type, total, state, filter, date) 
        VALUES (
            '$user_id', 
            '$type', 
            '$total', 
            'PROCESS', 
            '$filter_json',
            NOW()
        )");

    $Res = ['OK' => true, 'task_id' => $db->insert_id()];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

if ($action == "searchMoves") {
    $kinopoisk_id = (int)($_REQUEST['kp_id'] ?? 0);
    $title = trim($_REQUEST['title'] ?? '');
    $Res = ['by_title' => [], 'by_kp_id' => []];

    if (strlen($title) > 3) {
        $res = getApi('/publisher/catalog/data', [
            'filter'  => [],
            'draw' => 1,
            'start' => 0,
            'length' => 30,
            'columns' => [['data' => '', 'name' => '', 'searchable' => true, 'orderable' => true]],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'search'  => ['value' => $title]
        ]);
        if (!empty($res['data'])) {
            $res['data'] = array_slice($res['data'], 0, 10);
            foreach ($res['data'] as $vibix_post) {
                $Res['by_title'][] = prepareDataDleForm($vibix_post);
            }
        }
    }

    if ($kinopoisk_id > 3) {
        $res = getApi('/publisher/catalog/data', [
            'draw'   => 1,
            'start' => 0,
            'length' => 30,
            'columns' => [['data' => '', 'name' => '', 'searchable' => true, 'orderable' => true]],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'search' => ['value' => (string)$kinopoisk_id]
        ]);

        if (!empty($res['data'])) {
            foreach ($res['data'] as $vibix_post) {
                $Res['by_kp_id'][] = prepareDataDleForm($vibix_post);
            }
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

if ($action == "getTasks") {
    global $db;

    $lib_enum = [
        'CREATE'           => 'Добавить если нет',
        'CREATE_UPDATE'    => 'Добавить и обновить если есть',
        'ONLY_LINK_UPDATE' => 'Проставить код плеера Vibix',
        'DB_LINK_UPDATE'   => 'Проставить код плеера по базе',
        'CANCEL'           => 'Отмена',
        'PROCESS'          => 'В работе',
        'PAUSE'            => 'На паузе',
        'COMPLETE'         => 'Завершено',
    ];

    $Res = ['list' => []];
    $rows = $db->super_query("
        SELECT TASKS.*, USERS.name as user_name 
        FROM " . PREFIX . "_vibix_tasks as TASKS 
        LEFT JOIN " . PREFIX . "_users USERS ON USERS.user_id = TASKS.user_id 
        ORDER BY TASKS.date DESC
    ", true);

    if (!empty($rows)) {
        foreach ($rows as &$row) {
            $row['state_label'] = $lib_enum[$row['state']] ?? $row['state'];
            $row['type_label'] = $lib_enum[$row['type']] ?? $row['type'];
            $row['filter'] = json_decode($row['filter'], true);
            $row['date'] = date("d.m.Y H:i", strtotime($row['date']));
        }
        $Res['list'] = $rows;
    }
    header('Content-Type: application/json');
    echo json_encode($Res);
    exit;
}

if ($action == "vibixBtnGetConfig") {
    $xfield_vibix_to_dle = [];
    foreach ($vibix_xfields_config as $dle_field => $vibix_field) {
        $xfield_vibix_to_dle[$vibix_field] = $dle_field;
    }

    $Res = [
        'config' => [
            'dle_kp_id'            => $xfield_vibix_to_dle['kp_id'] ?? '',
            'dle_vibix_embed_code' => $xfield_vibix_to_dle['embed_code_new'] ?? ''
        ]
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

if ($action == "redirect_profile_get") {
    $token = $vibix_settings_config['api_token'] ?? '';

    if (empty($token)) {
        $Res = ['status' => 'error', 'message' => 'API Token не настроен'];
    } else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $vibix_config['api_server'] . "/profile/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $Res = ['status' => 'error', 'message' => 'Ошибка соединения: ' . $curl_error];
        } else {
            $Res = json_decode($response, true);
            if (!is_array($Res)) {
                $Res = ['status' => 'error', 'message' => 'Неверный ответ от сервера'];
            } else {
                $Res['http_code'] = $http_code;
            }
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

if ($action == "profile_set") {
    $token = $vibix_settings_config['api_token'] ?? '';
    $user_data = $_POST['user'] ?? [];

    if (empty($token)) {
        $Res = ['status' => 'error', 'message' => 'API Token не настроен'];
    } else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $vibix_config['api_server'] . "/profile/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($user_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json;charset=utf-8'
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $Res = ['status' => 'error', 'message' => 'Ошибка соединения: ' . $curl_error];
        } else {
            $Res = json_decode($response, true);
            if (!is_array($Res)) {
                $Res = ['status' => 'error', 'message' => 'Неверный ответ от сервера'];
            } else {
                $Res['http_code'] = $http_code;
            }
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($Res);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($Res);
exit;

function prepareDataDleForm($vibix_post)
{
    global $config, $db, $vibix_xfields_config, $vibix_category_config;

    $xfieldsdata = [];
    $xfieldsdata = vibixToDle($vibix_xfields_config, $vibix_post);

    $category = [];
    foreach ($vibix_category_config as $dle_cat_id => $fields) {
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }
        foreach ($fields as $item) {
            $parts = explode('.', $item);
            if (count($parts) < 2) continue;
            list($field, $value) = $parts;
            if ($value == 'Фильм') $value = 'movie';
            if ($value == 'Сериал') $value = 'serial';
            if (!empty($vibix_post[$field]) && strpos($vibix_post[$field], $value) !== false) {
                $category[] = $dle_cat_id;
                break;
            }
        }
    }

    $title = tpl_field('title', $vibix_post, $vibix_post['name'] ?? '');
    return [
        'id'           => $vibix_post['id'] ?? '',
        'year'         => $vibix_post['year'] ?? '',
        'newdate'      => date("Y-m-d H:i:s"),
        'new_author'   => tpl_field('autor', $vibix_post, 'vibix'),
        'kinopoisk_id' => $vibix_post['kp_id'] ?? '',
        'title'        => $title,
        'category'     => array_unique($category),
        'alt_name'     => totranslit(stripslashes($title), true, false, $config['translit_url']),
        'descr'        => tpl_field('metadescription', $vibix_post, $vibix_post['name'] ?? ''),
        'tags'         => tpl_field('tags', $vibix_post, ''),
        'meta_title'   => tpl_field('metatitle', $vibix_post, $vibix_post['name'] ?? ''),
        'keywords'     => tpl_field('metakeywords', $vibix_post, ''),
        'short_story'  => tpl_field('short_story', $vibix_post, $vibix_post['description'] ?? ''),
        'full_story'   => tpl_field('full_story', $vibix_post, $vibix_post['description'] ?? ''),
        'xfields'      => $xfieldsdata,
        'allow_comm'   => '1',
        'approve'      => '1',
        'allow_main'   => '1',
        'fixed'        => '0',
        'allow_br'     => '1',
        'symbol'       => '',
    ];
}
?>