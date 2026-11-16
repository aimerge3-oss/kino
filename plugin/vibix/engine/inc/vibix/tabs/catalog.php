<? require_once(ENGINE_DIR . '/inc/vibix/functions.php'); ?>
<style>
    .form-control.vibix_basket {
        width: 20px;

    }

    .req_ask .btn {
        padding: 0;
    }

    .centered-text {
        display: block;
        text-align: center;
    }
</style>
<div class="panel-body">

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" integrity="sha512-nMNlpuaDPrqlEls3IX/Q56H36qvBASwb3ipuo3MxeWbsQB1881ox0cRv7UPTgBlriqoynt35KjEwgGUeUXIPnw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        svg.icon {
            display: none
        }
    </style>
    <div id="content"></div>

    <div class="modal fade" id="popupVideo" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title fs-5"></h3>
                    <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="hold-box">

                        <div class="row">
                            <div class="videoplayer" style="height: 500px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $('body').on('click', '[data-fancybox="ajax"].danger', function(event) {
                    event.preventDefault();
                    let url = $(this).data('href');
                    let name = $(this).closest('tr').find('td:eq(5)').html();
                    $('#popupVideo .modal-title').html(name);
                    url = `<ins ${url}></ins>`;
                    $('.videoplayer').html(url);
                    $('#popupVideo').modal('show');
                });
                $("#popupVideo").on("hidden.bs.modal", function() {
                    $('.videoplayer').empty();
                });
            });
        </script>
    </div>
    <div class="modal fade" id="popupInfo" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title fs-5"></h3>
                    <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
        <script>
            localStorage.setItem('token', '<?= $vibix_settings_config['api_token'] ?>');
            $(document).ready(function() {
                $('body').on('click', '[data-tippy-content="Информация"]', function(event) {
                    event.preventDefault();
                    var url = $(this).data('href');
                    $('#popupInfo .modal-title').text($(this).closest('tr').find('td:eq(5)').text());
                    $.ajax({
                        beforeSend: function(xhr, settings) {
                            xhr.setRequestHeader('Authorization', 'Bearer ' + localStorage.getItem('token'))
                        },
                        type: "GET",
                        crossDomain: true,
                        url: url,
                        data: null,
                        success: function(res) {
                            $('#popupInfo .modal-body').html(res);
                            $('#popupInfo').modal('show');
                        },
                        error: function(errMsg) {

                        }
                    });
                })
            });
        </script>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.full.min.js" integrity="sha512-RtZU3AyMVArmHLiW0suEZ9McadTdegwbgtiQl5Qqo9kunkVg1ofwueXD8/8wv3Af8jkME3DDe3yLfR8HSJfT2g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        function locationHashChanged() {
            if (location.hash === "#/" || location.hash === "") {
                PAGES.catalog();
                return;
            }
        }

        var FORM = {
            api: '/',
            form_selector: '',
            submit_selector: '',
            save_method: '',
            get_method: '',
            callback_after_save: function(data) {},
            init: function(api_url, form_selector, get_method, save_method = false, submit_selector = false, callback_after_save = false) {
                if (form_selector && api_url) {
                    this.api = api_url;
                    this.form_selector = form_selector;
                    this.submit_selector = submit_selector;
                    this.get_method = get_method;
                    this.save_method = save_method;
                    this.callback_after_save = callback_after_save;

                    if (this.get_method)
                        this.getFields();

                    if (submit_selector)
                        $(`${FORM.form_selector} ${submit_selector}`).click(function() {
                            FORM.req(FORM.save_method, FORM.getForm(), function(data, isOk = true) {
                                FORM.callback_after_save(data, isOk);
                            });
                        });

                }
            },
            getFields: function() {
                this.req(FORM.get_method, false, function(res) {
                    if (res['data']) {
                        $.each(res['data'], function(field, value) {
                            $(`${FORM.form_selector} [name="${field}"]`).val(value);
                        });
                    }
                });
            },
            getForm: function() {
                var data = {};
                $(`${FORM.form_selector} [name]`).each(function() {
                    if ($(this).attr('name'))
                        data[$(this).attr('name')] = $(this).val();
                });
                return data;
            },
            req: function(method, data, callback = null) {
                $.ajax({
                    beforeSend: function(xhr, settings) {
                        xhr.setRequestHeader('Authorization', 'Bearer ' + localStorage.getItem('token'))
                    },
                    type: data === false ? "GET" : "POST",
                    crossDomain: true,
                    url: FORM.api + method,
                    data: data === false ? null : JSON.stringify(data),
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    success: function(data) {
                        if (callback !== null)
                            callback(data)
                    },
                    error: function(errMsg) {
                        if (errMsg['responseJSON'] && errMsg['responseJSON']['message'])
                            callback(errMsg['responseJSON'], false);
                        else
                            callback({
                                "message": "Что то пошло не так ;("
                            }, false);
                    }
                });
            },
        };

        var GRID = {
            api: '/',
            table_selector: '',
            filter_selector: '',
            table: false,
            table_search: true,
            filters: {},
            init: function(api_url, table_selector, filter_selector, table_search = true) {
                if (table_selector && api_url && filter_selector) {
                    this.api = api_url;
                    this.table_selector = table_selector;
                    this.table_search = table_search;
                    this.filter_selector = filter_selector;
                    GRID.initFilters(GRID.initTable.bind(this));
                }
            },
            initFilters: function(onInit) {
                this.req('getFilters', {}, this.setFilterList.bind(this, onInit))
            },
            getFilter: function(filterClass = '') {
                var form = {
                    'filter': {},
                    'group': {}
                };
                $(GRID.filter_selector + ' .form-control ' + filterClass).each(function() {
                    if ($(this).val()) {
                        var parts_name = $(this).attr('name').split('.');
                        if (parts_name.length === 2)
                            form[parts_name[0]][parts_name[1]] = $(this).val();
                        if (parts_name.length === 3)
                            form[parts_name[0]][parts_name[1]][parts_name[2]] = $(this).val();
                    }
                });
                return form;
            },
            createFilterItem: function(container, type, field, filter) {
                var dataSets = '';
                if (filter['ajax-url'])
                    dataSets = ` data-ajax-url="${filter['ajax-url']}"`;
                var field_name = `${type}.${field}`;
                if (filter['date'])
                    container.append(`<div class="col-md-${filter['size']} col-lg-${filter['size'] - 1}" style="float: left">
            <input value="${filter['date']}" type="date" placeholder="${filter['des']}"  name="${field_name}" class="form-control form-control-sm float-left" style="min-width: 100px"></div>`)
                else {
                    var multiple = '';
                    if (filter['multiple'])
                        multiple = 'multiple';
                    container.append(`<div class="col-md-${filter['size']} col-lg-${filter['size'] - 1}" style="float: left; margin-bottom: 5px">
                                        <select ${dataSets}  name="${field_name}" ${multiple} data-placeholder="${filter['des']}" data-minlength="0" class="use_select2 form-control form-control-sm float-left" style="width: 100%"><option value="">${filter['des']} - Все</option></select></div>`)
                    if (filter['list'])
                        $.each(filter['list'], function(i, item) {
                            var count = '';
                            if (item['count'])
                                count = ` (${item['count']})`;

                            $('[name="' + field_name + '"]', container).append(`<option value="${item['value']}">${item['name']}${count}</option>`);
                        })
                    if (localStorage.getItem("_" + field_name)) {
                        if ($('[name="' + field_name + ']"]', container).prop('multiple'))
                            $.each(localStorage.getItem("_" + field_name).split(","), function(i, e) {
                                $(`[name="${field_name}"] option[value="${e}"]`, container).prop("selected", true);
                            });
                        else
                            $('[name="' + field_name + '"]', container).val(localStorage.getItem("_" + field_name))
                    } else if (filter['default']) {
                        $.each(filter['default'].split(","), function(i, e) {
                            $(`[name="${field_name}"] option[value="${e}"]`, container).prop("selected", true);
                        });
                    }
                    initSelect2('[name="' + field_name + '"]');
                }
                $('[name="' + field_name + '"]', container).change(function() {
                    localStorage.setItem("_" + field_name, $(this).val());
                    if (GRID.table)
                        GRID.table.ajax.url(GRID.api + "data").draw()
                });
                //$('[name="filter[' + field + ']"]', statistics_filter).select2({placeholder: $('[name="filter[' + field + ']"]', statistics_filter).attr('placeholder'), allowClear: true})
            },
            setFilterList: function(onInit, data) {
                $.each(data['group'], function(field, filter) {
                    GRID.createFilterItem($(GRID.filter_selector), 'group', field, filter);
                });
                this.filters = data['filters'];
                $.each(data['filters'], function(field, filter) {
                    GRID.createFilterItem($(GRID.filter_selector), 'filter', field, filter);
                });
                onInit();
            },
            req: function(method, data, callback = null) {
                $.ajax({
                    beforeSend: function(xhr, settings) {
                        xhr.setRequestHeader('Authorization', 'Bearer ' + localStorage.getItem('token'))
                    },
                    type: "POST",
                    crossDomain: true,
                    url: GRID.api + method,
                    data: JSON.stringify(data),
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    success: function(data) {
                        if (callback !== null)
                            callback(data)
                    },
                    error: function(errMsg) {
                        //window.location.href = '/#logout/';
                    }
                });
            },
            initTable: function() {
                // this.req('columnsTable', {}, function (columns) {
                var

                    columns = [{
                        "data": "",
                        "width": '25px',
                        "render": function(data, type, row) {
                            if (!row['embed_code_new'])
                                return ``;

                            var vibix_basket = localStorage.getItem('vibix_basket');

                            if (!vibix_basket)
                                vibix_basket = [];
                            else
                                vibix_basket = JSON.parse(vibix_basket);
                            if (vibix_basket.indexOf(row['id'].toString()) > -1)
                                return `<input checked class="form-control vibix_basket" type="checkbox" value="${row['id']}">`;
                            else
                                return `<input class="form-control vibix_basket" type="checkbox" value="${row['id']}">`;
                        },
                        "title": "",
                        "sorting": false
                    },
                        {
                            "data": "kp_id",
                            "width": '70px',
                            "title": "КП ID",
                            "sorting": false
                        },
                        // логика показа иконок в гриде
                        {
                            "data": "preview",
                            "width": '25px',
                            "title": "",
                            "sorting": false,
                            "render": function(data, type, row) {
                                if (!row['embed_code_new'] && row['uploaded_at'])
                                    return `<i class="fa fa-clock-o text-muted"></i>`;
                                if (!row['embed_code_new'] && !row['uploaded_at'])
                                    return ``;
                                if (row['embed_code_new'] && row['uploaded_at']) {
                                    function escapeHtml(str) {
                                        if (!str) return '';
                                        return str
                                            .replace(/&/g, '&amp;')
                                            .replace(/</g, '&lt;')
                                            .replace(/>/g, '&gt;')
                                            .replace(/"/g, '&quot;')
                                            .replace(/'/g, '&#39;');
                                    }

                                    var embed_code_new = escapeHtml(row['embed_code_new']);
                                    return `<a data-href="${embed_code_new}" class="danger" data-fancybox="ajax" data-tippy-content="Предпросмотр"><i class="fa fa-video-camera"></i></a>`;
                                }
                            },
                        },
                        // логика колонки "Добавлен"
                        {
                            "data": "uploaded_at",
                            "title": "Добавлен",
                            "width": "90px",
                            "render": function(data, type, row) {
                                if (!row['embed_code_new'] && row['uploaded_at'])
                                    return `<span style="font-style: italic" class="text-muted"> Загружается</span>`;

                                if (!row['embed_code_new'] && !row['uploaded_at'] && !row['proposal_status'])
                                    return `<span data-id="${row['id']}" class="req_ask"><button id="req_ask_button_${row['id']}" type="button" class="btn btn-info btn-sm btn-block">Запросить</button></span>`;

                                if (row['embed_code_new'] && row['uploaded_at'])
                                    return row['uploaded_at'];

                                if (!row['embed_code_new'] && !row['uploaded_at'] && row['proposal_status'] == 'pending')
                                    return `<span style="font-style: italic" class="text-muted"> Запрос сделан</span>`;

                                if (!row['embed_code_new'] && !row['uploaded_at'] && row['proposal_status'] == 'rejected')
                                    return `<span style="font-style: italic" class="text-muted"> Запрос отклонен</span>`;
                            },
                        },
                        {
                            "data": "",
                            "title": "",
                            "sorting": false,
                            "render": function(data, type, row) {
                                if (row['exists'])
                                    return '<span title="Есть на сайте" class="text-success fa fa-check-circle"></span> ';
                                if (!row['exists'])
                                    return '';
                            },
                        },
                        {
                            "data": "name",
                            "title": "Название",
                            "sorting": false,
                            "render": function(data, type, row) {
                                if (row.name_eng) {
                                    alt_name = row.name_eng
                                } else {
                                    alt_name = " "
                                }
                                if (row.name_rus) {
                                    alt_name = row.name_rus
                                }
                                if (row.name_original) {
                                    alt_name = row.name_original
                                }
                                return `${row.name} <br><span style="font-size:12px;">${alt_name}</span>`
                            },
                        },
                        {
                            "data": "genre",
                            "title": "Жанр",
                            "sorting": false
                        },
                        {
                            "data": "year",
                            "title": "Год"
                        },


                        {
                            "data": "country",
                            "title": "Страна",
                            "width": '120px',
                            "sorting": false,
                            "render": function(data, type, row) {
                                countries = row.country;
                                if (countries) {
                                    result = countries.replaceAll(",", "<br>");
                                    return result;
                                } else {
                                    return " ";
                                }
                            }
                        },
                        {
                            "data": "imdb_rating",
                            "width": '25px',
                            "title": "IMDB",

                        },
                        {
                            "data": "kp_rating",
                            "width": '25px',
                            "title": "КП"
                        },
                        {
                            "data": "kp_votes",
                            "width": '70px',
                            "title": "КП оценок"
                        },
                    ];
                if (GRID.table !== false) {
                    GRID.table.destroy();
                    $(GRID.table_selector).empty();
                    GRID.table = false;
                }
                GRID.table = $(GRID.table_selector).DataTable({
                    language: ru,
                    "bInfo": false,
                    ajax: {
                        beforeSend: function(xhr, settings) {
                            xhr.setRequestHeader('Authorization', 'Bearer ' + localStorage.getItem('token'))
                        },
                        type: 'POST',
                        url: GRID.api + "data",
                        data: function(data) {
                            var form = GRID.getFilter();
                            data['filter'] = form['filter'];
                            data['group'] = form['group'];
                            return data;
                        }
                    },
                    searchDelay: 1000,
                    paging: true,
                    searching: GRID.table_search,
                    ordering: true,
                    autoWidth: false,
                    order: [
                        [3, 'desc']
                    ],
                    select: true,
                    layout: {
                        topStart: 'info',
                        bottom: 'paging',
                        bottomStart: null,
                        bottomEnd: null
                    },
                    lengthMenu: [
                        [25, 50],
                        [25, 50]
                    ],
                    //data: data.rows,
                    processing: true,
                    serverSide: true,
                    columns: columns
                });
                //});
            },
        }

        var PAGES = {
            catalog: function() {
                $('.main').addClass('aside_show');
                $('#content').html(`<form id="grid_filter" class="row" style="padding: 10px;"></form><table id="DataTable" class="table table-striped"></table>`);
                GRID.init('<?= vibix_ajax_url() . 'redirect_' ?>', '#DataTable', '#grid_filter');
            },
        };

        $(document).ready(function() {
            locationHashChanged();
            window.onhashchange = locationHashChanged;

            $('body').on('change', '.vibix_basket', function() {
                var vibix_basket = localStorage.getItem('vibix_basket');
                if (!vibix_basket) vibix_basket = [];
                else vibix_basket = JSON.parse(vibix_basket);
                if ($(this).is(':checked') && vibix_basket.indexOf($(this).val()) === -1)
                    vibix_basket.push($(this).val())
                else {
                    if (vibix_basket.indexOf($(this).val()) > -1)
                        vibix_basket.splice(vibix_basket.indexOf($(this).val()), 1);
                }
                localStorage.setItem('vibix_basket', JSON.stringify(vibix_basket));
                $('.vibix_basket_count').text(vibix_basket.length)
            });

            $('body').on('click', '[data-action]', function(event) {
                event.preventDefault();
                var action = $(this).data('action');
                if (action == 'reset_basket') {
                    localStorage.setItem('vibix_basket', '');
                    $('.vibix_basket_count').text('0');
                    $('.vibix_basket').prop('checked', false);

                    return;
                } else if (action == 'CREATE' || action == 'CREATE_UPDATE' || action == 'ONLY_LINK_UPDATE') {
                    vibixLoading(true, 'Создание задания...');
                    var vibix_basket = localStorage.getItem('vibix_basket');
                    if (!vibix_basket) vibix_basket = [];
                    else vibix_basket = JSON.parse(vibix_basket);

                    $('#popupInfo .modal-title').text('');
                    $('#popupInfo .modal-title').text('');

                    $.ajax({
                        type: "post",
                        crossDomain: true,
                        url: '<?= vibix_ajax_url(); ?>' + action,
                        dataType: 'json',
                        data: {
                            "add_type": $('[name="add_type"]').val(),
                            "vibix_basket": vibix_basket,
                            "filter": GRID.getFilter()['filter']
                        },
                        success: function (res) {
                            vibixLoading(0);

                            if (res && res.EMPTY) {
                                $('#popupInfo .modal-title').html('Ошибка создания задания');
                                $('#popupInfo .modal-body').html(`<div class="alert alert-danger alert-styled-left alert-arrow-left alert-component">
                                    Внимание! Не выбрано ни одной записи. Если вы используете добавление с помощью фильтра, убедитесь, что соответствующая опция выбрана внизу.</div>`);
                                $('#popupInfo').modal('show');
                                return;
                            }

                            if (res && res.status && res.status != 200) {
                                $('#popupInfo .modal-title').html('Ошибка создания задания');
                                $('#popupInfo .modal-body').html(`<div class="alert alert-danger alert-styled-left alert-arrow-left alert-component">
                                    ${res.message || 'Ошибка создания задания'}</div>`);
                                $('#popupInfo').modal('show');
                                return;
                            }

                            localStorage.setItem('vibix_basket', '');
                            $('.vibix_basket_count').text('0');
                            $('.vibix_basket').prop('checked', false);
                            $('#popupInfo .modal-title').html('Задание успешно создано');
                            $('#popupInfo .modal-body').html(`<div class="alert alert-info alert-styled-left alert-arrow-left alert-component">
                                Внимание! Задание создано и запущено. Список заданий и их выполнение вы можете контролировать в разделе <b>Фоновые задачи</b>.</div>`);
                            $('#popupInfo').modal('show');

                            if (typeof reloadActiveTasks === 'function') {
                                reloadActiveTasks();
                            }
                        },
                        error: function (errMsg) {
                            vibixLoading(0);
                            $('#popupInfo .modal-title').html('Ошибка создания задания');
                            $('#popupInfo .modal-body').html(`<div class="alert alert-danger alert-styled-left alert-arrow-left alert-component">
                                Внимание! Что то пошло не так ;(.</div>`);
                            $('#popupInfo').modal('show');
                        }
                    });
                } else if (action == 'SAVE_FILTER') {
                    var filters = GRID.getFilter()['filter'];

                    $.ajax({
                        type: "post",
                        url: '<?= vibix_ajax_url(); ?>' + action,
                        data: {
                            "filter": filters
                        },
                        success: function(res) {
                            if (res.ok) {
                                $('#popupInfo .modal-title').html('Фильтры сохранены');
                                $('#popupInfo .modal-body').html(`<div class="alert alert-success alert-styled-left alert-arrow-left alert-component">
                                     Фильтры успешно сохранены
                                </div>`);
                                $('#popupInfo').modal('show');
                            } else {
                                $('#popupInfo .modal-title').html('Ошибка');
                                $('#popupInfo .modal-body').html(`<div class="alert alert-danger alert-styled-left alert-arrow-left alert-component">
                                    ${res.error || 'Ошибка сохранения фильтров'}
                                </div>`);
                                $('#popupInfo').modal('show');
                            }
                        }
                    });
                }

                else if (action == 'DB_LINK_UPDATE') {
                   
                    vibixLoading(true, 'Создание задания...')


                    $.ajax({
                        type: "POST",
                        url: '<?= vibix_ajax_url(); ?>' + action,
                        data: { action_ajax: 'DB_LINK_UPDATE' },
                        dataType: 'json',
                        success: function (res) {
                            vibixLoading(false);
                            if (res.status == 200) {
                                if (typeof reloadActiveTasks === 'function') {
                                    reloadActiveTasks();
                                }
                                $('#popupInfo .modal-body').html(`<div class="alert alert-info alert-styled-left alert-arrow-left alert-component">
                                Внимание! Задание создано и запущено. Список заданий и их выполнение вы можете контролировать в разделе <b>Фоновые задачи</b>.</div>`);
                                $('#popupInfo .modal-title').html(`Задание успешно создано`);
                                $('#popupInfo').modal('show');
                            } else {
                                $('#popupInfo .modal-title').html('Ошибка');
                                $('#popupInfo .modal-body').html(`<div class="alert alert-danger alert-styled-left alert-arrow-left alert-component">
                                    ${res.message || 'Ошибка создания задания'}
                                </div>`);
                                $('#popupInfo').modal('show');
                            }
                        },
                        error: function () {
                            vibixLoading(false);
                            alert('Ошибка запроса');
                        }
                    });
                }
            });

            var vibix_basket = localStorage.getItem('vibix_basket');
            if (!vibix_basket) vibix_basket = [];
            else vibix_basket = JSON.parse(vibix_basket);
            $('.vibix_basket_count').text(vibix_basket.length)
        });
    </script>
</div>
<div class="panel-footer" style="position: sticky;bottom:0;">
    <button data-action="reset_basket" class="btn bg-slate-600 btn-sm btn-raised position-left"> Выбрано
        <b class="vibix_basket_count"></b> Сбросить выбор.
    </button>
    <div class="pull-right">
        <div class="btn-group bootstrap-select uniform position-left">
            <select name="add_type" class="uniform position-left" tabindex="-98">
                <option value="selected"><b>Только с выбранными</b></option>
                <option value="filter">Со всей выборкой по фильтру</option>
            </select>
        </div>
        <button data-action="SAVE_FILTER" class="btn bg-teal btn-sm btn-raised position-left ms-2">
            <i class="fa fa-save"></i> Сохранить фильтр для авто-обновления
        </button>
        <button data-action="CREATE" class="btn bg-danger btn-sm btn-raised position-left ms-2">Добавить если нет</button>
        <button data-action="CREATE_UPDATE" class="btn bg-danger btn-sm btn-raised position-left ms-2">Добавить и обновить если есть</button>
        <button data-action="ONLY_LINK_UPDATE" class="btn bg-danger btn-sm btn-raised position-left">Проставить ссылку на плеер Vibix</button>
        <button data-action="DB_LINK_UPDATE" class="btn bg-teal btn-sm btn-raised position-left tip" data-original-title="Проставить код плееа Vibix по всем видео в базе. ">
            <i class="fa fa-server"></i>
        </button>
    </div>
    
</div>
<!-- модалька для кнопки запроса видео-->

<div class="modal fade" id="popupReqAdd" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-5"></h3>
                <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            $('body').on('click', '.req_ask', function(event) {
                event.preventDefault();

                var id = $(this).data('id');
                $('#popupReqAdd .modal-title').text('Запрос на добавление материала: ' + $(this).closest('tr').find('td:eq(5)').html());
                $.ajax({
                    beforeSend: function(xhr, settings) {
                        xhr.setRequestHeader('Authorization', 'Bearer ' + localStorage.getItem('token'))
                    },
                    type: "POST",
                    crossDomain: true,
                    url: '<?= $vibix_config['api_server'] ?>/video-request',
                    data: {
                        video_id: id
                    },
                    success: function(res) {
                        $('#popupReqAdd .modal-body').html(res.message);
                        $('#popupReqAdd').modal('show');

                        // Замена кнопки запроса на кнопку выполнения

                        $('#req_ask_button_' + id).replaceWith('<span style="font-style: italic" class="text-muted"> Запрос сделан</span>');
                    },
                    error: function(errMsg) {

                    }
                });
            })
        });
    </script>
</div>