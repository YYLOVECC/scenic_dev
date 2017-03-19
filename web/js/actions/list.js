var globals = {
    csrf: $('meta[name=csrf-token]').attr('content')
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

    var url = '/actions/ajax/list';
    var data = {};

    //翻页参数
    data['start'] = listForm.get_start_num();
    data['page_size'] = listForm.page_size;

    data['_csrf'] = globals.csrf;
    $.ajax({
        url: url,
        data: data,
        type: 'POST',
        dataType: 'json',
        async: false,
        success: function (rdata) {
            if (rdata.success) {
                //初始化部分
                listForm.record_count = rdata.count;
                $('#record_count').html(rdata.count);
                var data_page_num = Math.ceil(rdata.count / listForm.page_size);
                listForm.page_count = data_page_num;
                $('#page_str').html(listForm.page + '/' + data_page_num);
                //初始化部分结束

                var html_func = _.template($('#actions_list_template').html());
                $('#actions_list_layer').html(html_func({tasks: rdata.data}));
            }
        }
    });
};

/**
 * 修改启用状态
 * @param id
 * @param is_enable
 */
var enable = function(id,is_enable){

    var status = '';
    var target_enable = 0;

    //修改当前状态
    if(is_enable == 0){
        target_enable = 1;
        status = '启用';
    }else if(is_enable == 1){
        target_enable = 0;
        status = '停用';
    }
    var url = '/actions/ajax/enable';
    var data = {'id': id,'is_enable':target_enable, '_csrf': globals.csrf};

    var cancel = function(index) {
        layer.close(index);
    }
    var yes = function() {
        $.ajax({
            url: url,
            data: data,
            type: 'post',
            dataType: 'json',
            async: false,
            success: function (result) {
                if (result.success) {
                    var target_obj = $('#task_' + id);
                    var target_status_obj = $('#task_status_' + id);
                    if (target_enable) {
                        target_status_obj.attr('class', 'blue');
                        target_status_obj.text('正常');
                        if ($.inArray('disable', globals.actions) != -1) {
                            target_obj.attr('onclick', 'enable(' + id + ', ' + target_enable + ')');
                            target_obj.text('停用');
                        } else {
                            target_obj.remove();
                        }
                    } else {
                        target_status_obj.attr('class', 'gray');
                        target_status_obj.text('停用');
                        if ($.inArray('enable', globals.actions) != -1) {
                            target_obj.attr('onclick', 'enable(' + id + ', ' + target_enable + ')');
                            target_obj.text('启用');
                        } else {
                            target_obj.remove();
                        }
                    }
                    layer.alert(result.msg, function(index){
                        layer.close(index);
                        listForm.page=1;
                        listForm.loadList();
                    })
                } else {
                    layer.alert(result.msg);
                }
            }
        })
    };
    layer.confirm('是否'+status+'该行为?', yes, cancel);

};

$(function () {
    listForm.loadList();
});