$("#bumen").delegate("input", "click", function () {
    var i = $(this).val();
    if (i == 1) {
        $("#bumens").show();
    } else {
        $("#bumens").hide();
    }
});
var setting = {
    view:{
        showIcon: false,
        showLine: false
    },
    check:{
        enable: true,
        chkStyle: "radio",
        radioType: "all"

    },
    data: {
        simpleData: {
            enable: true,
            idKey: "id",
            pIdKey: "pId",
            rootPId: ""
        }

    }
};

function reload_znode(parent_id) {
    var url = '/features-auth/ajax/tree';
    var data = {'_csrf': $('meta[name=csrf-token]').attr('content'),'id':$('#id').val()};
    $.ajax({
        url: url,
        data: data,
        type: 'post',
        dataType: 'json',
        async: false,
        success: function (data) {
            if (data.success) {
                if(data.data.length >0) {
                    var zNodes = [];
                    $.each(data.data, function (n, value) {
                        zNodes.push({
                            id: parseInt(value.id),
                            pId: parseInt(value.parent_id),
                            name: value.name,
                            open: true
                        })
                    });
                    var treeObj = $.fn.zTree.init($("#feature_tree"), setting, zNodes);
                    //编辑时选中父亲节点
                    if(!isNullOrEmpty(parent_id) && parseInt(parent_id)){
                        var node = treeObj.getNodeByParam("id", parent_id, null);
                        treeObj.checkNode(node, true);
                    }
                    $('#tree').show();
                }else{
                    $('#tree').hide();
                }
            }
        }
    });

}

$("#form").Validform({tiptype: 2});

var valid_from = function () {
    var is_parent = parseInt($('#bumen input[name="is_parent"]:checked').val());
    if(is_parent){
        var treeObj = $.fn.zTree.getZTreeObj("feature_tree");
        var sNodes = treeObj.getCheckedNodes();
        var checked_parent = [];
        $.each(sNodes, function(index, cNode){
            var check_status = cNode.getCheckStatus();
            if (check_status.checked==true && check_status.half==false){
                checked_parent.push(cNode.id);
            }
        });
        if (checked_parent.length!=1){
            xwin.tips('', '请选择上级模块');
            return false;
        }
        $('#parent_id').val(checked_parent[0]);
    }
};
$(function () {
    if(isNullOrEmpty(parent_id) || !parseInt(parent_id)){
        $('input:radio[name="is_parent"][value="0"]').prop('checked', true);
    }else{
        $('input:radio[name="is_parent"][value="1"]').prop('checked', true);
    }
    reload_znode(parent_id);
});