/**
 * Created by licong on 2017/2/11.
 */
var GLOBAL = {
    csrf: $('meta[name=csrf-token]').attr('content')
};

/**
 * 获取表单数据
 */
var get_search_params = function(){
    var query = {};

    //验证员工编码
    var id = $('#id').val();
    if (!isNullOrEmpty(id)){
        query['id'] = StringUtil.trim(id);
    }

    //验证员工姓名
    var name = $('#name').val();
    if (!isNullOrEmpty(name)){
        query['name'] = StringUtil.trim(name);
    }

    //验证员工邮箱
    var email = $('#email').val();
    if (!isNullOrEmpty(email)){
        query['email'] = StringUtil.trim(email);
    }

    //验证状态
    var status_arr = [-1,0,1];
    var status = $('#status').val();
    if (status_arr.indexOf(status)){
        query['status'] = status;
    }

    //验证角色
    var role_id = $('#role_id').val();
    if (role_id > -2){
        query['role_id'] = role_id;
    }
    return query;
};

$(function() {
    $('#status').val(-1);
    $('#role_id').val(-1);
    var gotoPage = $('#gotoPage'),
        record_count = $('#record_count'),
        page_str = $('#page_str');

    listForm.loadList = function() {
        var self = this;
        if (gotoPage.val() != self.page) {
            gotoPage.val(self.page);
        }

        var post_data = get_search_params();
        post_data['start'] = self.get_start_num();
        post_data['page_size'] = self.page_size;
        post_data['_csrf'] = GLOBAL.csrf;

        $.ajax({
            url: '/users/ajax/list',
            type: 'post',
            dataType: 'json',
            data: post_data,
            success: function(data) {
                self.record_count = data.count;
                record_count.html(data.count);
                self.page_count = Math.ceil(data.count / self.page_size);
                page_str.html(self.page + '/' + self.page_count);
                var html_func = _.template($('#item_user_template').html());
                $('#users_list_layer').html(html_func({users: data.data}));
            }
        });
    };

    listForm.loadList();

    $('#search_user_btn').click(function(){
        listForm.page = 1;
        listForm.loadList();
    });
    //启用用户
    $('#users_list_layer').on('click', '.enable', function(evt){
        var currentTarget = $(evt.currentTarget),
            user_id = currentTarget.data('id');
        var cancel = function(index) {
            layer.close(index);
        };
        var yes = function() {
            $.ajax({
                type: 'post',
                url: '/users/ajax-enable-user',
                data:{'_csrf':GLOBAL.csrf, 'id':user_id},
                dataType:'json',
                success:function(data){
                    if (data.success) {
                        layer.alert(data.msg, function (index) {
                            layer.close(index);
                            listForm.page = '1';
                            listForm.loadList();
                        })
                    } else {
                        layer.alert(data.msg);
                    }
                    window.location.reload();
                }

            })
        };
        layer.confirm('确定启用该用户?', yes, cancel);
    });
    //停用用户
    $('#users_list_layer').on('click', '.disable', function(evt) {
        var currentTarget = $(evt.currentTarget),
            user_id = currentTarget.data('id');
        var cancel = function(index) {
            layer.close(index);
        };
        var yes = function() {
            $.ajax({
                type: 'post',
                url: '/users/ajax-disable-user',
                data: {'_csrf': GLOBAL.csrf, 'id': user_id},
                dataType: 'json',
                success: function (data) {
                    if (data.success) {
                        layer.alert(data.msg, function (index) {
                            layer.close(index);
                            listForm.page = '1';
                            listForm.loadList();
                        })
                    } else {
                        layer.alert(data.msg);
                    }
                    window.location.reload();
                }

            })
        };
        layer.confirm('确定停用该用户?', yes, cancel);
    });

});

