var globals = {
    csrf: $('meta[name=csrf-token]').attr('content')
};

var box = function(id) {
    $("#tanbox").fadeIn(200);
    var b = $("#" + id);
    var bh = b.height();
    var wh = $(window).height();
    if (bh < wh) {
        var m = (wh - bh) / 2;
        b.css("margin-top", 0).show();
    } else {
        b.css("margin-top", 0).show();
    }
};
var close_qx = function() {
    $("#tanbox").hide();
    $("#sbox").hide();
};
/**
* 行为管理
* @param module_id
* @param module_name
*/
var shuju = function(module_id, module_name) {
    $('#feature_action').text(module_name+'——行为管理');
    box('sbox');
    var url = '/features-auth/ajax/dialog/list';
    var data = {'module_id': module_id, '_csrf': globals.csrf};
    $.ajax({
        url: url,
        data: data,
        type: 'post',
        dataType: 'json',
        async: false,
        success: function (result) {
            if (result.success) {
                var html_data = _.template($('#action_content').html());
                $('#action_layer').html(html_data({'actions': result.actions,'actions_ids': result.module_actions_ids
                }));
            } else {
                layer.alert(result.msg);
            }
        }
    });
    $("#s_name_id").val(module_id);
};

/**
* 保存行为管理
*/
var save_actions = function() {
    var module_id = $('#s_name_id').val();
    if (module_id == '' || module_id == undefined) {
        xwin.tips('', 'module_id is null');
        return;
    }
    //post参数
    module_id = parseInt(module_id);
    var post_data = {'module_id': module_id, '_csrf': globals.csrf};
    var action_ids_array = new Array();
    $('#action_layer tr').each(function (idx) {
        var action_id = $(this).data('id');
        var check_ele = $($(this).find('input')[0]);
        if (check_ele.is(':checked')) {
            action_ids_array.push(action_id);
        }
    });

    if (action_ids_array.length >= 1) {
        post_data['data_ids'] = action_ids_array.join(',');
    }

    $.ajax({
        url: '/features-auth/ajax/dialog/save',
        data: post_data,
        type: 'post',
        dataType: 'json',
        async: false,
        success: function (result) {
            if (result.success) {
                close_qx();
                window.location.reload();
            } else {
                layer.alert(result.msg);
            }
        }
    })

};

//获取表单参数，并验证
var get_search_params = function () {
    var query = {};

    //验证模块编码
    var id = StringUtil.trim($('#module_id').val());
    if (!isNullOrEmpty(id)) {
        query['id'] = id;
    }

    //验证模块姓名
    var name = StringUtil.trim($('#module_name').val());
    if (!isNullOrEmpty(name)) {
        query['name'] = name;
    }

    return query;
};

//翻页参数实例
listForm.recordCount = 0;
listForm.pageCount = 0;
listForm.page = '1';//当前页
listForm.page_size = '15';//每页显示数量

listForm.loadList = function () {
    //重置快速翻页的页码
    if ($('#gotoPage').val() != listForm.page) {
        $('#gotoPage').val(listForm.page);
    }

    var url = '/features-auth/ajax/list';
    var data = get_search_params();

    //翻页参数
    data['start'] = listForm.get_start_num();
    data['page_size'] = listForm.page_size;

    data['_csrf'] = globals.csrf;
    $.ajax({
        url: url,
        data: data,
        type: 'POST',
        async: true,
        success: function (rdata) {
            var rdata = $.parseJSON(rdata);
            if (rdata.success) {
                //初始化部分
                listForm.record_count = rdata.count;
                $('#record_count').html(rdata.count);
                var data_page_num = Math.ceil(rdata.count / listForm.page_size);
                listForm.page_count = data_page_num;

                $('#page_str').html(listForm.page + '/' + data_page_num);
                //初始化部分结束

                var html_func = _.template($('#features_list_template').html());
                $('#features_list_layer').html(html_func({features: rdata.data, names:rdata.names}));
            }
        }
    });
};


$(function () {
    listForm.loadList();

    $('#search_features_list_button').click(function () {
        listForm.page = 1;
        listForm.loadList();
    });
});