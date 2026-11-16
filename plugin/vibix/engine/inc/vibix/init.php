
<?php

require_once(ENGINE_DIR . '/inc/vibix/functions.php');

$alerts_msg = [];
if (!empty($_REQUEST['action']))
    require_once(ENGINE_DIR . '/inc/vibix/actions.php');

include(ENGINE_DIR . '/data/vibix.config.php');
include(ENGINE_DIR . '/data/vibix.settings_config.php');
include(ENGINE_DIR . '/data/vibix.xfields_config.php');
include(ENGINE_DIR . '/data/vibix.category_config.php');
foreach ($vibix_category_config as &$item)
    $item = explode(',', $item);
unset($item);
?>

<?php

//статус токена

$token_status = 200;
$token_api_error = false;
$token_api_error_message = '';

if ($curl = curl_init()) {
    if (!empty($vibix_settings_config['api_token'])) {
        
        $check_url = $vibix_config['api_server'] . "/token/validate?token=" . $vibix_settings_config['api_token'];
    } else {
        
        $check_url = $vibix_config['api_server'] . "/token/validate";
    }

    curl_setopt($curl, CURLOPT_URL, $check_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($curl, CURLOPT_TIMEOUT, 8);
    curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $token_result = curl_exec($curl);
    $token_errno  = curl_errno($curl);
    $token_error  = curl_error($curl);
    $token_http   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($token_errno !== 0 || $token_result === false) {
        $token_api_error = true;
        $token_api_error_message = 'Ошибка соединения: ' . $token_error . ' (errno=' . $token_errno . ')';
    }
    elseif ($token_http >= 500) {
        $token_api_error = true;
        $token_api_error_message = 'API вернул ошибку сервера: HTTP ' . $token_http;
    }
    elseif ($token_http === 0) {
        $token_api_error = true;
        $token_api_error_message = 'API не отвечает';
    }
    
}


if (!empty($_REQUEST['action_ajax'])) {
    include(ENGINE_DIR . '/inc/vibix/actions_ajax.php');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($Res);
    exit();
}

$alerts_msg = implode('', $alerts_msg);
$tab = $_GET['act'] ?? 'catalog';

echoheader("<i class=\"fa fa-video-camera position-left\"></i><span class=\"text-semibold\">Vibix видеобалансер</span>", array('?mod=vibix' => 'Vibix'));

if (!empty($token_api_error)) {
    echo '<div class="alert alert-warning alert-styled-left alert-arrow-left alert-component">';
    echo '<b>API Vibix недоступен.</b> ';
    echo ' ' . htmlspecialchars($token_api_error_message) . '. ';
    echo 'Обратитесь к хостеру или в <a href="https://t.me/vibix_tv" target="_blank">поддержку Vibix</a>.';
    echo '</div>';
}

//проверка на пустой или невалидный токен

if (empty($vibix_settings_config['api_token']) or ($token_status == 401)) { ?>
    <style>
        .select:invalid {
            border: 2px solid red;
            box-shadow: 0 0 5px red;
        }
        .pulseblock {

            box-shadow: 0 0 0 rgba(55, 63, 80, 0.4);
            animation: pulseblock 2s infinite;
        }

        .pulseblock:hover {
            animation: none;
        }

        @-webkit-keyframes pulseblock {
            0% {
                -webkit-box-shadow: 0 0 0 0 rgba(55, 63, 80, 0.95);
            }

            70% {
                -webkit-box-shadow: 0 0 0 20px rgba(55, 63, 80, 0);
            }

            100% {
                -webkit-box-shadow: 0 0 0 0 rgba(55, 63, 80, 0);
            }
        }

        @keyframes pulseblock {
            0% {
                -moz-box-shadow: 0 0 0 0 rgba(55, 63, 80, 0.95);
                box-shadow: 0 0 0 0 rgba(55, 63, 80, 0.95);
            }

            70% {
                -moz-box-shadow: 0 0 0 20px rgba(55, 63, 80, 0);
                box-shadow: 0 0 0 20px rgba(55, 63, 80, 0);
            }

            100% {
                -moz-box-shadow: 0 0 0 0 rgba(55, 63, 80, 0);
                box-shadow: 0 0 0 0 rgba(55, 63, 80, 0);
            }
        }
    </style>
    <div class="alert alert-danger alert-styled-left alert-arrow-left alert-component">
        Внимание! Для использования плагина необходимо получить <b>API token</b>. Для этого зарегистрируйтесь и авторизуйтесь в своём кабинете на <a target="_blank" href="https://vibix.org">Vibix.org</a>.
        Далее в настройках сгенерируйте API token и укажите его в формe ниже.<br>
        По всем вопросам наш <a href="https://t.me/vibix_tv" target="_blank"><i class="fa fa-telegram"></i> Телеграм </a>

    </div>
    <div id="vibix" class="panel panel-default">
        <form name="settings" action="" method="post" class="tab" id="settings" style="position: relative;" onsubmit="validateToken()">
            <div class="panel-body border-bottom">
                Основные Настройки
            </div>
            <table class="table table-striped">
                <tbody>
                    <tr>
                        <td class="col-xs-6 col-sm-6 white-line">
                            <h6 class="media-heading text-semibold">API token</h6>
                            <span class="text-muted text-size-small hidden-xs">Его Вы можете получить в своём кабинете <a target="_blank" href="https://vibix.org">Vibix.org</a></span>
                        </td>
                        <td class="col-xs-6 col-sm-6 white-line">
                            <input type="text" class="form-control" name="settings_config[api_token]" value="" placeholder="  Введите ваш api token" required />
                        </td>
                    </tr>
                    <?php
                    $options = "";
                    $options = "";
                    $fields = vibix_xfieldsload();

                    if (!empty($fields)) {
                        foreach ($fields as $field_id => $field_data) {
                            if (DLE_VERSION >= 19) {
                                if (isset($field_data['type']) && $field_data['type'] == 'text') {
                                    $options .= '<option value="' . $field_id . '">' . $field_data['name'] . '</option>';
                                }
                            } else {
                                if (isset($field_data[3]) && in_array($field_data[3], ['text'])) {
                                    $options .= '<option value="' . $field_id . '">' . $field_data[1] . '</option>';
                                }
                            }
                        }
                    }

                    if (empty($options)) {
                        $options = '<option value="" disabled>Нет текстовых полей</option>';
                    }

                    ?>
                    <tr>
                        <td class="col-xs-6 col-sm-6 white-line">
                            <h6 class="media-heading text-semibold">Кинопоиск ID</h6>
                            <span class="text-muted text-size-small hidden-xs"> Укажите поле в ваших дополнительных полях</span>
                        </td>
                        <td class="col-xs-6 col-sm-6 white-line">
                            <select name="kp_id" style="width:100%;max-width:350px;" class="uniform" required>
                                <option value="" disabled selected>Обязательно к заполнению</option><?= $options ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="col-xs-6 col-sm-6 white-line">
                            <h6 class="media-heading text-semibold">Ссылка на плеер Vibix</h6>
                            <span class="text-muted text-size-small hidden-xs"> Укажите поле в ваших дополнительных полях</span>
                        </td>
                        <td class="col-xs-6 col-sm-6 white-line">
                            <select name="embed_code_new" style="width:100%;max-width:350px;" class="uniform" required>
                                <option value="" disabled selected>Обязательно к заполнению</option><?= $options ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="panel-footer" style="position: sticky;bottom:0;">
                <input type="hidden" name="action" value="save_config">
                <input type="hidden" name="user_hash" value="<?= $dle_login_hash ?>">
                <input type="submit" class="btn bg-teal btn-sm btn-raised position-left legitRipple" value="Сохранить">
                <input type="reset" class="btn bg-slate-600 btn-sm btn-raised position-left legitRipple" value="Вернуть">
            </div>
        </form>
    </div>
    <script>
    async function validateToken() {
        event.preventDefault();

        let token = document.forms["settings"]["settings_config[api_token]"].value;

        $.ajax({
            type: "POST",
            url: '<?= vibix_ajax_url('validate_token'); ?>',
            data: {
                token: token,
            },
            success: function(response) {

                if (response.status == 200) {
                    document.forms["settings"].submit();
                } else {
                    document.forms["settings"]["settings_config[api_token]"].value = response.message;
                    document.forms["settings"]["settings_config[api_token]"].style.borderColor = 'red';
                }
            },
            error: function() {
                document.forms["settings"]["settings_config[api_token]"].value = "Ошибка проверки токена";
                document.forms["settings"]["settings_config[api_token]"].style.borderColor = 'red';
            }
        });
    }
</script>

<?php
    echofooter();
    exit();
}

?>
<div class="alert alert-info alert-styled-left alert-arrow-left alert-component">
    Внимание! Модуль находится в стадии тестирования. Но вы уже можете им пользоваться, не забудьте отправлять отзывы о багах и предложениях. Заранее спасибо от всей команды Vibix! <br>
    По всем вопросам наш <a href="https://t.me/vibix_tv" target="_blank"><i class="fa fa-telegram"></i> Телеграм </a>
</div>
<?php
    //проверка версии
    $upgrade_data = $db->query("SELECT version, upgradeurl FROM " . PREFIX . "_plugins WHERE name = 'vibix'");

    foreach ($upgrade_data as $row) {
        $upgrade_url = $row['upgradeurl'];
        $current_version = $row['version'];
    }

    if (!empty($upgrade_url)) {
        $ch = curl_init($upgrade_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $version_raw = curl_exec($ch);
        $version_errno = curl_errno($ch);
        curl_close($ch);

        if ($version_errno === 0 && $version_raw !== false) {
            $version = json_decode($version_raw, true);
            if ($version && !empty($version['version'])) {
                if ($version['version'] <> $current_version) {
                    echo "
                    <div class='alert alert-danger alert-styled-left alert-arrow-left alert-component'>
                        Внимание! У Вас установлена устаревшая версия плагина.
                    </div>";
                }
            }
        }
    }
?>

<?= $alerts_msg ?>

<style>
    

    #vibix-loading-overlay {
        display: flex;
        position: fixed;
        inset: 0;                 
        background: rgba(0, 0, 0, 0.45);
        z-index: 100000;
        align-items: center;      
        justify-content: center;  
        opacity: 0;
        visibility: hidden;
        transition: opacity .15s ease, visibility .15s ease;
    }

    #vibix-loading-overlay.vibix-visible {
        opacity: 1;
        visibility: visible;
    }

    #vibix-loading-overlay .vibix-loading-box {
        background: #fff;
        border-radius: 8px;
        padding: 24px 32px;
        text-align: center;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .25);
        min-width: 200px;
    }

    #vibix-loading-overlay .vibix-loading-box .fa-spinner {
        font-size: 32px;
        color: #26a69a;
        display: block;
        margin: 0 auto 10px;
    }

    #vibix-loading-overlay .vibix-loading-text {
        margin-top: 10px;
        font-size: 14px;
        color: #333;
    }

    .dle_theme_dark #vibix-loading-overlay .vibix-loading-box {
        background: #353F53;
    }

    .dle_theme_dark #vibix-loading-overlay .vibix-loading-text {
        color: #e0e0e0;
    }
    
    #profile {
        display: none;
    }

    .pulseblock {
        box-shadow: 0 0 0 rgba(55, 63, 80, 0.4);
        animation: pulseblock 2s infinite;
    }

    .pulseblock:hover {
        animation: none;
    }

    .dle_theme_dark .select2-container--default .select2-selection--multiple {
        background-color: #353F53;
    }

    .dle_theme_dark .select2-dropdown {
        background-color: #353F53;
    }


    .dle_theme_dark .select2-selection__choice {
        background-color: #262D3D !important;
    }

    .dle_theme_dark .select2-results__option[aria-selected=true][role=option] {
        background-color: #262D3D;
    }

    .dle_theme_dark .select2-results__option[aria-selected=false] {
        background-color: #353F53;
    }

    .dle_theme_dark .panel-footer {
        background-color: #3C4250;
    }



    .dle_theme_dark .select2-results__option--highlighted {
        background-color: #39f !important;
    }

    .dle_theme_dark .delete_task_button {
        background-color: #272D3D;
    }






    @-webkit-keyframes pulseblock {
        0% {
            -webkit-box-shadow: 0 0 0 0 rgba(55, 63, 80, 0.95);
        }

        70% {
            -webkit-box-shadow: 0 0 0 20px rgba(55, 63, 80, 0);
        }

        100% {
            -webkit-box-shadow: 0 0 0 0 rgba(55, 63, 80, 0);
        }
    }

    @keyframes pulseblock {
        0% {
            -moz-box-shadow: 0 0 0 0 rgba(55, 63, 80, 0.95);
            box-shadow: 0 0 0 0 rgba(55, 63, 80, 0.95);
        }

        70% {
            -moz-box-shadow: 0 0 0 20px rgba(55, 63, 80, 0);
            box-shadow: 0 0 0 20px rgba(55, 63, 80, 0);
        }

        100% {
            -moz-box-shadow: 0 0 0 0 rgba(55, 63, 80, 0);
            box-shadow: 0 0 0 0 rgba(55, 63, 80, 0);
        }
    }

    .btn-close {
        position: absolute;
        top: 5px;
        right: 5px;
        background: no-repeat;
        border: none;
    }

    .btn-close::before {
        content: "\f00d";
        font-size: 25px;
        font-family: 'FontAwesome';
    }

    .vibix-btn .fa-plus {
        margin-right: 0
    }

    @-webkit-keyframes spinner-border {
        to {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }

    @keyframes spinner-border {
        to {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }

    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: .2em;
    }

    .spinner-border {
        display: inline-block;
        width: 2rem;
        height: 2rem;
        vertical-align: center;
        border: .25em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        -webkit-animation: spinner-border .75s linear infinite;
        animation: spinner-border .75s linear infinite;
    }
</style>

<div class="navbar navbar-default navbar-component navbar-xs systemsettings">
    <ul class="nav navbar-nav visible-xs-block">
        <li class="full-width text-center"><a data-toggle="collapse" data-target="#navbar-filter" class="legitRipple"><i class="fa fa-bars"></i></a></li>
    </ul>
    <div class="navbar-collapse collapse" id="navbar-filter">
        <ul class="nav navbar-nav">
            <li id="catalog-btn"> <a href="?mod=vibix&act=catalog" class="tip legitRipple" data-original-title="Каталог"><i class="fa fa-th-list"></i> Каталог</a></li>
            <li id="settings-btn"> <a href="?mod=vibix&act=settings" class="tip legitRipple" title="" data-original-title="Настройки"><i class="fa fa-cog"></i> Настройки</a></li>
            <li id="mapping_fields-btn"> <a href="?mod=vibix&act=mapping_fields" class="tip legitRipple" data-original-title="Сопоставление полей"><i class="fa fa-cog"></i> Сопоставление полей</a></li>
            <li id="mapping_cats-btn"> <a href="?mod=vibix&act=mapping_cats" class="tip legitRipple" data-original-title="Сопоставление категорий"><i class="fa fa-cog"></i> Сопоставление категорий</a></li>
            <li id="statistics-btn"> <a href="?mod=vibix&act=statistics" class="tip legitRipple" data-original-title="Статистика"><i class="fa fa-bar-chart"></i> Статистика</a></li>
            <li id="profile-btn"> <a href="?mod=vibix&act=profile" class="tip legitRipple" data-original-title="Профиль"><i class="fa fa-user"></i> Профиль</a></li>
            <li id="request-btn"> <a href="?mod=vibix&act=request" class="tip legitRipple" data-original-title="Запросы и заявки"><i class="fa fa-cog"></i> Запросы и заявки</a></li>
            <li id="changelog-btn"> <a href="?mod=vibix&act=changelog" class="tip legitRipple" data-original-title="История версий"><i class="fa fa-file-text-o"></i> История версий</a></li>
            <li id="tasks-btn" class="mass_action"> <a href="?mod=vibix&act=tasks" class="tip legitRipple" data-original-title="Фоновые задания"><i class="fa fa-clock-o"></i> Фоновые задания</a></li>
        </ul>
        <span class="spinner-border spinner-border-sm" aria-hidden="true" style="margin: 7px -12px 0;display: none"></span>
    </div>
</div>


<script>
    function vibixLoading(show, text) {
        var $el = $('#vibix-loading-overlay');

        if (show) {
            if (!$el.length) {
                $el = $('<div id="vibix-loading-overlay">' +
                            '<div class="vibix-loading-box">' +
                                '<i class="fa fa-spinner fa-spin"></i>' +
                                '<div class="vibix-loading-text"></div>' +
                            '</div>' +
                        '</div>').appendTo('body');
            }
            $el.find('.vibix-loading-text').text(text || 'Загрузка...');
            $el.addClass('vibix-visible');
        } else {
            if ($el.length) {
                $el.removeClass('vibix-visible');
            }
        }
        return $el;
    }
    //Обёртка для select2 к фильтрам грида
    function initSelect2(selector) {
        $(selector).each(function() {
            var placeholder = '';
            var multiple = false;
            var width = 'resolve';
            var ctx = $(this);

            if ($(this).attr('multiple') == 'multiple') {
                multiple = true;
                $(this).closest('div').addClass('select2-block-multi')
            } else
                $(this).closest('div').addClass('select2-block-singl')

            if ($(this).data('placeholder'))
                placeholder = $(this).data('placeholder');

            if ($(this).data('select2-width'))
                width = $(this).data('select2-width');

            if ($(this).data('ajax-url')) {
                let url = $(this).data('ajax-url');
                let minlength = 3;

                if ($(this).data('minlength') !== undefined)
                    minlength = $(this).data('minlength');

                if ($(this).data('value'))
                    $.ajax({
                        dataType: 'JSON',
                        url: url + "&q=" + $(this).data('value'),
                    }).done(function(res) {
                        if (res['items'] && res['items'].length > 1) {
                            $.each(res['items'], function(i, item) {
                                $(ctx).append('<option value="' + item['id'] + '">' + item['text'] + '</option>')
                            });
                            $(ctx).val($(ctx).data('value')).change();
                        }
                    }).fail(function(d) {})
                $(this).select2({
                    minimumInputLength: minlength,
                    width: width,
                    placeholder: placeholder,
                    debug: true,
                    closeOnSelect: !multiple,
                    dropdownParent: $(this).closest('div'),
                    templateSelection: function(selection) {
                        return $.parseHTML('<span class="' + $(selection.element).attr('class') + '">' + selection.text + '</span>');
                    },
                    ajax: {
                        data: function(params) {
                            params['params'] = $(this).data('ajax-url');
                            var ctx = this;
                            $.each($(this).data(), function(field) {
                                if (field.toString().indexOf('param_') === 0)
                                    params[field.toString()] = $(ctx).data(field.toString())
                            })
                            return params;
                        },
                        url: url,
                        dataType: 'json',
                        processResults: function(data) {
                            return {
                                results: data.items
                            };
                        }
                    },
                    templateResult: function(data, container) {
                        if (data['class']) $(container).addClass(data['class']);
                        if (data['data-act']) $(container).data('act', data['data-act']);
                        if (data['data-name']) $(container).data('name', data['data-name']);
                        return data.text;
                    }
                });
            } else {
                $(this).select2({
                    language: "ru",
                    width: width,
                    placeholder: placeholder,
                    debug: true,
                    closeOnSelect: !multiple,
                    dropdownParent: $(this).closest('div'),
                    templateResult: function(data, container) {
                        if (data.element) $(container).addClass($(data.element).attr("class"));
                        return data.text;
                    }
                });
            }
        });
    }
    var ru = {
        "processing": "Подождите...",
        "search": "",
        "lengthMenu": "Показать _MENU_ записей",
        "info": "Записи с _START_ до _END_ из _TOTAL_ записей",
        "infoEmpty": "Записи с 0 до 0 из 0 записей",
        "infoFiltered": "(отфильтровано из _MAX_ записей)",
        "loadingRecords": "Загрузка записей...",
        "zeroRecords": "Записи отсутствуют.",
        "emptyTable": "В таблице отсутствуют данные",
        "paginate": {
            "first": "Первая",
            "previous": "Предыдущая",
            "next": "Следующая",
            "last": "Последняя"
        },
        "aria": {
            "sortAscending": ": активировать для сортировки столбца по возрастанию",
            "sortDescending": ": активировать для сортировки столбца по убыванию"
        },
        "select": {
            "rows": {
                "_": "Выбрано записей: %d",
                "1": "Выбрана одна запись"
            },
            "cells": {
                "_": "Выбрано %d ячеек",
                "1": "Выбрана 1 ячейка "
            },
            "columns": {
                "1": "Выбран 1 столбец ",
                "_": "Выбрано %d столбцов "
            }
        },
        "searchBuilder": {
            "conditions": {
                "string": {
                    "startsWith": "Начинается с",
                    "contains": "Содержит",
                    "empty": "Пусто",
                    "endsWith": "Заканчивается на",
                    "equals": "Равно",
                    "not": "Не",
                    "notEmpty": "Не пусто",
                    "notContains": "Не содержит",
                    "notStartsWith": "Не начинается на",
                    "notEndsWith": "Не заканчивается на"
                },
                "date": {
                    "after": "После",
                    "before": "До",
                    "between": "Между",
                    "empty": "Пусто",
                    "equals": "Равно",
                    "not": "Не",
                    "notBetween": "Не между",
                    "notEmpty": "Не пусто"
                },
                "number": {
                    "empty": "Пусто",
                    "equals": "Равно",
                    "gt": "Больше чем",
                    "gte": "Больше, чем равно",
                    "lt": "Меньше чем",
                    "lte": "Меньше, чем равно",
                    "not": "Не",
                    "notEmpty": "Не пусто",
                    "between": "Между",
                    "notBetween": "Не между ними"
                },
                "array": {
                    "equals": "Равно",
                    "empty": "Пусто",
                    "contains": "Содержит",
                    "not": "Не равно",
                    "notEmpty": "Не пусто",
                    "without": "Без"
                }
            },
            "data": "Данные",
            "deleteTitle": "Удалить условие фильтрации",
            "logicAnd": "И",
            "logicOr": "Или",
            "title": {
                "0": "Конструктор поиска",
                "_": "Конструктор поиска (%d)"
            },
            "value": "Значение",
            "add": "Добавить условие",
            "button": {
                "0": "Конструктор поиска",
                "_": "Конструктор поиска (%d)"
            },
            "clearAll": "Очистить всё",
            "condition": "Условие",
            "leftTitle": "Превосходные критерии",
            "rightTitle": "Критерии отступа"
        },
        "searchPanes": {
            "clearMessage": "Очистить всё",
            "collapse": {
                "0": "Панели поиска",
                "_": "Панели поиска (%d)"
            },
            "count": "{total}",
            "countFiltered": "{shown} ({total})",
            "emptyPanes": "Нет панелей поиска",
            "loadMessage": "Загрузка панелей поиска",
            "title": "Фильтры активны - %d",
            "showMessage": "Показать все",
            "collapseMessage": "Скрыть все"
        },
        "buttons": {
            "pdf": "PDF",
            "print": "Печать",
            "collection": "Коллекция <span class=\"ui-button-icon-primary ui-icon ui-icon-triangle-1-s\"><\/span>",
            "colvis": "Видимость столбцов",
            "colvisRestore": "Восстановить видимость",
            "copy": "Копировать",
            "copyTitle": "Скопировать в буфер обмена",
            "csv": "CSV",
            "excel": "Excel",
            "pageLength": {
                "-1": "Показать все строки",
                "_": "Показать %d строк",
                "1": "Показать 1 строку"
            },
            "removeState": "Удалить",
            "renameState": "Переименовать",
            "copySuccess": {
                "1": "Строка скопирована в буфер обмена",
                "_": "Скопировано %d строк в буфер обмена"
            },
            "createState": "Создать состояние",
            "removeAllStates": "Удалить все состояния",
            "savedStates": "Сохраненные состояния",
            "stateRestore": "Состояние %d",
            "updateState": "Обновить",
            "copyKeys": "Нажмите ctrl  или u2318 + C, чтобы скопировать данные таблицы в буфер обмена.  Для отмены, щелкните по сообщению или нажмите escape."
        },
        "decimal": ".",
        "infoThousands": ",",
        "autoFill": {
            "cancel": "Отменить",
            "fill": "Заполнить все ячейки <i>%d<i><\/i><\/i>",
            "fillHorizontal": "Заполнить ячейки по горизонтали",
            "fillVertical": "Заполнить ячейки по вертикали",
            "info": "Информация"
        },
        "datetime": {
            "previous": "Предыдущий",
            "next": "Следующий",
            "hours": "Часы",
            "minutes": "Минуты",
            "seconds": "Секунды",
            "unknown": "Неизвестный",
            "amPm": [
                "AM",
                "PM"
            ],
            "months": {
                "0": "Январь",
                "1": "Февраль",
                "10": "Ноябрь",
                "11": "Декабрь",
                "2": "Март",
                "3": "Апрель",
                "4": "Май",
                "5": "Июнь",
                "6": "Июль",
                "7": "Август",
                "8": "Сентябрь",
                "9": "Октябрь"
            },
            "weekdays": [
                "Вс",
                "Пн",
                "Вт",
                "Ср",
                "Чт",
                "Пт",
                "Сб"
            ]
        },
        "editor": {
            "close": "Закрыть",
            "create": {
                "button": "Новый",
                "title": "Создать новую запись",
                "submit": "Создать"
            },
            "edit": {
                "button": "Изменить",
                "title": "Изменить запись",
                "submit": "Изменить"
            },
            "remove": {
                "button": "Удалить",
                "title": "Удалить",
                "submit": "Удалить",
                "confirm": {
                    "_": "Вы точно хотите удалить %d строк?",
                    "1": "Вы точно хотите удалить 1 строку?"
                }
            },
            "multi": {
                "restore": "Отменить изменения",
                "title": "Несколько значений",
                "info": "Выбранные элементы содержат разные значения для этого входа. Чтобы отредактировать и установить для всех элементов этого ввода одинаковое значение, нажмите или коснитесь здесь, в противном случае они сохранят свои индивидуальные значения.",
                "noMulti": "Это поле должно редактироваться отдельно, а не как часть группы"
            },
            "error": {
                "system": "Возникла системная ошибка (<a target=\"\\\" rel=\"nofollow\" href=\"\\\">Подробнее<\/a>)."
            }
        },
        "searchPlaceholder": "Что ищете?",
        "stateRestore": {
            "creationModal": {
                "button": "Создать",
                "search": "Поиск",
                "columns": {
                    "search": "Поиск по столбцам",
                    "visible": "Видимость столбцов"
                },
                "name": "Имя:",
                "order": "Сортировка",
                "paging": "Страницы",
                "scroller": "Позиция прокрутки",
                "searchBuilder": "Редактор поиска",
                "select": "Выделение",
                "title": "Создать новое состояние",
                "toggleLabel": "Включает:"
            },
            "removeJoiner": "и",
            "removeSubmit": "Удалить",
            "renameButton": "Переименовать",
            "duplicateError": "Состояние с таким именем уже существует.",
            "emptyError": "Имя не может быть пустым.",
            "emptyStates": "Нет сохраненных состояний",
            "removeConfirm": "Вы уверены, что хотите удалить %s?",
            "removeError": "Не удалось удалить состояние.",
            "removeTitle": "Удалить состояние",
            "renameLabel": "Новое имя для %s:",
            "renameTitle": "Переименовать состояние"
        },
        "thousands": " "
    };
</script>

<div id="vibix" class="panel panel-default">

    <?php
    $tab = $_GET['act'] ?? 'catalog';
    ?>
    <script>
        $(<?php echo "'#" . $tab . "-btn'"; ?>).addClass("active");
    </script>
    <?php
    require ENGINE_DIR . "/inc/vibix/tabs/$tab.php"
    ?>

</div>

<script>
    $(document).ready(function() {
        $('.categoryselect').chosen({
            no_results_text: 'Не найден: '
        });
    });

</script>

<script>
    var script = document.createElement('script');
    script.src = 'https://graphicslab.io/sdk/v2/rendex-sdk.min.js';
    document.head.appendChild(script);
</script>

<?php
    echofooter();
?>