<form action="" method="post" class="tab" id="mapping_cats" style="position: relative;">
    <div class="panel-body border-bottom">
        <div class="row">
            <div class="col-xs-3 col-sm-3">
                Ваши категории
            </div>
            <div class="col-xs-9 col-sm-9">
                Значения полей Vibix, при совпадении с которыми, статья будет добавлена в соответствующую категорию.
            </div>
        </div>
    </div>
    <table class="table table-striped">
        <tbody>
            <?php
            $res = getApi('/publisher/catalog/getFilters', []);
            $filters = $res['filters'];
            
            foreach ($cat_info as $row) {
                $options = "";
                foreach ($filters as $filter_key => $filter) {
                    $options .= '<option disabled value>' . mb_strtoupper(str_replace('Все ', '', $filter['des'])) . '</option>';
                    foreach ($filter['list'] as $item) {
                        $selected = "";
                        if (!empty($vibix_category_config[$row['id']]) and in_array($filter_key . '.' . $item['name'], $vibix_category_config[$row['id']]))
                            $selected = " selected";
                        $options .= '<option value="' . $filter_key . '.' . $item['name'] . '"' . $selected . '>&nbsp;&nbsp;&nbsp;' . $item['name'] . '</option>';
                    }
                }
                if (!empty($options)) { ?>
                    <tr>
                        <td class="col-xs-3 col-sm-3 white-line">
                            <h6 class="media-heading text-semibold"><?= $row['name'] ?></h6>
                        </td>
                        <td class="col-xs-9 col-sm-9 white-line">
                            <select multiple name="category_config[<?= $row['id'] ?>][]" style="width:100%;max-width:350px;" class="categoryselect"><?= $options ?></select>
                        </td>
                    </tr>
            <?php }
            } ?>
        </tbody>
    </table>

    <div class="panel-footer" style="position: sticky;bottom:0;">
        <input type="hidden" name="action" value="save_mapping_fields">
        <input type="hidden" name="user_hash" value="<?= $dle_login_hash ?>">
        <input type="submit" class="btn bg-teal btn-sm btn-raised position-left legitRipple" value="Сохранить">
        <input type="reset" class="btn bg-slate-600 btn-sm btn-raised position-left legitRipple" value="Вернуть">
    </div>
</form>