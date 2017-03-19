var GLOBAL = {
    csrf: $('meta[name=csrf-token]').attr('content')
};
//var actions = $.parseJSON('{{ actions|json_encode|raw }}');

var box = function(id){
    $("#tanbox").fadeIn(200);
    var b=$("#"+id);
    var bh=b.height();
    var wh=$(window).height();
    if(bh<wh){
        var m=(wh-bh)/2;
        b.css("margin-top",0).show();
    }else{
        b.css("margin-top",0).show();
    }
};

var close_qx=function(){
    $("#tanbox").hide();
}

var setting = {
    view:{
        showIcon: false,
        showLine: false
    },
    check:{
        enable: true,
        chkStyle: "checkbox",
        chkboxType: { "Y": "ps", "N": "s" }
    },
    data: {
        simpleData: {
            enable: true,
            idKey: "id",
            pIdKey: "pId",
            rootPId: 0
        }

    }
};
<!-- 功能权限开始 -->
/**
 * 获取角色数据
 * @returns {Array}
 */
var reload_feature_nodes = function(role_id){
    var nodes = [{id:0,pId:-1,name:'票务系统功能菜单',open:false}];
    $.ajax({
        url: '/role/ajax/role_feature_privilege',
        data: {'id': role_id, '_csrf': GLOBAL.csrf},
        type: 'post',
        dataType: 'json',
        async: false,
        success: function (data) {
            if (data.success) {
                if(data.modules.length >0) {
                    $.each(data.modules, function (n, value) {
                        nodes.push({
                            id: parseInt(value.id),
                            pId: parseInt(value.parent_id),
                            name: value.name,
                            open: false
                        });
                        if(value['actions'].length>0){
                            $.each(value['actions'], function(index, item){
                                nodes.push({
                                    id: value.id+'_'+item.id+'_'+item.module_action_id,
                                    pId: parseInt(value.id),
                                    name: item.name,
                                    open: false
                                });
                            });

                        }
                    });
                    var treeObj = $.fn.zTree.init($("#feature_tree"), setting, nodes);
                    //选中已拥有功能权限
                    var role_module_ids = data.role_module_ids;
                    if(role_module_ids.length>0){
                        for (var i = 0; i < role_module_ids.length; i++) {
                            var node = treeObj.getNodeByParam("id", role_module_ids[i], null);
                            treeObj.checkNode(node, true);
                        }
                    }
                }
            }
        }
    });
};

function gongneng(id){
    box("gbox");
    $("#g_name_id").val(id);
    reload_feature_nodes(id);
}

var close_gn = function(){
    $("#tanbox").hide();
    $("#gbox").hide();
    $("#feature_tree").empty();
};

/**
 * 保存角色功能权限
 */
function save_feature_privilege(){
    var role_id = $('#g_name_id').val();
    if (role_id == '' || role_id == undefined) {
        layer.alert('role_id is null');
        return ;
    }
    //post参数
    role_id = parseInt(role_id);
    //获取选中功能权限
    var treeObj = $.fn.zTree.getZTreeObj("feature_tree");
    var sNodes = treeObj.getCheckedNodes();
    var checked_feature_ids = [];
    $.each(sNodes, function(index, cNode){
        var check_feature = cNode.getCheckStatus();
        if (check_feature.checked==true && cNode.id){
            checked_feature_ids.push(cNode.id);
        }
    });
    var post_data = {'id': role_id, '_csrf': GLOBAL.csrf,'feature_id_str': checked_feature_ids.join(',')};
    $.ajax({
        url:'/role/ajax/save_feature_privilege',
        data: post_data,
        type: 'post',
        dataType: 'json',
        async: false,
        success: function(result) {
            if (result.success){
                close_gn();
            }else{
                layer.alert(result.msg);
            }
        }
    })

}
<!-- 功能权限结束 -->
var close_zd = function(){
    $("#tanbox").hide();
};
<!-- 列表数据渲染开始 -->

//翻页参数实例
listForm.recordCount = 0;
listForm.pageCount = 0;
listForm.page = '1';//当前页
listForm.page_size = '15';//每页显示数量

