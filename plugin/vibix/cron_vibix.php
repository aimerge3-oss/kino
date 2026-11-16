<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 0);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/engine/cache/cron_php_errors.log');

ini_set('display_errors', 0);
ini_set('log_errors', 0);


if (defined('CRON_VIBIX_RUNNING')) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['task_id' => 0, 'error' => 'Recursive call detected']);
    exit();
}
define('CRON_VIBIX_RUNNING', true);

define('DATALIFEENGINE', true);
define('ROOT_DIR', dirname(__FILE__));
define('ENGINE_DIR', ROOT_DIR . '/engine');

require_once ENGINE_DIR . '/data/config.php';

if ($config['version_id'] >= 13) {
    require_once(ENGINE_DIR . '/classes/plugins.class.php');
    require_once(DLEPlugins::Check(ENGINE_DIR . '/classes/mysql.php'));
    require_once(DLEPlugins::Check(ENGINE_DIR . '/data/dbconfig.php'));
    if (file_exists(ROOT_DIR . '/language/' . $config['langs'] . '/website.lng'))
        require_once ROOT_DIR . '/language/' . $config['langs'] . '/website.lng';
    require_once(DLEPlugins::Check(ENGINE_DIR . '/modules/functions.php'));
} else {
    require_once ENGINE_DIR . '/classes/mysql.php';
    require_once ENGINE_DIR . '/data/dbconfig.php';
    if (file_exists(ROOT_DIR . '/language/' . $config['langs'] . '/website.lng'))
        require_once ROOT_DIR . '/language/' . $config['langs'] . '/website.lng';
    require_once ENGINE_DIR . '/modules/functions.php';
}

require_once ENGINE_DIR . '/data/vibix.config.php';
require_once ENGINE_DIR . '/data/vibix.settings_config.php';
require_once ENGINE_DIR . '/data/vibix.xfields_config.php';
require_once ENGINE_DIR . '/data/vibix.category_config.php';

require_once ENGINE_DIR . '/inc/vibix/functions.php';

header('Content-Type: application/json; charset=UTF-8');

$cron_limit = isset($vibix_settings_config['cron_limit']) ? (int)$vibix_settings_config['cron_limit'] : 100;
$cron_usleep = isset($vibix_settings_config['cron_usleep']) ? (int)$vibix_settings_config['cron_usleep'] : 50000;
$progress_update_freq = isset($vibix_settings_config['progress_update_freq']) ? (int)$vibix_settings_config['progress_update_freq'] : 10;

$lockFile = ROOT_DIR . '/engine/cache/vibix_cron.lock';
$lockTimeout = 300; 

if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    if (time() - $lockTime > $lockTimeout) {
        @unlink($lockFile);
    } else {
        echo json_encode(['task_id' => 0, 'error' => 'Already running']);
        exit();
    }
}

file_put_contents($lockFile, time());

