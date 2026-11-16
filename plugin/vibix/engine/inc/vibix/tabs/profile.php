<?php
    if (!defined('DATALIFEENGINE') || !defined('LOGGED_IN')) {
        die('Hacking attempt!');
    }

    $api_token = $vibix_settings_config['api_token'] ?? '';
    require_once(ENGINE_DIR . '/inc/vibix/functions.php');
    

    $diag_static = [
        'DLE_VERSION'            => defined('DLE_VERSION') ? DLE_VERSION : 'NOT DEFINED',
        "config['version_id']"   => $config['version_id'] ?? 'не задан',
        "config['version']"      => $config['version'] ?? 'не задан',
        'ENGINE_DIR'             => defined('ENGINE_DIR') ? ENGINE_DIR : 'NOT DEFINED',
        'ROOT_DIR'               => defined('ROOT_DIR') ? ROOT_DIR : 'NOT DEFINED',
        'PHP version'            => PHP_VERSION,
        'PHP SAPI'               => php_sapi_name(),
        'memory_limit'           => ini_get('memory_limit'),
        'max_execution_time'     => ini_get('max_execution_time'),
        'default_socket_timeout' => ini_get('default_socket_timeout'),
        'post_max_size'          => ini_get('post_max_size'),
        'upload_max_filesize'    => ini_get('upload_max_filesize'),
        'short_open_tag'         => ini_get('short_open_tag'),
        'display_errors'         => ini_get('display_errors'),
        'curl'                   => extension_loaded('curl')
                                        ? 'OK (' . (curl_version()['version'] ?? '?') . ', ' . (curl_version()['ssl_version'] ?? '?') . ')'
                                        : 'НЕТ',
        'openssl'                => extension_loaded('openssl') ? 'OK' : 'НЕТ',
        'json'                   => extension_loaded('json') ? 'OK' : 'НЕТ',
        'mbstring'               => extension_loaded('mbstring') ? 'OK' : 'НЕТ',
        'mysqli'                 => extension_loaded('mysqli') ? 'OK' : 'НЕТ',
        'pdo_mysql'              => extension_loaded('pdo_mysql') ? 'OK' : 'НЕТ',
        'api_server'             => $vibix_config['api_server'] ?? 'НЕ ЗАДАН',
        'api_token'              => !empty($vibix_settings_config['api_token'])
                                        ? $vibix_settings_config['api_token']
                                        : 'НЕ ЗАДАН',
        'xfields_config'         => empty($vibix_xfields_config) ? 'пусто' : count($vibix_xfields_config) . ' полей',
        'category_config'        => empty($vibix_category_config) ? 'пусто' : count($vibix_category_config) . ' категорий',
        'memory usage'           => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
        'memory peak'            => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
        'uname'                  => php_uname(),
    ];
    ?>

    

    <div class="alert alert-warning alert-styled-left alert-arrow-left alert-component">
        Отображение профиля можно отключить в <a href="https://vibix.org/#profile/" target="_blank"> личном кабинете </a> "Настройки - Показ профиля".
    </div>

<?php if (empty($api_token)): ?>
    <div class="alert alert-danger">
        <i class="fa fa-exclamation-triangle"></i>
        Для работы профиля необходимо настроить <b>API Token</b> в разделе <a href="?mod=vibix&act=settings">Настройки</a>.
    </div>
