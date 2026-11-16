<div class="tab" id="help" style="position: relative;">
    <div class="panel-body">

        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" integrity="sha512-nMNlpuaDPrqlEls3IX/Q56H36qvBASwb3ipuo3MxeWbsQB1881ox0cRv7UPTgBlriqoynt35KjEwgGUeUXIPnw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

        <style>
            svg.icon {
                display: none
            }
        </style>
        <div id="content_request"></div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.full.min.js" integrity="sha512-RtZU3AyMVArmHLiW0suEZ9McadTdegwbgtiQl5Qqo9kunkVg1ofwueXD8/8wv3Af8jkME3DDe3yLfR8HSJfT2g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

        <script>
            //Обёртка для select2 к фильтрам грида

            var GRID_ = {
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
                        this.initFilters(this.initTable.bind(this));
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
                    $(this.filter_selector + ' .form-control ' + filterClass).each(function() {
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
                    var ctx = this;
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
                        if (ctx.table)
                            ctx.table.ajax.url(GRID.api + "data").draw()
                    });
                    //$('[name="filter[' + field + ']"]', statistics_filter).select2({placeholder: $('[name="filter[' + field + ']"]', statistics_filter).attr('placeholder'), allowClear: true})
                },
                setFilterList: function(onInit, data) {
                    var ctx = this;
                    $.each(data['group'], function(field, filter) {
                        ctx.createFilterItem($(ctx.filter_selector), 'group', field, filter);
                    });
                    this.filters = data['filters'];
                    $.each(data['filters'], function(field, filter) {
                        ctx.createFilterItem($(ctx.filter_selector), 'filter', field, filter);
                    });
                    onInit();
                },
                req: function(method, data, callback = null) {
                    var ctx = this;
                    $.ajax({
                        beforeSend: function(xhr, settings) {
                            xhr.setRequestHeader('Authorization', 'Bearer ' + localStorage.getItem('token'))
                        },
                        type: "POST",
                        crossDomain: true,
                        url: ctx.api + method,
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
                    var ctx = this;
                    this.req('columnsTable', {}, function(columns) {

                        if (ctx.table !== false) {
                            ctx.table.destroy();
                            $(ctx.table_selector).empty();
                            ctx.table = false;
                        }
                        ctx.table = $(ctx.table_selector).DataTable({

                            language: ru,
                            "bInfo": false,
                            ajax: {
                                beforeSend: function(xhr, settings) {
                                    xhr.setRequestHeader('Authorization', 'Bearer ' + localStorage.getItem('token'))
                                },
                                type: 'POST',
                                url: ctx.api + "data",
                                data: function(data) {
                                    var form = ctx.getFilter();
                                    data['filter'] = form['filter'];
                                    data['group'] = form['group'];
                                    return data;
                                }
                            },
                            paging: true,
                            searching: ctx.table_search,
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
                                [50, 100],
                                [50, 100]
                            ],
                            //data: data.rows,
                            processing: true,
                            serverSide: true,
                            columns: columns
                        });
                    });
                },
            }
            var PAGES_REQUEST = {
                catalog_request: function() {
                    $('#content_request').html(`<form id="grid_filter_request" class="row" style="padding: 10px;"></form><table id="DataTable_request" class="table table-striped"></table>`);
                    GRID_.init('<?= vibix_ajax_url() . 'redirect_requests_'; ?>', '#DataTable_request', '#grid_filter_request');
                },
            };
            PAGES_REQUEST.catalog_request();
        </script>
    </div>
</div>
