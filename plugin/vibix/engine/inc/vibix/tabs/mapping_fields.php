<form action="" method="post" class="tab" id="mapping_fields" style="position: relative;">
    <div class="panel-body border-bottom">
        <div class="row">
            <div class="col-xs-6 col-sm-6">
                Ваши дополнительные поля
            </div>
            <div class="col-xs-6 col-sm-6">
                Поля по базе Vibix
            </div>
        </div>
    </div>

    <table class="table table-striped">
        <tbody>
        <?php

        $tags_arr = array(
            "embed_code_new"          => "Ссылка на плеер Vibix",
            "kp_id"                   => "Кинопоиск ID",
            "imdb_id"                 => "IMDB ID",
            "preview"                 => "Постер",
            "genre"                   => "Жанры",
            "year"                    => "Дата релиза",
            "name"                    => "Название",
            "name_original"           => "Оригинальное название",
            "country"                 => "Страны",
            "description"             => "Описание",
            "quality"                 => "Качество",
            "duration"                => "Продолжительность",
            "voiceovers"              => "Озвучка",
            "preview_backdrop"        => "Широкоформатный постер",
            "persons.actor"           => "Актеры",
            "persons.designer"        => "Художники",
            "persons.editor"          => "Монтажеры",
            "persons.composer"        => "Композиторы",
            "persons.director"        => "Режиссеры",
            "persons.writer"          => "Сценаристы",
            "persons.operator"        => "Операторы",
            "persons.producer"        => "Продюсеры",
            "kp_rating"               => "Кинопоиск рейтинг",
            "imdb_rating"             => "IMDB рейтинг",
        );

        $xfields = vibix_xfieldsload();

        if (!empty($xfields)) {
            foreach ($xfields as $xfield_id => $xfield_data) {
                if (DLE_VERSION >= 19) {
                    $field_name = isset($xfield_data['name']) ? $xfield_data['name'] : $xfield_id;
                    $field_type = isset($xfield_data['type']) ? $xfield_data['type'] : 'text';
                } else {
                    $field_name = isset($xfield_data[1]) ? $xfield_data[1] : $xfield_id;
                    $field_type = isset($xfield_data[3]) ? $xfield_data[3] : 'text';
                }

                if (!in_array($field_type, ['text', 'textarea', 'htmljs', 'select'])) {
                    continue;
                }

                $options = "";
                foreach ($tags_arr as $key => $value) {
                    $selected = "";
                    if (isset($vibix_xfields_config[$xfield_id]) && $vibix_xfields_config[$xfield_id] == $key) {
                        $selected = " selected";
                    }
                    $options .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
                }
                ?>
                <tr>
                    <td class="col-xs-6 col-sm-6 white-line">
                        <h6 class="media-heading text-semibold"><?= htmlspecialchars($field_name) ?></h6>
                        <span class="text-muted text-size-small hidden-xs">Поле в шаблоне [xfvalue_<?= htmlspecialchars($xfield_id) ?>]</span>
                    </td>
                    <td class="col-xs-6 col-sm-6 white-line">
                        <select name="xfields_config[<?= htmlspecialchars($xfield_id) ?>]" style="width:100%;max-width:350px;" class="uniform">
                            <option value=" ">Нет</option>
                            <?= $options ?>
                        </select>
                    </td>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="2" class="text-center">
                    <div class="alert alert-warning">Дополнительные поля не найдены. Создайте хотя бы одно поле в <b>Управление пользователями → Дополнительные поля</b>.</div>
                </td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>

    <div class="panel-footer" style="position: sticky;bottom:0;">
        <input type="hidden" name="action" value="save_mapping_fields">
        <input type="hidden" name="user_hash" value="<?= $dle_login_hash ?>">
        <input type="submit" class="btn bg-teal btn-sm btn-raised position-left legitRipple" value="Сохранить">
        <input type="reset" class="btn bg-slate-600 btn-sm btn-raised position-left legitRipple" value="Вернуть">
    </div>
</form>