<?php else: ?>

    <form action="" method="post" style="position: relative;">
        <div id="profile-container">
            <div id="profile-loading" class="text-center" style="padding: 20px;">
                <i class="fa fa-spinner fa-spin fa-2x"></i> Загрузка профиля...
            </div>

            <div id="profile-content" style="display: none;">
                <table class="table table-striped">
                    <tbody>
                    <tr>
                        <td class="col-xs-4 col-sm-4" style="vertical-align: middle;">
                            <h6 class="media-heading text-semibold">Имя</h6>
                        </td>
                        <td class="col-xs-8 col-sm-8">
                            <input type="text" class="form-control" id="name" disabled />
                        </td>
                    </tr>
                    <tr>
                        <td class="col-xs-4 col-sm-4" style="vertical-align: middle;">
                            <h6 class="media-heading text-semibold">Email</h6>
                        </td>
                        <td class="col-xs-8 col-sm-8">
                            <input type="text" class="form-control" id="email" disabled />
                        </td>
                    </tr>
                    <tr>
                        <td class="col-xs-4 col-sm-4" style="vertical-align: middle;">
                            <h6 class="media-heading text-semibold">Сайты</h6>
                        </td>
                        <td class="col-xs-8 col-sm-8">
                            <input type="text" class="form-control" id="sites" disabled />
                        </td>
                    </tr>
                    <tr>
                        <td class="col-xs-4 col-sm-4" style="vertical-align: middle;">
                            <h6 class="media-heading text-semibold">CPM</h6>
                        </td>
                        <td class="col-xs-8 col-sm-8">
                            <input type="text" class="form-control" id="cpm" disabled />
                        </td>
                    </tr>
                    <tr>
                        <td class="col-xs-4 col-sm-4" style="vertical-align: middle;">
                            <h6 class="media-heading text-semibold">Протокол выплат</h6>
                        </td>
                        <td class="col-xs-8 col-sm-8">
                            <input type="text" class="form-control" id="payment_protocol" disabled />
                        </td>
                    </tr>
                    <tr>
                        <td class="col-xs-4 col-sm-4" style="vertical-align: middle;">
                            <h6 class="media-heading text-semibold">Кошелек для выплат</h6>
                        </td>
                        <td class="col-xs-8 col-sm-8">
                            <input type="text" class="form-control" id="wallet" disabled />
                        </td>
                    </tr>
                    </tbody>
                </table>

                <div id="profile-error" class="alert alert-danger" style="display: none;"></div>
            </div>
        </div>
    </form>

    <div class="panel panel-default" style="margin-bottom: 15px;">
        <div class="panel-heading" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between;" data-toggle="collapse" data-target="#vibix-diag-body">
            <h6 class="media-heading text-semibold" style="margin: 0;">Диагностика</h6>
            <i class="fa fa-chevron-down"></i>
        </div>
        <div id="vibix-diag-body" class="panel-body collapse" style="padding: 0;">
            <div style="padding: 10px 15px; border-bottom: 1px solid rgba(128,128,128,0.2); text-align: right;">
                <a href="#" onclick="exportVibixDiag(); return false;" title="Скачать отчёт">
                    <i class="fa fa-download"></i> Экспорт
                </a>
            </div>
            <table class="table table-striped" style="margin: 0;" id="vibix-diag-table">
                <tbody>
                <?php foreach ($diag_static as $key => $value): ?>
                    <tr>
                        <td style="width: 35%;"><b><?= htmlspecialchars($key) ?></b></td>
                        <td style="font-family: monospace; word-break: break-all;"><?= htmlspecialchars((string)$value) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function exportVibixDiag() {
            var lines = [];
            lines.push('=== Информация о сервере и плагине Vibix ===');
            lines.push('Дата: ' + new Date().toLocaleString('ru-RU'));
            lines.push('Сайт: ' + window.location.hostname);
            lines.push('='.repeat(60));
            lines.push('');

            var rows = document.querySelectorAll('#vibix-diag-table tbody tr');
            var maxKeyLen = 0;
            var data = [];

            rows.forEach(function(row) {
                var cells = row.querySelectorAll('td');
                if (cells.length === 2) {
                    var key = cells[0].textContent.trim();
                    var val = cells[1].textContent.trim();
                    data.push([key, val]);
                    if (key.length > maxKeyLen) maxKeyLen = key.length;
                }
            });

            data.forEach(function(pair) {
                var key = pair[0];
                var val = pair[1];
                while (key.length < maxKeyLen) key += ' ';
                lines.push(key + '  : ' + val);
            });

            lines.push('');
            lines.push('='.repeat(60));
            lines.push('Конец отчёта');

            var content = lines.join('\n');
            var blob = new Blob([content], { type: 'text/plain;charset=utf-8' });

            var now = new Date();
            var pad = function(n) { return n < 10 ? '0' + n : n; };
            var filename = window.location.hostname + '_server_info_' +
                now.getFullYear() + '-' +
                pad(now.getMonth() + 1) + '-' +
                pad(now.getDate()) + '_' +
                pad(now.getHours()) + '-' +
                pad(now.getMinutes()) + '.txt';

            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>

    <script>
        $(document).ready(function() {
            getProfile();
        });

        async function getProfile() {
            var token = '<?= addslashes($api_token) ?>';

            if (!token) {
                $('#profile-loading').html('<div class="alert alert-danger">API Token не настроен</div>');
                return;
            }

            try {
                var response = await $.ajax({
                    type: "GET",
                    url: '<?= vibix_ajax_url() . 'redirect_profile_get' ?>'

                });

                $('#profile-loading').hide();
                $('#profile-content').show();

                if (response.status == 'error') {
                    $('#profile-error').html('<i class="fa fa-exclamation-triangle"></i> ' + response.message).show();
                    return;
                }

                if (response.data && !$.isEmptyObject(response.data)) {
                    var data = response.data;

                    if (data.name !== undefined) $('#name').val(data.name);
                    if (data.email !== undefined) $('#email').val(data.email);
                    if (data.sites !== undefined) {
                        var sites = Array.isArray(data.sites) ? data.sites.join(', ') : data.sites;
                        $('#sites').val(sites);
                    }
                    if (data.cpm !== undefined) $('#cpm').val(data.cpm);
                    if (data.payment_protocol !== undefined) $('#payment_protocol').val(data.payment_protocol);
                    if (data.wallet !== undefined) $('#wallet').val(data.wallet);
                } else {
                    $('#profile-content').prepend('<div class="alert alert-info">Профиль не найден</div>');
                }
            } catch (error) {
                $('#profile-loading').html(
                    '<div class="alert alert-danger">' +
                    '<i class="fa fa-exclamation-triangle"></i> ' +
                    'Ошибка загрузки профиля: ' + (error.statusText || error.message) +
                    '</div>'
                );
            }
        }
    </script>

<?php endif; ?>