try {
    // авто-создание
    if (!empty($vibix_settings_config['auto_create_enabled'])) {

        $active_tasks = $db->super_query("SELECT COUNT(*) as cnt FROM " . PREFIX . "_vibix_tasks WHERE state IN ('PROCESS', 'PAUSE')");

        if ($active_tasks['cnt'] == 0) {

            $auto_create_time = $vibix_settings_config['auto_create_time'] ?? '03:00';
            $current_time = date('H:i');

            $target_timestamp = strtotime($auto_create_time);
            $current_timestamp = strtotime($current_time);
            $time_diff = abs($current_timestamp - $target_timestamp);

            $last_create_file = ROOT_DIR . '/engine/cache/vibix_last_auto_create.lock';
            $last_create_time = file_exists($last_create_file) ? filemtime($last_create_file) : 0;
            $today_created = (date('Y-m-d', $last_create_time) == date('Y-m-d'));

            if ($time_diff <= 300 && !$today_created) {

                $saved_filters = $vibix_settings_config['saved_filters'] ?? [];
                $auto_create_type = $vibix_settings_config['auto_create_type'] ?? 'CREATE_UPDATE';

                if (!empty($saved_filters)) {

                    $filter_json = $db->safesql(json_encode($saved_filters));

                    $res = getApi('/publisher/catalog/data', [
                        'draw'    => 1,
                        'start'   => 0,
                        'length'  => 50,
                        'columns' => [['data' => '', 'name' => '', 'searchable' => true, 'orderable' => true,]],
                        'order'   => [['column' => 0, 'dir' => 'desc']],
                        'filter'  => $saved_filters
                    ]);

                    if (!empty($res['recordsTotal'])) {
                        $total = (int)$res['recordsTotal'];
                        $db->query("INSERT INTO " . PREFIX . "_vibix_tasks 
                                    (user_id, type, total, state, filter, date, processed, progress) 
                                    VALUES (1, '{$auto_create_type}', '{$total}', 'PROCESS', '{$filter_json}', NOW(), 0, 0)");

                        file_put_contents($last_create_file, time());
                        echo json_encode(['task_id' => 0, 'auto_created' => true, 'total' => $total]);
                        @unlink($lockFile);
                        exit();
                    }
                }
            }
        }
    }

    // основное задание
    $task = $db->super_query("SELECT * FROM " . PREFIX . "_vibix_tasks WHERE total>0 and state = 'PROCESS' ORDER BY date ASC LIMIT 1");

    if (empty($task['id'])) {
        echo json_encode(['task_id' => 0]);
        @unlink($lockFile);
        exit();
    }

    if ($task['type'] === 'DB_LINK_UPDATE') {

        $limit = $cron_limit;
        $processed = (int)$task['processed'];
        $total = (int)$task['total'];

        $cacheFile = ENGINE_DIR . '/cache/vibix_task_' . $task['id'] . '.json';

        if (!file_exists($cacheFile)) {
            $db->query("UPDATE " . PREFIX . "_vibix_tasks SET state='COMPLETE', progress=100 WHERE id='{$task['id']}'");
            echo json_encode(['task_id' => $task['id'], 'progress' => 100, 'error' => 'Файл очереди не найден']);
            @unlink($lockFile);
            exit();
        }

        $cache = json_decode(file_get_contents($cacheFile), true);

        if (!is_array($cache) || !isset($cache['ids']) || !is_array($cache['ids'])) {
            $db->query("UPDATE " . PREFIX . "_vibix_tasks SET state='COMPLETE', progress=100 WHERE id='{$task['id']}'");
            @unlink($cacheFile);
            echo json_encode(['task_id' => $task['id'], 'progress' => 100, 'error' => 'Файл очереди повреждён']);
            @unlink($lockFile);
            exit();
        }

        $total = (int)($cache['total'] ?? $total);

        if (empty($cache['ids'])) {
            $db->query("UPDATE " . PREFIX . "_vibix_tasks SET state='COMPLETE', progress=100, processed='{$total}' WHERE id='{$task['id']}'");
            @unlink($cacheFile);
            echo json_encode(['task_id' => $task['id'], 'progress' => 100]);
            @unlink($lockFile);
            exit();
        }

        $api_max = 100;
        $batch_size = max(2, min($limit, $api_max));

        $batch     = array_slice($cache['ids'], 0, $batch_size);
        $remaining = array_slice($cache['ids'], $batch_size);

        $batch_kp   = [];
        $batch_imdb = [];
        foreach ($batch as $item) {
            if ($item['type'] === 'kp')   $batch_kp[]   = $item['value'];
            if ($item['type'] === 'imdb') $batch_imdb[] = $item['value'];
        }

        $embed_field = null;
        foreach ($vibix_xfields_config as $dle_field => $api_field) {
            if ($api_field === 'embed_code_new' || $api_field === 'embed_code') {
                $embed_field = $dle_field;
                break;
            }
        }
        if ($embed_field === null) {
            $db->query("UPDATE " . PREFIX . "_vibix_tasks SET state='COMPLETE', progress=100 WHERE id='{$task['id']}'");
            @unlink($cacheFile);
            echo json_encode(['task_id' => $task['id'], 'progress' => 100, 'error' => 'Не сопоставлено поле ссылки']);
            @unlink($lockFile);
            exit();
        }

        $rows_from_api = [];

        if (!empty($batch_kp)) {
            $r = getApiGet('/publisher/videos/links', [
                'kp_id'       => $batch_kp,
                'exist_kp_id' => 'true',
                'fields'      => 'embed_code,kp_id',
                'page'        => 1,
                'limit'       => $batch_size,
            ]);
            if ($r === null) {
                echo json_encode(['task_id' => $task['id'], 'progress' => $task['progress'], 'error' => 'ошибка API (kp)']);
                @unlink($lockFile);
                exit();
            }
            if (!empty($r['data']) && is_array($r['data'])) {
                foreach ($r['data'] as $row) {
                    $rows_from_api[] = $row;
                }
            }
            usleep($cron_usleep);
        }

        if (!empty($batch_imdb)) {
            $r = getApiGet('/publisher/videos/links', [
                'imdb_id' => $batch_imdb,
                'fields'  => 'embed_code,imdb_id',
                'page'    => 1,
                'limit'   => $batch_size,
            ]);
            if ($r === null) {
                echo json_encode(['task_id' => $task['id'], 'progress' => $task['progress'], 'error' => 'ошибка API (imdb)']);
                @unlink($lockFile);
                exit();
            }
            if (!empty($r['data']) && is_array($r['data'])) {
                foreach ($r['data'] as $row) {
                    $rows_from_api[] = $row;
                }
            }
            usleep($cron_usleep);
        }

        $posts_by_kp   = [];
        $posts_by_imdb = [];

        $all_kp_vals   = [];
        $all_imdb_vals = [];

        foreach ($rows_from_api as $row) {
            if (!empty($row['kp_id']))   $all_kp_vals[]   = trim($row['kp_id']);
            if (!empty($row['imdb_id'])) $all_imdb_vals[] = trim($row['imdb_id']);
        }
        $all_kp_vals   = array_values(array_unique($all_kp_vals));
        $all_imdb_vals = array_values(array_unique($all_imdb_vals));

        $kp_field   = array_search('kp_id', $vibix_xfields_config);
        $imdb_field = array_search('imdb_id', $vibix_xfields_config);
        if ($kp_field === false)   $kp_field = '';
        if ($imdb_field === false) $imdb_field = '';

        if ($kp_field !== '' && !empty($all_kp_vals)) {
            $kp_field_sql = $db->safesql($kp_field);
            $escaped = array_map(function ($id) use ($db) { return $db->safesql($id); }, $all_kp_vals);
            $pattern = implode('|', array_map(function ($id) { return preg_quote($id, '/'); }, $escaped));

            $q = "SELECT id, xfields FROM " . PREFIX . "_post
                WHERE xfields REGEXP '" . $kp_field_sql . "\\\\|(" . $pattern . ")(\\\\||$)'";
            $pr = $db->query($q);
            while ($p = $db->get_row($pr)) {
                $xf = vibix_xfieldsdataload($p['xfields']);
                if (!empty($xf[$kp_field])) {
                    $posts_by_kp[$xf[$kp_field]][] = $p;
                }
            }
            $db->free($pr);
        }

        if ($imdb_field !== '' && !empty($all_imdb_vals)) {
            $imdb_field_sql = $db->safesql($imdb_field);
            $escaped = array_map(function ($id) use ($db) { return $db->safesql($id); }, $all_imdb_vals);
            $pattern = implode('|', array_map(function ($id) { return preg_quote($id, '/'); }, $escaped));

            $q = "SELECT id, xfields FROM " . PREFIX . "_post
                WHERE xfields REGEXP '" . $imdb_field_sql . "\\\\|(" . $pattern . ")(\\\\||$)'";
            $pr = $db->query($q);
            while ($p = $db->get_row($pr)) {
                $xf = vibix_xfieldsdataload($p['xfields']);
                if (!empty($xf[$imdb_field])) {
                    $posts_by_imdb[$xf[$imdb_field]][] = $p;
                }
            }
            $db->free($pr);
        }

        foreach ($rows_from_api as $row) {
            $embed = trim((string)($row['embed_code'] ?? ''));
            if ($embed === '') continue;

            $targets = [];

            if (!empty($row['kp_id']) && !empty($posts_by_kp[trim($row['kp_id'])])) {
                $targets = array_merge($targets, $posts_by_kp[trim($row['kp_id'])]);
            }
            if (!empty($row['imdb_id']) && !empty($posts_by_imdb[trim($row['imdb_id'])])) {
                $targets = array_merge($targets, $posts_by_imdb[trim($row['imdb_id'])]);
            }

            $targets = array_values(array_unique($targets));

            foreach ($targets as $p) {
                if (empty($p['id'])) continue;

                $xf = vibix_xfieldsdataload($p['xfields']);

                if (isset($xf[$embed_field]) && $xf[$embed_field] === $embed) {
                    continue;
                }

                $xf[$embed_field] = $embed;

                $set = [];
                foreach ($xf as $k => $v) {
                    if ($k === '') continue;
                    $set[] = $k . '|' . str_replace('|', '&#124;', (string)$v);
                }
                $set = $db->safesql(implode('||', $set));

                $db->query("UPDATE " . PREFIX . "_post SET xfields='{$set}' WHERE id='" . (int)$p['id'] . "'");
            }
        }

        $newProcessed = $total - count($remaining);
        $newProgress  = ($total > 0) ? min(100, floor(($newProcessed * 100 / $total) * 10) / 10) : 100;

        if (empty($remaining)) {
            $db->query("UPDATE " . PREFIX . "_vibix_tasks
                        SET processed='{$total}', progress=100, state='COMPLETE'
                        WHERE id='{$task['id']}'");
            @unlink($cacheFile);
            echo json_encode(['task_id' => $task['id'], 'progress' => 100]);
            @unlink($lockFile);
            exit();
        }

        $newCache = [
            'total'     => $total,
            'processed' => $newProcessed,
            'ids'       => $remaining,
        ];

        $payload = json_encode($newCache, JSON_UNESCAPED_UNICODE);
        $tmpFile = $cacheFile . '.tmp';

        if ($payload === false || file_put_contents($tmpFile, $payload) === false || !@rename($tmpFile, $cacheFile)) {
            @unlink($tmpFile);
            echo json_encode(['task_id' => $task['id'], 'progress' => $task['progress'], 'error' => 'Не удалось сохранить очередь']);
            @unlink($lockFile);
            exit();
        }

        $db->query("UPDATE " . PREFIX . "_vibix_tasks
                    SET processed='{$newProcessed}', progress='{$newProgress}'
                    WHERE id='{$task['id']}'");

        echo json_encode(['task_id' => $task['id'], 'progress' => $newProgress]);
        @unlink($lockFile);
        exit();
    }


    $limit = $cron_limit;
    $processed = $task['processed'];
    $filter = json_decode($task['filter'], true);
    $res = getApi('/publisher/catalog/data', [
        'draw'    => 1,
        'start'   => $processed,
        'length'  => $limit,
        'columns' => [['data' => '', 'name' => '', 'searchable' => true, 'orderable' => true,]],
        'order'   => [['column' => 0, 'dir' => 'desc']],
        'filter'  => $filter
    ]);

    if ($res === null) {
        echo json_encode([
            'task_id'  => $task['id'],
            'progress' => $task['progress'],
            'error'    => 'ошибка API'
        ]);
        @unlink($lockFile);
        exit();
    }

    if (!empty($res['recordsTotal'])) {

        if (empty($res['data']) || !is_array($res['data'])) {
            echo json_encode([
                'task_id'  => $task['id'],
                'progress' => $task['progress'],
                'error'    => 'нет данных'
            ]);
            @unlink($lockFile);
            exit();
        }

        $xfield_vibix_to_dle = [];
        foreach ($vibix_xfields_config as $dle_field => $vibix_field) {
            $xfield_vibix_to_dle[$vibix_field] = $dle_field;
        }

        $post_by_kpid = [];
        $kp_ids_to_check = [];

        foreach ($res['data'] as $vibix_catalog_row) {
            if (!empty($vibix_catalog_row['kp_id'])) {
                $kp_id = trim($vibix_catalog_row['kp_id']);
                if (!empty($kp_id) && $kp_id !== '0') {
                    $kp_ids_to_check[] = $kp_id;
                }
            }
        }

        if (!empty($kp_ids_to_check)) {
            $kp_ids_to_check = array_unique($kp_ids_to_check);
            
            $kp_field_name = $db->safesql($xfield_vibix_to_dle['kp_id'] ?? 'kp_id');

            $escaped_ids = array_map(function($id) use ($db) {
                return $db->safesql($id);
            }, $kp_ids_to_check);

            $ids_pattern = implode('|', array_map(function($id) {
                return preg_quote($id, '/');
            }, $escaped_ids));
            
            $query = "SELECT id, xfields FROM " . PREFIX . "_post 
                    WHERE xfields REGEXP '" . $kp_field_name . "\\\\|(" . $ids_pattern . ")(\\\\||$)'";

            $posts_result = $db->query($query);

            while ($post = $db->get_row($posts_result)) {
                $xfieldsdata = vibix_xfieldsdataload($post['xfields']);
                if (!empty($xfieldsdata[$xfield_vibix_to_dle['kp_id']])) {
                    $post_by_kpid[$xfieldsdata[$xfield_vibix_to_dle['kp_id']]] = $post;
                }
            }
            $db->free($posts_result);
        }

        $processedCount = 0;

        foreach ($res['data'] as $vibix_catalog_row) {
            if (empty($vibix_catalog_row['kp_id'])) {
                $processedCount++;
                continue;
            }

            switch ($task['type']) {
                case 'ONLY_LINK_UPDATE':
                    if (!empty($post_by_kpid[$vibix_catalog_row['kp_id']])) {
                        updatePost($vibix_catalog_row, $post_by_kpid[$vibix_catalog_row['kp_id']], ['embed_code_new'], false);
                    }
                    break;

                case 'CREATE':
                    if (empty($post_by_kpid[$vibix_catalog_row['kp_id']])) {
                        addPost($vibix_catalog_row);
                    }
                    break;

                case 'CREATE_UPDATE':
                    if (empty($post_by_kpid[$vibix_catalog_row['kp_id']])) {
                        addPost($vibix_catalog_row);
                    } else {
                        updatePost($vibix_catalog_row, $post_by_kpid[$vibix_catalog_row['kp_id']], [], false);
                    }
                    break;
            }

            $processedCount++;

            usleep($cron_usleep);

            if ($processedCount % $progress_update_freq == 0) {
                $currentProcessed = $processed + $processedCount;
                $currentProgress = round($currentProcessed * 100 / $task['total']);
                $db->query("UPDATE " . PREFIX . "_vibix_tasks 
                            SET processed = '{$currentProcessed}', progress='{$currentProgress}' 
                            WHERE id = '{$task['id']}'");
            }
        }

        $finalProcessed = $processed + $processedCount;
        $finalProgress = ($finalProcessed >= $task['total']) ? 100 : round($finalProcessed * 100 / $task['total'], 1);
        $complete = ($finalProcessed >= $task['total']) ? ", state='COMPLETE'" : "";

        $db->query("UPDATE " . PREFIX . "_vibix_tasks 
                    SET processed = '{$finalProcessed}', progress='{$finalProgress}' {$complete} 
                    WHERE id = '{$task['id']}'");

        echo json_encode(['task_id' => $task['id'], 'progress' => $finalProgress]);
    } else {
        $db->query("UPDATE " . PREFIX . "_vibix_tasks 
                    SET state='COMPLETE', progress=100 
                    WHERE id = '{$task['id']}'");

        echo json_encode(['task_id' => $task['id'], 'progress' => 100]);
    }
} catch (Exception $e) {
    echo json_encode(['task_id' => 0, 'error' => $e->getMessage()]);
}

@unlink($lockFile);
exit();
?>