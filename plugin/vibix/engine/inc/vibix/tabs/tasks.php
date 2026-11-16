<style>
    .form-control.vibix_basket {
        width: 20px;
    }

    .req_ask .btn {
        padding: 0;
    }

    .panel-footer.panel-custom {
        background: LightSlateGrey;
    }

    .delete-all-btn {
        margin-left: 15px;
    }
</style>

<div class="tab" id="mass_action" style="position: relative;">
    <div class="panel-body">
        <div class="alert alert-warning alert-styled-left alert-arrow-left alert-component">
            Для работы фоновых заданий настройте планировщик заданий <b>Cron</b> на исполнение скрипта /cron_vibix.php, он находится в корневой директории вашего сайта.
            Возможно вам поможет инструкция <a target="_blank" href="https://help.reg.ru/support/hosting/php-asp-net-i-skripty/planirovshchik-cron#0">REG RU Планировщик CRON</a>.<br>
            Если знаний и опыта не достаточно так же рекомендуем написать тикет в тех поддержку вашего хостинга с текстом: <i>Просьба помочь настроить планировщик крон на ежеминутное исполнение для файла /cron_vibix.php</i>
        </div>
    </div>

    <div class="panel-body border-bottom">
        Активные задания

        <div style="float: right" id="page_cron">
            <span>
                <input class="switch" type="checkbox" value="1" />
                Запустить задания в ручную
            </span>
            <button id="delete_all_tasks" class="btn btn-primary btn-sm delete-all-btn">
                <i class="fa fa-trash"></i> Удалить все
            </button>

        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-xs">
            <thead>
            <tr>
                <th>Создано</th>
                <th>Автор</th>
                <th>Тип задания</th>
                <th>Фильтр</th>
                <th>Всего</th>
                <th style="width: 150px">Прогресс</th>
                <th>Состояние</th>
                <th></th>
            </tr>
            </thead>
            <tbody class="vibix_task_list"></tbody>
        </table>
    </div>

    <script>
        window.cronRequestInProgress = false;
        var manualInterval = <?= isset($vibix_settings_config['manual_interval']) ? (int)$vibix_settings_config['manual_interval'] : 3 ?>;

        $(document).ready(function() {
            $('#vibix .spinner-border').show();
            reloadActiveTasks(function() {
                $('#vibix .spinner-border').hide();
            });

            var page_cron_interval = setInterval(function() {
                if ($("#page_cron input").prop('checked')) {
                    if (window.cronRequestInProgress) {
                        return;
                    }

                    window.cronRequestInProgress = true;

                    processTask(function(data) {
                        window.cronRequestInProgress = false;

                        if (data.task_id > 0) {
                            var tr_task = $('.vibix_task_list').find(`[data-id="${data.task_id}"]`);
                            $(tr_task).find('.progress_value').text(`${data.progress}%`);
                            $('.progress-bar', tr_task).css('width', data.progress + '%').attr('aria-valuenow', data.progress);
                            if (data.progress == 100) {
                                $(tr_task).find('.state').html(`Завершено`);
                                $("#page_cron .switchery").prop('checked', false).trigger("click");
                            }
                        } else {
                            $("#page_cron .switchery").prop('checked', false).trigger("click");
                        }
                    });
                }
            }, manualInterval * 1000);

            function processTask(callback = null) {
                $.ajax({
                    type: "POST",
                    crossDomain: true,
                    url: '/cron_vibix.php',
                    data: {},
                    dataType: "json",
                    timeout: 60000,
                    success: function(data) {
                        if (callback)
                            callback(data);
                    },
                    error: function(errMsg) {
                        window.cronRequestInProgress = false;
                        console.error('Cron error:', errMsg);
                    }
                });
            }

            function compileFilterTask(row) {
                var res = '';

                if (row.filter && typeof row.filter === 'object') {
                    $.each(row.filter, function(key, list_items) {
                        if (key == 'id') {
                            if (Array.isArray(list_items)) {
                                res += 'Выбрано: ' + list_items.length + ' шт.';
                            } else {
                                res += 'ID: ' + list_items;
                            }
                        } else {
                            if (Array.isArray(list_items)) {
                                res += key + ': ' + list_items.length + ' шт.';
                            } else {
                                res += key + ': ' + list_items;
                            }
                        }
                    });
                }

                if (res === '') {
                    res = 'Все материалы';
                }

                return res;
            }

            function reloadActiveTasks(callback = null) {
                $.ajax({
                    type: "POST",
                    crossDomain: true,
                    url: '<?= vibix_ajax_url(); ?>' + 'getTasks',
                    data: {},
                    dataType: "json",
                    success: function(data) {
                        if (callback)
                            callback(data);

                        $('.vibix_task_list').html('');
                        $.each(data['list'], function(key, row) {
                            $('.vibix_task_list').append(`
                                <tr id="${row['id']}" data-id="${row['id']}">
                                    <td>${row['date']}</td>
                                    <td>${row['user_name']}</td>
                                    <td>${row['type_label']}</td>
                                    <td width='300'>${compileFilterTask(row)}</td>
                                    <td>${row['total']}</td>
                                    <td>
                                        <span class="progress_value">${row['progress']}%</span>
                                        <div class="progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar overflow-visible progress-blue" style="width: ${row['progress']}%"></div>
                                        </div>
                                    </td>
                                    <td class="state">${row['state_label']}</td>
                                    <td><span data-id="${row['id']}" class="delete"><button id="delete_button_${row['id']}" type="button" class="btn btn-sm btn-block delete_task_button">Удалить</button></span></td>
                                </tr>
                            `);
                        });
                    },
                    error: function(errMsg) {
                        $('#vibix .spinner-border').hide();
                        console.error('Reload tasks error:', errMsg);
                    }
                });
            }

            // Удаление одного задания
            $('body').on('click', '.delete', function(event) {
                event.preventDefault();
                var id = $(this).data('id');
                $.ajax({
                    type: "POST",
                    crossDomain: true,
                    url: '/<?= $config['admin_path'] ?>?mod=vibix&action_ajax=delete_task',
                    data: {
                        id: id
                    },
                    success: function(res) {
                        $('#' + id).fadeOut();
                    },
                    error: function(errMsg) {
                        console.error('Delete error:', errMsg);
                    }
                });
            });

            // Удаление всех заданий
            $('#delete_all_tasks').on('click', function(event) {
                event.preventDefault();

                if (!confirm('Вы уверены, что хотите удалить ВСЕ задания?')) {
                    return;
                }

                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Удаление...');

                $.ajax({
                    type: "POST",
                    url: '/<?= $config['admin_path'] ?>?mod=vibix&action_ajax=delete_all_tasks',
                    data: {},
                    dataType: "json",
                    success: function(res) {
                        if (res.success) {
                            $('.vibix_task_list').html('');
                            btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Удалить все');
                        } else {
                            alert('Ошибка при удалении');
                            btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Удалить все');
                        }
                    },
                    error: function() {
                        alert('Ошибка при удалении заданий');
                        btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Удалить все');
                    }
                });
            });
        });
    </script>
</div>