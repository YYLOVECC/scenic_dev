var check_val = function (ele) {
    var ele_obj = $(ele);
    if (ele_obj.is(':checked')) {
        $('#is_enable').val(1);
    } else {
        $('#is_enable').val(0);
    }
};
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
        chkStyle: "checkbox",
        chkboxType: { "Y": "s", "N": "s" }

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

function reload_znode() {
    if(GLOBAL.valid_roles.length>0){
        var zNodes = [];
        $.each(GLOBAL.valid_roles, function (n, value) {
            zNodes.push({
                id: parseInt(value.id),
                pId: parseInt(value.parent_id),
                name: value.name,
                open: true
            })
        });
        var treeObj = $.fn.zTree.init($("#role_tree"), setting, zNodes);
        //选中已有角色
        if(GLOBAL.user_role_ids.length>0){
            for (var i = 0; i < GLOBAL.user_role_ids.length; i++) {
                var node = treeObj.getNodeByParam("id", GLOBAL.user_role_ids[i], null);
                treeObj.checkNode(node, true);
            }
        }
        $('#tree').show();
    }else{
        $('#tree').hide();
    }
}

var valid_form = function () {
    var is_parent = parseInt($('#bumen input[name="roles_is_parent"]:checked').val());
    if(is_parent){
        var treeObj = $.fn.zTree.getZTreeObj("role_tree");
        var sNodes = treeObj.getCheckedNodes();
        var checked_role_ids = [];
        $.each(sNodes, function(index, cNode){
            var check_status = cNode.getCheckStatus();
            if (check_status.checked==true){
                checked_role_ids.push(cNode.id);
            }
        });
        if (checked_role_ids.length!=1){
            layer.alert('请选择角色');
            return false;
        }
        $('#roles_id').val(checked_role_ids.join(','));
    }
};