<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 0);


if (defined('VIBIX_FUNCTIONS_LOADED')) {
    return;
}
define('VIBIX_FUNCTIONS_LOADED', true);

if (!defined('DLE_VERSION')) {
    $dle_version = (float)($config['version_id'] ?? 0);
    define('DLE_VERSION', $dle_version);
}

if (!function_exists('vibix_ajax_url')) {
    function vibix_ajax_url($action = '') {
        global $config;
        if (DLE_VERSION >= 19) {
            return $config['http_home_url'] . 'index.php?controller=ajax&mod=vibix&action_ajax=' . $action;
        } else {
            return $config['admin_path'] . '?mod=vibix&action_ajax=' . $action;
        }
    }
}

if (!function_exists('vibix_xfieldsload')) {
    function vibix_xfieldsload() {
        if (DLE_VERSION >= 19) {
            DLEXFields::Init();
            $fields = DLEXFields::$fields['fields'] ?? [];
            $result = [];
            foreach ($fields as $field_id => $field_data) {
                $result[$field_id] = $field_data;
            }
            return $result;
        } else {
            $fields = xfieldsload();
            $result = [];
            foreach ($fields as $field) {
                if (isset($field[0])) {
                    $result[$field[0]] = $field;
                }
            }
            return $result;
        }
    }
}

if (!function_exists('vibix_xfieldsdataload')) {
    function vibix_xfieldsdataload($xfields) {
        if (DLE_VERSION >= 19) {
            return DLEXFields::xfieldsdataload($xfields);
        } else {
            return xfieldsdataload($xfields);
        }
    }
}

if (!isset($vibix_settings_config)) {
    include ENGINE_DIR . '/data/vibix.settings_config.php';
}

function getApiGet($url, $params = [])
{
    global $vibix_settings_config, $vibix_config;

    $full = $vibix_config['api_server'] . $url;
    if (!empty($params)) {
        $full .= (strpos($full, '?') === false ? '?' : '&') . http_build_query($params);
    }

    $ch = curl_init($full);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $vibix_settings_config['api_token']]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    $result = curl_exec($ch);
    $errno  = curl_errno($ch);
    $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $result === false || $http >= 400 || $http === 0) {
        return null;
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : null;
}