listForm.loadList = function(){
    //重置快速翻页的页码
    if ($('#gotoPage').val() != listForm.page) {
        $('#gotoPage').val(listForm.page);
    }

    var start_num = listForm.get_start_num();
    var url = '/role/ajax/list';
    //查询参数
    //部门状态
    var post_data = {'state': $('#role_state').val(), '_csrf': GLOBAL.csrf};
    //部门名称
    var search_name = $.trim($('#search_name').val());
    if (search_name) {
        search_name = search_name.match(/\S+/g).join('');
        post_data['search_name'] = search_name;
    }

    //翻页参数
    post_data['start'] = start_num;
    post_data['page_size'] = listForm.page_size;

    $.ajax({
        url: url,
        data: post_data,
        type: 'POST',
        dataType: 'json',
        async: false,
        success: function (rdata) {
            if (rdata.success){
                //初始化部分
                listForm.record_count = rdata.count;
                $('#record_count').html(rdata.count);
                var data_page_num = Math.ceil(rdata.count / listForm.page_size);
                listForm.page_count = data_page_num;
                $('#page_str').html(listForm.page + '/' + data_page_num);
                //初始化部分结束

                var html_func = _.template($('#roles').html());
                $('#role_content').html(html_func({roles: rdata.data}));
            }
        }
    });
};
<!-- 列表数据渲染结束 -->

/**
 * 停用角色
 * @param role_id
 * @param state
 * @returns {boolean}
 */
function disable_role(role_id, state){
    state = parseInt(state);
    role_id = parseInt(role_id);
    if(state != 1){
        layer.alert('数据有误');
        return false;
    }
    var url = '/role/ajax/disable';
    var cancel = function() {

    }
    var yes = function(){
        $.ajax({
            url: url,
            data: {id: role_id, '_csrf': GLOBAL.csrf},
            type: 'post',
            dataType: 'json',
            async: false,
            success: function (data) {
                if (data.success) {
                    $.each(data.disable_ids, function (index, d_id) {
                        var operate_text = '';
                        if($.inArray('enable', GLOBAL.actions)!=-1){
                            operate_text += '<em class="vline">|</em><a href="javascript:void(0);" onclick="enable_role('+d_id+', 0)" class="cblue">启用</a>'
                        }
                        if($.inArray('delete', GLOBAL.actions)!=-1){
                            operate_text += '<em class="vline">|</em><a href="#" onclick="delete_role('+d_id+')" class="cblue">删除</a>';
                        }
                        $('#operate_'+d_id).html(operate_text);
                        $('#state_'+d_id).html('<b class="gray">停用<b>');
                    });
                    layer.alert(data.msg, function (index) {
                        layer.close(index);
                        listForm.page = '1';
                        listForm.loadList();

                    })
                } else {
                    layer.alert(data.msg);
                    return false;
                }
            }
        });
    }
    layer.confirm('确定停用该角色吗？', yes, cancel)
}


/**
 * 启用角色
 * @param role_id
 * @param state
 * @returns {boolean}
 */
function enable_role(role_id, state){
    state = parseInt(state);
    role_id = parseInt(role_id);
    if(state != 0){
        layer.alert('数据有误');
        return false;
    }
    var url = '/role/ajax/enable';
    var cancel = function() {
    };
    var yes = function(){
         $.ajax({
            url: url,
            data: {id: role_id, '_csrf': GLOBAL.csrf},
            type: 'post',
            dataType: 'json',
            async: false,
            success: function (data) {
                if (data.success) {
                    $.each(data.enable_ids, function (index, d_id) {
                        var operate_text = '';
                        if($.inArray('edit', GLOBAL.actions)!=-1){
                            operate_text += '<em class="vline">|</em><a href="/role/edit/'+d_id+'" class="cblue">修改</a>';
                        }
                        if($.inArray('disable', GLOBAL.actions)!=-1){
                            operate_text += '<em class="vline">|</em><a href="javascript:void(0);" onclick="disable_role('+d_id+', 1)" class="cblue">停用</a>';
                        }

                        $('#operate_'+d_id).html(operate_text);
                        $('#state_'+d_id).html('<b class="blue">正常<b>');

                    });
                    layer.alert(data.msg, function (index) {
                        layer.close(index);
                        listForm.page = '1';
                        listForm.loadList();

                    })
                } else {
                    layer.alert(data.msg);
                    return false;
                }
            }
        });
    }
    layer.confirm("确定启用该角色吗？",yes, cancel);
}

/**
 * 删除角色
 * @param role_id
 * @returns {boolean}
 */
function delete_role(role_id){
    var cancel = function (index) {
        layer.close(index)
    };
    var yes = function() {
        $.ajax({
            url: '/role/ajax/delete',
            data: {id: role_id, '_csrf': GLOBAL.csrf},
            type: 'post',
            dataType: 'json',
            async: false,
            success: function (data) {
                if (data.success) {
                    layer.alert(data.msg, function(index){
                        layer.close(index);
                        listForm.page = '1';
                        listForm.loadList();
                    });
                } else {
                    layer.alert(data.msg);
                    return false;
                }
            }
        });
    }
    layer.confirm("确定删除该角色及其子角色吗？", yes, cancel);
}

