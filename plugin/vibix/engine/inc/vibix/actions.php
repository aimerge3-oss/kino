<?php

function save_vibix_config($form_config)
{
    global $config;
    $find = array();
    $replace = array();

    $find[] = "'\r'";
    $replace[] = "";
    $find[] = "'\n'";
    $replace[] = "";

    foreach ($form_config as $var => $data) {
        if (!is_array($data))
            continue;
        $handler = fopen(ENGINE_DIR . '/data/vibix.' . $var . '.php', "w");
        fwrite($handler, "<?PHP \n\n//Vibix configurations\n\n\$vibix_{$var} = array(\n\n");

        foreach ($data as $name => $value) {
            if ($name == 'saved_filters' && is_array($value)) {
                $value_str = var_export($value, true);
                fwrite($handler, "'{$name}' => {$value_str},\n");
                continue;
            }

            if (is_array($value))
                $value = implode(',', $value);
            $value = trim(strip_tags(stripslashes($value)));
            $value = htmlspecialchars($value, ENT_QUOTES, $config['charset']);
            $value = preg_replace($find, $replace, $value);
            $name = trim(strip_tags(stripslashes($name)));
            $name = htmlspecialchars($name, ENT_QUOTES, $config['charset']);
            $name = preg_replace($find, $replace, $name);

            $value = str_replace('/', "", $value);
            $value = str_replace(chr(92), "", $value);
            $value = str_replace(chr(0), "", $value);
            $value = str_ireplace("decode", "dec&#111;de", $value);

            $name = str_replace("$", "&#036;", $name);
            $name = str_replace("{", "&#123;", $name);
            $name = str_replace("}", "&#125;", $name);
            $name = str_replace(".", "", $name);
            $name = str_replace('/', "", $name);
            $name = str_replace(chr(92), "", $name);
            $name = str_replace(chr(0), "", $name);
            $name = str_replace('(', "", $name);
            $name = str_replace(')', "", $name);
            $name = str_ireplace("decode", "dec&#111;de", $name);

            fwrite($handler, "'{$name}' => '{$value}',\n");
        }
        fwrite($handler, ");\n\n?>");
        fclose($handler);
        clear_cache();
        if (function_exists('opcache_reset'))
            opcache_reset();
    }
}

if ($_REQUEST['user_hash'] == "" or $_REQUEST['user_hash'] != $dle_login_hash)
    die("Hacking attempt! User not found");

if (in_array($_REQUEST['action'], ["save_config", "save_mapping_fields"])) {
    $db->query("INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras)  values ('" . $db->safesql($member_id['name']) . "', '{$_TIME}', '{$_IP}', '78', '')");
    
    if (!empty($_POST['xfields_config']) or !empty($_POST['category_config']) or !empty($_POST['settings_config'])) {

        if (isset($_POST['kp_id'])) {
            $_POST['xfields_config'][$_POST['kp_id']] = 'kp_id';
            unset($_POST['kp_id']);
        }
        if (isset($_POST['embed_code_new'])) {
            $_POST['xfields_config'][$_POST['embed_code_new']] = 'embed_code_new';
            unset($_POST['embed_code_new']);
        }

        $existing_settings = [];
        if (file_exists(ENGINE_DIR . '/data/vibix.settings_config.php')) {
            include(ENGINE_DIR . '/data/vibix.settings_config.php');
            $existing_settings = $vibix_settings_config;
        }
        
        if (!isset($_POST['settings_config']) || !is_array($_POST['settings_config'])) {
            $_POST['settings_config'] = [];
        }
        
        $_POST['settings_config'] = array_merge($existing_settings, $_POST['settings_config']);
        
        if (isset($existing_settings['saved_filters']) && empty($_POST['settings_config']['saved_filters'])) {
            $_POST['settings_config']['saved_filters'] = $existing_settings['saved_filters'];
        }

        save_vibix_config($_POST);
    }
}

if (in_array($_REQUEST['action'], ["update_links"])) {
    $vibix_config['api_server'] = $_POST['api_server'];
    $vibix_settings_config['api_token'] = $_POST['api_token'];
    $film_data = getApi('/publisher/catalog/data', [
        'draw'   => 1,
        'start' => 0,
        'length' => 30,
        'columns' => [['data' => '', 'name' => '', 'searchable' => true, 'orderable' => true,]],
        'order' => [['column' => 0, 'dir' => 'desc']],
        'search' => ['value' => '276589']
    ]);
    $film_link = $film_data['data'][0]['embed_code_new'];
    $old_links = $db->query("SELECT * FROM " . PREFIX . "_post WHERE xfields LIKE '%https://videoframe1.com/%'")->num_rows;
    
    if (!empty($film_link) and $old_links) {
        $embed_code_new = explode('.', $film_link);
        $user_prefix = explode('//', $embed_code_new[0]);
        $user_prefix = $user_prefix[1];
        if (!empty($user_prefix)) {
            $db->query("UPDATE " . PREFIX . "_post
                SET xfields = REPLACE(xfields, 'https://videoframe1.com/', 'https://" . $user_prefix . ".videoframe1.com/')
                WHERE xfields LIKE '%https://videoframe1.com/%';");
        }
    }
    save_vibix_config($_POST);
}
?>