function getApi($url, $data)
{
    global $vibix_settings_config, $vibix_config;

    $ch = curl_init($vibix_config['api_server'] . $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $vibix_settings_config['api_token']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    $result = curl_exec($ch);
    $errno  = curl_errno($ch);
    $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $result === false || $http >= 400 || $http === 0) {
        return null;
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : null;
    }

function tpl_field($field, $vibix_post, $default_value)
{
    global $vibix_settings_config;
    $collection = '';
    if (empty($vibix_settings_config['tpl_' . $field]))
        return $default_value;
    $str = $vibix_settings_config['tpl_' . $field];
    foreach ($vibix_post as $field => $value) {
        if (is_array($value)) {
            foreach ($value as $sub_field => $sub_value)
                if ($field == 'persons') {
                    foreach ($sub_value as $person) {
                        $collection .= $person['name_anyway'] . ", ";
                    }
                    $str = str_replace("{{$field}.{$sub_field}}", $collection, $str);
                    $collection = '';
                }
        } else {
            if (!empty($value))
                $str = str_replace("{{$field}}", $value, $str);
        }
    }
    $str = preg_replace('/\{[\s\S]+?\}/', '', $str);
    return $str;
}

function addPost($vibix_post)
{
    global $config, $db, $vibix_xfields_config, $vibix_category_config, $vibix_settings_config;


    $xfieldsdata = [];
    $xfieldsdata = vibixToDle($vibix_xfields_config, $vibix_post);
    vibixDownloadPoster($xfieldsdata, $vibix_xfields_config, $vibix_post, $vibix_settings_config);

    $set_xfields = [];

    foreach ($xfieldsdata as $key => $value)
        if ($value)
            $set_xfields[] = $key . "|" . str_replace('|', '&#124;', $value);
    $set_xfields = $db->safesql(implode('||', $set_xfields));


    $category = [];
    foreach ($vibix_category_config as $dle_cat_id => $item) {
        $conditions = explode(',', $item);
        $allConditionsMet = true;

        foreach ($conditions as $condition) {
            list($field, $expected_value) = explode('.', $condition);
            $field = trim($field);
            $expected_value = trim($expected_value);

            if ($expected_value == 'Фильм') $expected_value = 'movie';
            if ($expected_value == 'Сериал') $expected_value = 'serial';

            if (!empty($vibix_post[$field])) {
                $actual_value = $vibix_post[$field];

                if (is_array($actual_value)) {
                    $found = false;
                    foreach ($actual_value as $val) {
                        if (stripos($val, $expected_value) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $allConditionsMet = false;
                        break;
                    }
                }
                else {
                    if (stripos($actual_value, $expected_value) === false) {
                        $allConditionsMet = false;
                        break;
                    }
                }
            } else {
                $allConditionsMet = false;
                break;
            }
        }

        if ($allConditionsMet) {
            $category[] = $dle_cat_id;
        }
    }


    $title = tpl_field('title', $vibix_post, $vibix_post['name']);
    $dle_post = [
        'date'        => date("Y-m-d H:i:s"),
        'autor'       => $db->safesql(tpl_field('autor', $vibix_post, 'vibix')),
        'title'       => $db->safesql($title),
        'category'    => implode(',', $category),
        'alt_name'    => totranslit(stripslashes($title), true, false, $config['translit_url']),
        'descr'       => $db->safesql(tpl_field('metadescription', $vibix_post, $vibix_post['name'])),
        'tags'        => $db->safesql(tpl_field('tags', $vibix_post, '')),
        'metatitle'   => $db->safesql(tpl_field('metatitle', $vibix_post, $vibix_post['name'])),
        'keywords'    => $db->safesql(tpl_field('metakeywords', $vibix_post, '')),
        'short_story' => $db->safesql(tpl_field('short_story', $vibix_post, $vibix_post['description'])),
        'full_story'  => $db->safesql(tpl_field('full_story', $vibix_post, $vibix_post['description'])),
        'xfields'     => $set_xfields,
        'allow_comm'  => '1',
        'approve'     => '1',
        'allow_main'  => '1',
        'fixed'       => '0',
        'allow_br'    => '1',
        'symbol'      => '',
    ];


    $db->query("INSERT INTO " . PREFIX . "_post (" . implode(', ', array_keys($dle_post)) . ") VALUES ('" . implode("', '", array_values($dle_post)) . "');");
    $id = $db->insert_id();

    $json_ld = generate_json_ld($vibix_post, $id);
    $og_microdata = generate_og_microdata($vibix_post, $id);
    if ($json_ld || $og_microdata) {
        if ($json_ld) {
            $xfieldsdata['vibix_schema_microdata'] = $db->safesql($json_ld);
        }

        if ($og_microdata) {
            $xfieldsdata['vibix_og_microdata'] = $db->safesql($og_microdata);
        }

        $set_xfields = [];
        foreach ($xfieldsdata as $key => $value) {
            $set_xfields[] = $key . "|" . str_replace('|', '&#124;', $value);
        }
        $set_xfields = $db->safesql(implode('||', $set_xfields));
        $db->query("UPDATE " . PREFIX . "_post SET xfields = '{$set_xfields}' WHERE id = '{$id}'");
    }

    if (!empty($dle_post['tags'])) {
        $tags = [];
        $dle_post['tags'] = explode(",", $dle_post['tags']);
        foreach ($dle_post['tags'] as $value)
            $tags[] = "('" . $id . "', '" . trim($value) . "')";
        $db->query("INSERT INTO " . PREFIX . "_tags (news_id, tag) VALUES " . implode(", ", $tags));
    }

    if (!empty($category)) {
        $cat_ids = array();
        foreach ($category as $value)
            $cat_ids[] = "('" . $id . "', '" . trim($value) . "')";
        $cat_ids = implode(", ", $cat_ids);
        $db->query("INSERT INTO " . PREFIX . "_post_extras_cats (news_id, cat_id) VALUES {$cat_ids}");
    }

    $def_fields_extras = [
        'news_id'         => $id,
        'allow_rate'      => '1',
        'votes'           => '0',
        'disable_index'   => '0',
        'related_ids'     => '',
        'access'          => '',
        'user_id'         => '1',
        'disable_search'  => '0',
        'need_pass'       => '0',
        'allow_rss'       => '1',
        'allow_rss_dzen'  => '1',
    ];
    $db->query("INSERT INTO " . PREFIX . "_post_extras (" . implode(', ', array_keys($def_fields_extras)) . ")  VALUES ('" . implode("', '", array_values($def_fields_extras)) . "');");
}

function updatePost($vibix_post, $dle_post, $fields = [], $update_cats = false)
{
    global $db, $vibix_xfields_config, $vibix_category_config, $vibix_settings_config;

    $xfieldsdata = vibix_xfieldsdataload($dle_post['xfields']);

    if (empty($fields) || !in_array('embed_code_new', $fields)) {
        vibixDownloadPoster($xfieldsdata, $vibix_xfields_config, $vibix_post, $vibix_settings_config);
    }
    unset($xfieldsdata['']);

    foreach ($vibix_xfields_config as $xfield_name => $vibix_field) {

        $vibix_sub_field = null;
        if (strpos($vibix_field, 'persons.') !== false) {
            $subfields = explode('.', $vibix_field);
            $vibix_field = $subfields[0];
            $vibix_sub_field = $subfields[1];
        }

        if ((!empty($vibix_field) && !empty($vibix_post[$vibix_field])) &&
            ((empty($fields)) || (!empty($fields) && in_array($vibix_field, $fields)))
        ) {
            if (is_array($vibix_post[$vibix_field]) && !empty($vibix_post[$vibix_field][$vibix_sub_field])) {
                $persons = [];
                foreach ($vibix_post[$vibix_field][$vibix_sub_field] as $item) {
                    if (!empty($item['name_anyway'])) {
                        $persons[] = $item['name_anyway'];
                    }
                }
                $xfieldsdata[$xfield_name] = implode(', ', $persons);
            } elseif ($vibix_field === 'voiceovers') {
                $voiceovers = [];
                foreach ($vibix_post[$vibix_field] as $item) {
                    if (!empty($item['name'])) {
                        $voiceovers[] = $item['name'];
                    }
                }
                $xfieldsdata[$xfield_name] = implode(', ', $voiceovers);
            } elseif (!is_array($vibix_post[$vibix_field]) && $vibix_field !== 'preview') {

                $xfieldsdata[$xfield_name] = $vibix_post[$vibix_field];

            }
        }
    }

    $json_ld = generate_json_ld($vibix_post, $dle_post['id']);
    $og_microdata = generate_og_microdata($vibix_post, $dle_post['id']);

    if ($json_ld) {
        $xfieldsdata['vibix_schema_microdata'] = $db->safesql($json_ld);
    }

    if ($og_microdata) {
        $xfieldsdata['vibix_og_microdata'] = $db->safesql($og_microdata);
    }

    $str_sql = '';

    $set_xfields = [];
    foreach ($xfieldsdata as $key => $value)
        $set_xfields[] = $key . "|" . str_replace('|', '&#124;', $value);

    if (empty($fields)) {
        $category = [];
        foreach ($vibix_category_config as $dle_cat_id => $item) {
            $conditions = explode(',', $item);
            $allConditionsMet = true;

            foreach ($conditions as $condition) {
                list($field, $expected_value) = explode('.', $condition);
                $field = trim($field);
                $expected_value = trim($expected_value);

                if ($expected_value == 'Фильм') $expected_value = 'movie';
                if ($expected_value == 'Сериал') $expected_value = 'serial';

                if (!empty($vibix_post[$field])) {
                    $actual_value = $vibix_post[$field];

                    if (is_array($actual_value)) {
                        $found = false;
                        foreach ($actual_value as $val) {
                            if (stripos($val, $expected_value) !== false) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $allConditionsMet = false;
                            break;
                        }
                    }
                    else {
                        if (stripos($actual_value, $expected_value) === false) {
                            $allConditionsMet = false;
                            break;
                        }
                    }
                } else {
                    $allConditionsMet = false;
                    break;
                }
            }

            if ($allConditionsMet) {
                $category[] = $dle_cat_id;
            }
        }
        if (isset($dle_post['id']) && !empty($dle_post['id'])) {
            $post_data = $db->super_query("SELECT date FROM " . PREFIX . "_post WHERE id = '{$dle_post['id']}'");
            $updated_at = $post_data['date'] ?? date('Y-m-d H:i:s');
        } else {
            $updated_at = isset($dle_post['date']) ? $dle_post['date'] : date('Y-m-d H:i:s');
        }

        if (!empty($vibix_post['uploaded_at'])) {
            $uploaded_at = DateTime::createFromFormat('d.m.Y H:i', $vibix_post['uploaded_at']);

            if ($uploaded_at !== false) {
                $current_date = DateTime::createFromFormat('Y-m-d H:i:s', $updated_at);

                if ($uploaded_at > $current_date) {
                    $updated_at = $uploaded_at->format('Y-m-d H:i:s');
                }
            }
        }

        $dle_post = [
            'id'          => $dle_post['id'],
            'date'        => $updated_at,
            'category'    => implode(',', $category),
            'autor'       => tpl_field('autor', $vibix_post, 'vibix'),
            'title'       => tpl_field('title', $vibix_post, $vibix_post['name']),
            'descr'       => tpl_field('metadescription', $vibix_post, $vibix_post['name']),
            'tags'        => tpl_field('tags', $vibix_post, ''),
            'metatitle'   => tpl_field('metatitle', $vibix_post, $vibix_post['name']),
            'keywords'    => tpl_field('metakeywords', $vibix_post, ''),
            'short_story' => tpl_field('short_story', $vibix_post, $vibix_post['description']),
            'full_story'  => tpl_field('full_story', $vibix_post, $vibix_post['description']),
        ];

        foreach ($dle_post as $field => $value) {
            $value = $db->safesql($value);
            $str_sql .= ", `{$field}` = '{$value}'";
        }
    }

    $set_xfields = $db->safesql(implode('||', $set_xfields));

    $db->query("UPDATE " . PREFIX . "_post SET  xfields='{$set_xfields}' {$str_sql}  WHERE id='{$dle_post['id']}';");

    if (!empty($category)) {
        $db->query("DELETE FROM  " . PREFIX . "_post_extras_cats WHERE news_id = '{$dle_post['id']}';");
        $cat_ids = array();
        foreach ($category as $value)
            $cat_ids[] = "('" . $dle_post['id'] . "', '" . trim($value) . "')";
        $cat_ids = implode(", ", $cat_ids);
        $db->query("INSERT INTO " . PREFIX . "_post_extras_cats (news_id, cat_id) VALUES {$cat_ids}");
    }
}

function vibixToDle($vibix_xfields_config, $vibix_post)
{

    foreach ($vibix_xfields_config as $xfield_name => $vibix_field) {
        $api_field_to_use = $vibix_field;
        if ($vibix_field == 'embed_code_new') {
            $api_field_to_use = 'embed_code_new';
        }

        if (gettype(strpos($api_field_to_use, 'persons.')) <> 'boolean') {
            $subfields = explode('.', $api_field_to_use);
            $api_field_to_use = $subfields[0];
            $vibix_sub_field = $subfields[1];
        }

        if ((!empty($api_field_to_use) and !empty($vibix_post[$api_field_to_use]))) {
            if (is_array($vibix_post[$api_field_to_use]) and !empty($vibix_post[$api_field_to_use][$vibix_sub_field])) {
                foreach ($vibix_post[$api_field_to_use][$vibix_sub_field] as $item) {
                    if (!empty($item['name_anyway']))
                        $persons[] = $item['name_anyway'];
                }
                $persons = implode(', ', $persons);
                $xfieldsdata[$xfield_name] = $persons;
                unset($persons);
            }
            if ($api_field_to_use == 'voiceovers') {
                foreach ($vibix_post[$api_field_to_use] as $item) {
                    if (!empty($item['name']))
                        $voiceovers[] = $item['name'];
                }
                $voiceovers = implode(', ', $voiceovers);
                $xfieldsdata[$xfield_name] = $voiceovers;
                unset($voiceovers);
            }
            if (!is_array($vibix_post[$api_field_to_use])) {
                $xfieldsdata[$xfield_name] = $vibix_post[$api_field_to_use];
            }
        }
    }

    return $xfieldsdata;
}

function vibixDownloadPoster(&$xfieldsdata, $vibix_xfields_config, $vibix_post, $vibix_settings_config)
{
    $poster_field_name = null;
    foreach ($vibix_xfields_config as $field => $api_key) {
        if ($api_key === 'preview') {
            $poster_field_name = $field;
            break;
        }
    }

    $poster_url = trim((string)($vibix_post['preview'] ?? ''));
    if (!$poster_field_name || empty($poster_url) || !filter_var($poster_url, FILTER_VALIDATE_URL)) {
        return;
    }

    $existing_value = $xfieldsdata[$poster_field_name] ?? '';

    $is_local = !empty($existing_value) && preg_match('#/uploads/posts/.*\.(jpg|jpeg|png|gif|webp)$#i', $existing_value);

    $save_poster = isset($vibix_settings_config['save_poster']) && $vibix_settings_config['save_poster'] === '1';

    if ($save_poster) {
        if ($is_local) {
            $local_file_path = rtrim(ROOT_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($existing_value, '/\\');
            $local_file_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $local_file_path);
            if (file_exists($local_file_path)) {
                return;
            }
        }

        $ext = pathinfo(parse_url($poster_url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $ext = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? $ext : 'jpg';

        $poster_name = 'poster_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $month = date('Y-m');
        $upload_dir = ROOT_DIR . "/uploads/posts/{$month}/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            @chmod($upload_dir, 0777);
        }

        $poster_path = $upload_dir . $poster_name;
        $img_data = @file_get_contents($poster_url);

        if ($img_data !== false) {
            file_put_contents($poster_path, $img_data);
            @chmod($poster_path, 0666);
            $xfieldsdata[$poster_field_name] = "/uploads/posts/{$month}/{$poster_name}";
        } else {
            $xfieldsdata[$poster_field_name] = $poster_url;
        }
    } else {
        if ($is_local) {
            $local_path = ROOT_DIR . $existing_value;
            if (file_exists($local_path)) {
                @unlink($local_path);
            }
        }

        $xfieldsdata[$poster_field_name] = $poster_url;
    }
}

function generate_json_ld($vibix_post, $dle_post_id) {
    $type = ($vibix_post['type'] == 'serial') ? "TVSeries" : "Movie";

    $microdata = [
        "@context" => "https://schema.org",
        "@type" => $type,
        "name" => $vibix_post['name_rus'] ?? $vibix_post['name'],
        "alternateName" => $vibix_post['name_original'],
        "description" => $vibix_post['description_rus'] ?? $vibix_post['description'],
        "image" => $vibix_post['preview'],
        "datePublished" => ($vibix_post['year'] ?? date('Y')) . '-01-01',
        "genre" => array_map('trim', explode(',', $vibix_post['genre'])),
        "countryOfOrigin" => $vibix_post['country'],
        "url" => get_full_url($dle_post_id),
    ];

    if ($type == "Movie" && !empty($vibix_post['duration']) && intval($vibix_post['duration']) > 0) {
        $microdata["duration"] = "PT" . intval($vibix_post['duration']) . "M";
    }

    if ($type == "TVSeries") {
        unset($microdata['duration']);

        $seasons_count = count($vibix_post['episodes'] ?? []);
        $total_episodes = array_sum(array_map('count', $vibix_post['episodes'] ?? []));

        if ($seasons_count > 0) {
            $microdata["numberOfSeasons"] = $seasons_count;
        }
        if ($total_episodes > 0) {
            $microdata["numberOfEpisodes"] = $total_episodes;
        }
    }

    if (!empty($vibix_post['imdb_id'])) {
        $microdata["sameAs"] = "https://www.imdb.com/title/" . $vibix_post['imdb_id'];
    }

    if (!empty($vibix_post['kp_rating'])) {
        $microdata["aggregateRating"] = [
            "@type" => "AggregateRating",
            "ratingValue" => $vibix_post['kp_rating'],
            "bestRating" => "10",
            "ratingCount" => $vibix_post['kp_votes'] ?? '1000'
        ];
    }

    if (!empty($vibix_post['persons']['actor'])) {
        $microdata["actor"] = array_slice(array_map(function($person) {
            return ["@type" => "Person", "name" => $person['name_anyway']];
        }, $vibix_post['persons']['actor']), 0, 10);
    }

    if (!empty($vibix_post['persons']['director'])) {
        $microdata["director"] = array_map(function($person) {
            return ["@type" => "Person", "name" => $person['name_anyway']];
        }, $vibix_post['persons']['director']);
    }

    if (!empty($vibix_post['persons']['producer'])) {
        $microdata["producer"] = array_slice(array_map(function($person) {
            return ["@type" => "Person", "name" => $person['name_anyway']];
        }, $vibix_post['persons']['producer']), 0, 3);
    }

    if (!empty($vibix_post['persons']['writer'])) {
        $microdata["author"] = array_map(function($person) {
            return ["@type" => "Person", "name" => $person['name_anyway']];
        }, $vibix_post['persons']['writer']);
    }

    if (!empty($vibix_post['persons']['composer'])) {
        $microdata["musicBy"] = [
            "@type" => "Person",
            "name" => $vibix_post['persons']['composer'][0]['name_anyway']
        ];
    }

    return json_encode(array_filter($microdata), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function generate_og_microdata($vibix_post, $dle_post_id) {
    global $config;

    $type = ($vibix_post['type'] == 'serial') ? "video.tv_show" : "video.movie";

    $og_data = [
        'og:title' => $vibix_post['name_rus'] ?? $vibix_post['name'],
        'og:description' => $vibix_post['description_rus'] ?? $vibix_post['description'],
        'og:image' => $vibix_post['preview'],
        'og:image:width' => '600',
        'og:image:height' => '900',
        'og:url' => get_full_url($dle_post_id),
        'og:type' => $type,
        'og:site_name' => $config['home_title'],
        'og:locale' => 'ru_RU'
    ];

    if ($type == "video.movie") {
        $og_data['video:duration'] = $vibix_post['duration'] * 60;
        $og_data['video:release_date'] = ($vibix_post['year'] ?? date('Y')) . '-01-01';

        if (!empty($vibix_post['persons']['actor'][0])) {
            $og_data['video:actor'] = $vibix_post['persons']['actor'][0]['name_anyway'];
        }
    } elseif ($type == "video.tv_show") {
        $og_data['video:series'] = $vibix_post['name_rus'] ?? $vibix_post['name'];
    }

    $og_data['video:quality'] = $vibix_post['quality'];

    if (!empty($vibix_post['persons']['director'][0])) {
        $og_data['video:director'] = $vibix_post['persons']['director'][0]['name_anyway'];
    }

    $tags = [];
    foreach (array_filter($og_data) as $property => $content) {
        $tags[] = "<meta property=\"{$property}\" content=\"" . htmlspecialchars($content, ENT_QUOTES) . "\">";
    }

    return implode(' ', $tags);
}

function get_full_url($post_id) {
    global $config;
    return $config['http_home_url'] . $post_id . '.html';
}