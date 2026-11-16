<form action="" method="post" class="tab" id="settings" style="position: relative;">
    
    <!-- Основные настройки -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h6 class="panel-title">Основные настройки</h6>
        </div>
        <table class="table table">
            <tbody>
                <tr>
                    <td class="col-xs-6 col-sm-6">
                        <h6 class="media-heading text-semibold">API token</h6>
                        <span class="text-muted text-size-small hidden-xs">Его Вы можете получить в своём кабинете <a target="_blank" href="https://vibix.org">Vibix.org</a></span>
                    </td>
                    <td class="col-xs-6 col-sm-6">
                        <input type="text" class="form-control" name="settings_config[api_token]" value="<?= $vibix_settings_config['api_token'] ?>" />
                    </td>
                </tr>
                <tr>
                    <td class="col-xs-6 col-sm-6">
                        <h6 class="media-heading text-semibold">Автор материала</h6>
                    </td>
                    <td class="col-xs-6 col-sm-6">
                        <input type="text" class="form-control" name="settings_config[tpl_autor]" value="<?= empty($vibix_settings_config['tpl_autor']) ? 'Vibix' : $vibix_settings_config['tpl_autor'] ?>" />
                    </td>
                </tr>
                <tr>
                    <td class="col-xs-6 col-sm-6">
                        <h6 class="media-heading text-semibold">
                            Хранить постер локально 
                            <span style="color: #ff9800;">(Нагрузка!)</span>
                        </h6>
                        </h6> 
                        <span class="text-muted text-size-small hidden-xs">Скачивание постеров сильно увеличивает время добавления новостей. Дважды
                            подумайте надо ли вам это.</span>
                    </td>
                    <td class="col-xs-6 col-sm-6">
                        <input type="hidden" name="settings_config[save_poster]" value="0">
                        <input type="checkbox" name="settings_config[save_poster]" value="1" <?= !empty($vibix_settings_config['save_poster']) ? 'checked' : '' ?> />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Авто-создание заданий -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h6 class="panel-title">Автоматическое создание заданий по сохраненным фильтрам</h6>
        </div>
        <div class="panel-body">
            <span class="text-muted text-size-small">Настройки для автоматического создания фоновых заданий по сохранённым фильтрам. Для работы фоновых заданий настройте планировщик заданий Cron на исполнение скрипта /cron_vibix.php, он находится в корневой директории вашего сайта.</span>
        </div>
        
        <div class="panel-body">
            <?php 
            $saved_filters = $vibix_settings_config['saved_filters'] ?? [];
            if (empty($saved_filters)) { ?>
                <div class="alert alert-warning alert-styled-left alert-arrow-left alert-component">
                  
                    Для автоматического создания заданий необходимо сохранить фильтры в 
                    <a href="?mod=vibix&act=catalog"><b>Каталоге</b></a>. Выберите нужные фильтры и нажмите 
                    <b>"Сохранить фильтр для авто-обновления"</b>.
                </div>
            <?php } else { ?>
                <div class="alert alert-success alert-styled-left alert-arrow-left alert-component">
                    
                    Авто-создание заданий будет использовать сохраненные фильтры. При необходимости вы можете 
                    <a href="?mod=vibix&act=catalog"><b>обновить фильтры</b></a> в каталоге.
                </div>
            <?php } ?>
        </div>

        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="col-xs-4 col-sm-4">
                        <h6 class="media-heading text-semibold">Включить</h6>
                        <span class="text-muted text-size-small hidden-xs">Задания будут создаваться автоматически по расписанию</span>
                    </td>
                    <td class="col-xs-8 col-sm-8">
                        <?php 
                        $saved_filters = $vibix_settings_config['saved_filters'] ?? [];
                        $has_filters = !empty($saved_filters);
                        ?>
                        <input type="hidden" name="settings_config[auto_create_enabled]" value="0">
                        <input type="checkbox" name="settings_config[auto_create_enabled]" value="1" 
                            <?= !empty($vibix_settings_config['auto_create_enabled']) && $has_filters ? 'checked' : '' ?>
                            <?= !$has_filters ? 'disabled' : '' ?>>
                     
                    </td>
                </tr>
                <tr>
                    <td class="col-xs-4 col-sm-4">
                        <h6 class="media-heading text-semibold">Время запуска</h6>
                        <span class="text-muted text-size-small hidden-xs">Укажите время в формате HH:MM (24 часа)</span>
                    </td>
                    <td class="col-xs-8 col-sm-8">
                        <?php 
                        $auto_create_time = $vibix_settings_config['auto_create_time'] ?? '03:00';
                        ?>
                        <input type="time" class="form-control" name="settings_config[auto_create_time]" 
                            value="<?= $auto_create_time ?>" style="width: auto; display: inline-block;"><br>
                        <span class="text-muted text-size-small">Пример: 03:00 - задания будут создаваться каждый день в 3 часа ночи</span>
                    </td>
                </tr>
                <tr>
                    <td class="col-xs-4 col-sm-4">
                        <h6 class="media-heading text-semibold">Тип задания</h6>
                        <span class="text-muted text-size-small hidden-xs">Какой тип задания создавать автоматически</span>
                    </td>
                    <td class="col-xs-8 col-sm-8">
                        <select name="settings_config[auto_create_type]" class="form-control" style="width: auto;">
                            <option value="CREATE" <?= ($vibix_settings_config['auto_create_type'] ?? 'CREATE_UPDATE') == 'CREATE' ? 'selected' : '' ?>>Добавить если нет</option>
                            <option value="CREATE_UPDATE" <?= ($vibix_settings_config['auto_create_type'] ?? 'CREATE_UPDATE') == 'CREATE_UPDATE' ? 'selected' : '' ?>>Добавить и обновить если есть</option>
                            <option value="ONLY_LINK_UPDATE" <?= ($vibix_settings_config['auto_create_type'] ?? 'CREATE_UPDATE') == 'ONLY_LINK_UPDATE' ? 'selected' : '' ?>>Проставить ссылку на плеер Vibix</option>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Настройки производительности -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h6 class="panel-title">Настройки производительности</h6>
        </div>
        <div class="panel-body">
            <span class="text-muted text-size-small">Эти настройки влияют на скорость импорта и нагрузку на сервер.</span>
        </div>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="col-xs-6 col-sm-6">
                        <h6 class="media-heading text-semibold">Максимальное количество записей в одном цикле</h6>
                        <span class="text-muted text-size-small hidden-xs">Количество записей, обрабатываемых за один запуск скрипта. Рекомендуется: 50-200</span>
                    </td>
                    <td class="col-xs-6 col-sm-6">
                        <input type="number" class="form-control" name="settings_config[cron_limit]" min="20" max="300" step="10" value="<?= empty($vibix_settings_config['cron_limit']) ? 100 : $vibix_settings_config['cron_limit'] ?>" />
                    </td>
                </tr>
                <tr>
                    <td class="col-xs-6 col-sm-6">
                        <h6 class="media-heading text-semibold">Задержка между записями (микросекунд)</h6>
                        <span class="text-muted text-size-small hidden-xs">Пауза между добавлением каждой записи. 1000000 = 1 секунда. Рекомендуется: 20000-500000</span>
                    </td>
                    <td class="col-xs-6 col-sm-6">
                        <input type="number" class="form-control" name="settings_config[cron_usleep]" min="20000" max="1000000" step="10000" value="<?= empty($vibix_settings_config['cron_usleep']) ? 200000 : $vibix_settings_config['cron_usleep'] ?>" />
                        <span class="text-muted text-size-small">Текущее значение: <?= empty($vibix_settings_config['cron_usleep']) ? 200000 : $vibix_settings_config['cron_usleep'] ?> мкс = <?= round((empty($vibix_settings_config['cron_usleep']) ? 200000 : $vibix_settings_config['cron_usleep']) / 1000000, 2) ?> сек</span>
                    </td>
                </tr>
                <tr>
                    <td class="col-xs-6 col-sm-6">
                        <h6 class="media-heading text-semibold">Интервал обновления прогресс бара в фоновых заданиях</h6>
                        <span class="text-muted text-size-small hidden-xs">Как часто будет обновляться статус задачи при ручном запуске. Рекомендуется: 5-50</span>
                    </td>
                    <td class="col-xs-6 col-sm-6">
                        <input type="number" class="form-control" name="settings_config[manual_interval]" min="1" max="50" step="1" value="<?= empty($vibix_settings_config['manual_interval']) ? 3 : $vibix_settings_config['manual_interval'] ?>" />
                        <span class="text-muted text-size-small">Внимание: слишком маленький интервал может увеличить нагрузку!</span>
                    </td>
                </tr>
                <tr>
                    <td class="col-xs-6 col-sm-6">
                        <h6 class="media-heading text-semibold">Частота обновления прогресса в базе</h6>
                        <span class="text-muted text-size-small hidden-xs">Через сколько записей сохранять прогресс выполнения задания в базу. Меньше значение = чаще сохранение, но больше нагрузка на БД</span>
                    </td>
                    <td class="col-xs-6 col-sm-6">
                        <input type="number" class="form-control" name="settings_config[progress_update_freq]" min="5" max="50" step="5" value="<?= empty($vibix_settings_config['progress_update_freq']) ? 10 : $vibix_settings_config['progress_update_freq'] ?>" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Шаблоны для SEO -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h6 class="panel-title">Шаблоны для SEO</h6>
        </div>
        <div class="panel-body">
            <span class="text-muted text-size-small">В этой секции можно настроить шаблоны для SEO. Допускается использование сразу нескольких тегов.</span>
            <br><br>
            <b>Список доступных тегов:</b><br><br>
            <b>{name}</b> - название<br>
            <b>{year}</b> - год выхода<br>
            <b>{kp_rating}</b> - рейтинг на кинопоиске<br>
            <b>{genre}</b> - жанр<br>
            <b>{catrgory}</b> - категория<br>
            <b>{country}</b> - страна<br>
            <b>{description}</b> - описание<br>
            <b>{duration}</b> - продолжительность в минутах<br>
            <b>{kp_id}</b> - id на кинопоиске<br>
            <b>{kp_votes}</b> - количество оценок на кинопоиске<br>
            <br>
            <b>{persons.actor}</b> - актеры<br>
            <b>{persons.director}</b> - режиссеры<br>
            <b>{persons.designer}</b> - художники<br>
            <b>{persons.writer}</b> - сценаристы<br>
            <b>{persons.operator}</b> - операторы<br>
            <b>{persons.editor}</b> - монтажеры<br>
            <b>{persons.producer}</b> - продюсеры<br>
            <b>{persons.composer}</b> - композиторы<br>
            <b>{persons.voice_actor}</b> - роли озвучивали<br>
        </div>
        <table class="table table-striped">
            <tbody>
                <?php foreach ([
                    ['Заголовок материала', 'tpl_title', '{name}'],
                    ['Краткое описание', 'tpl_short_story', '{description}'],
                    ['Полное описание', 'tpl_full_story', '{description}'],
                    ['Теги материала', 'tpl_tags', ''],
                    ['Meta Title', 'tpl_metatitle', '{name}'],
                    ['Meta Description', 'tpl_metadescription', ''],
                    ['Meta Keywords', 'tpl_metakeywords', ''],
                ] as $item) { ?>
                    <tr>
                        <td class="col-xs-6 col-sm-6">
                            <h6 class="media-heading text-semibold"><?= $item[0] ?>:</h6>
                        </td>
                        <td class="col-xs-6 col-sm-6">
                            <input type="text" class="form-control" name="settings_config[<?= $item[1] ?>]" placeholder="если оставить поле пустым, то по умолчанию будет использован тег: <?= !empty([$item[2]]) ? $item[2] :  ' ' ?>" value="<?= empty($vibix_settings_config[$item[1]]) ? $item[2] : $vibix_settings_config[$item[1]] ?>" />
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    
    <div class="panel-footer" style="position: sticky;bottom:0;">
        <input type="hidden" name="settings_config[new_links]" value="1">
        <input type="hidden" name="action" value="save_config">
        <input type="hidden" name="user_hash" value="<?= $dle_login_hash ?>">
        <input type="submit" class="btn bg-teal btn-sm btn-raised position-left legitRipple" value="Сохранить">
        <input type="reset" class="btn bg-slate-600 btn-sm btn-raised position-left legitRipple" value="Вернуть">
    </div>
</form>