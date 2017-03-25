/**
 * Created by licong on 2017/3/11.
 */
(function(){
    var GLOBAL = {
        csrf: $('meta[name=csrf-token]').attr('content'),
        module_url: $('#module_url').val(),
        hasr: true
    };
    var gotoPage     = $('#gotoPage'),
        record_count = $('#record_count'),
        page_str = $('#page_str');

    /**
     * 获取表单数据
     */
    var get_search_params = function() {
        var post_data      = {};
        var riqi           = $('#riqi').val();
        var ticket_name = $('#ticket_name').val();
        var scenic_id = $('#scenic_id option:selected').val();
        var ticket_price      = $('#ticket_price option:selected').val();
        var distributor_id = $('#distributor_id option:selected').val();

        // 判断是否包含日期条件
        if (riqi) {
            post_data['created_at_str'] = riqi.trim();
        }
        if (ticket_name) {
            post_data['ticket_name'] = ticket_name.trim();
        }
        if (scenic_id>0) {
            post_data['scenic_id'] =scenic_id;
        }
        if (ticket_price) {
            post_data['ticket_price'] = ticket_price;
        }
        if (distributor_id>0) {
            post_data['distributor_id'] = distributor_id;
        }

        return post_data;
    };

    /**
     * 加载数据
     * @param post_data 通过`get_search_params`请求参数封装
     */
    listForm.loadList = function(post_data) {
        var self = this;
        if (gotoPage.val() != self.page) {
            gotoPage.val(self.page);
        }
        post_data = post_data || get_search_params(); // 不管是否传入参数，都需要获取表单数据
        post_data['start'] =  self.get_start_num();
        post_data['page_size'] = self.page_size;
        post_data['_csrf'] = GLOBAL.csrf;
        post_data['ordinal_str'] = self.ordinal_str;
        post_data['ordinal_type'] = self.ordinal_type;

        $.ajax({
            url: GLOBAL.module_url + '/ajax/ticket_list',
            type: 'post',
            dataType: 'json',
            data: post_data,
            success: function(data) {
                self.record_count = data.count;
                record_count.html(data.count);
                self.page_count = Math.ceil(data.count / self.page_size);
                page_str.html(self.page + '/' + self.page_count);
                var html_func = _.template($('#ticket_list_template').html());
                $('#ticket_list_layer').html(html_func({ticket_info: data.ticket_data}));

            }
        });
    };
    $(function(){
        // 日期控件绑定
        Kalendae.moment.lang("zh-cn", {
            months: '一月_二月_三月_四月_五月_六月_七月_八月_九月_十月_十一月_十二月'.split('_'),
            monthsShort: '1月_2月_3月_4月_5月_6月_7月_8月_9月_10月_11月_12月'.split('_'),
            weekdays: '星期日_星期一_星期二_星期三_星期四_星期五_星期六'.split('_'),
            weekdaysShort: '周日_周一_周二_周三_周四_周五_周六'.split('_'),
            weekdaysMin: '日_一_二_三_四_五_六'.split('_')
        });
        var k4 = new Kalendae.Input('riqi', {
            months: 2,
            mode: 'range',
            direction: 'today-past',
            format: 'YYYY/MM/DD',
            selected: [Kalendae.moment().subtract({M: 1}), Kalendae.moment().add({M: 0})]
        });
        //初始化列表
        listForm.loadList();

        $('#search_ticket_list_button').on('click', function() {
            listForm.page=1;
            listForm.loadList();
            $('#checked_count').html(0);
        });
        /**
         * 获取checkbox的选中值
         */
        var getSelectedCheckboxValues = function() {
            var ids = [];
            $('input[name="ckbox"]:checked').each(function (index, item) {
                ids.push($(item).data('id'));
            });
            return ids.join(',');
        };
        /**
         * 统计勾选的总数
         */
        var countSelectedItems = function() {
            var checkedItems = getSelectedCheckboxValues(),
                checkedItemArr = checkedItems.split(',').filter(function (item) {
                    return item != "";
                });
            $('#checked_count').html(checkedItemArr.length);
        };
        // `common.js`中条目的点击后会触发该全局绑定事件
        $('body').on('OrderList:UpdateSelectedItems', function() {
            countSelectedItems();
        });
        //上架
        $('#ticket_list_layer').on('click', '.uiUp', function(evt){
            var currentTarget = $(evt.currentTarget),
                ticket_id = currentTarget.data('id');
            var cancel = function(index) {
                layer.close(index);
            };
            var yes = function() {
                $.ajax({
                    type: 'post',
                    url: '/scenic-show/ajax-up-ticket',
                    data:{'_csrf':GLOBAL.csrf, 'id':ticket_id},
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
            layer.confirm('确定上架此门票?', yes, cancel);
        });
        //下架
        $('#ticket_list_layer').on('click', '.uiDown', function(evt) {
            var currentTarget = $(evt.currentTarget),
                ticket_id = currentTarget.data('id');
            var cancel = function(index) {
                layer.close(index);
            };
            var yes = function() {
                $.ajax({
                    type: 'post',
                    url: '/scenic-show/ajax-down-ticket',
                    data: {'_csrf': GLOBAL.csrf, 'id': ticket_id},
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
            layer.confirm('确定下架此门票?', yes, cancel);
        });
        //下架
        $('#ticket_list_layer').on('click', '.force-down', function(evt) {
            var currentTarget = $(evt.currentTarget),
                ticket_id = currentTarget.data('id');
            var cancel = function(index) {
                layer.close(index);
            };
            var yes = function() {
                $.ajax({
                    type: 'post',
                    url: '/scenic-show/ajax-down-ticket',
                    data: {'_csrf': GLOBAL.csrf, 'id': ticket_id},
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
            layer.confirm('确定强制下架此门票?', yes, cancel);
        });

        //下架
        $('#uiDownBtn').bind('click', function () {
            var o_id = [];
            var pt = $("input[name='ckbox']:checked");
            var plen = pt.length;
            if (plen == 0) {
                layer.alert('请勾选你要处理的数据');
                return false;
            }
            for (var i = 0; i < plen; i++) {
                var pv = pt.eq(i);
                var pv_i = pv.val();
                var id = pv.data('id');
                var status = pv.data('status');
                if (status == 1) {
                    o_id.push(pv_i);
                } else {
                    layer.alert('只能下架已上架的门票');
                }
            }

            var o_id_str = o_id.join(',');
            layer.confirm('确认确定下架？', function () {
                $.ajax({
                    type: "POST",
                    url: GLOBAL.module_url + "/ajax-down-ticket",
                    data: {id: o_id_str, '_csrf': GLOBAL.csrf},
                    dataType: "json",
                    success: function (res) {
                        layer.alert(res.msg);
                        if (res.success) {
                            listForm.loadList();
                        } else {
                            layer.alert(res.msg);
                        }
                    }
                });
            });
        })

        //上架
        $('#uiUpBtn').bind('click', function () {
            var o_id = [];
            var pt = $("input[name='ckbox']:checked");
            var plen = pt.length;
            if (plen == 0) {
                layer.alert('请勾选你要处理的数据');
                return false;
            }
            for (var i = 0; i < plen; i++) {
                var pv = pt.eq(i);
                var pv_i = pv.val();
                var id = pv.data('id');
                var status = pv.data('status');
                if (status == 0 ||status == 2) {
                    o_id.push(pv_i);
                } else {
                    layer.alert('只能上架已下架门票');
                }
            }
            var o_id_str = o_id.join(',');
            layer.confirm('确认确定上架？', function () {
                $.ajax({
                    type: "POST",
                    url: GLOBAL.module_url + "/ajax-up-ticket",
                    data: {id: o_id_str, '_csrf': GLOBAL.csrf},
                    dataType: "json",
                    success: function (res) {
                        if (!res.success) {
                            layer.alert(res.msg);
                        } else {
                            listForm.loadList();
                            layer.alert('操作成功');
                        }
                    }
                });
            });
        })

        //强制下架
        $('#uiForceDownBtn').bind('click', function () {
            var o_id = [];
            var pt = $("input[name='ckbox']:checked");
            var plen = pt.length;
            if (plen == 0) {
                layer.alert('请勾选你要处理的数据');
                return false;
            }
            for (var i = 0; i < plen; i++) {
                var pv = pt.eq(i);
                var pv_i = pv.val();
                var id = pv.data('id');
                var status = pv.data('status');
                if (status == 1) {
                    o_id.push(pv_i);
                } else {
                    layer.alert('只能强制下架已上架的门票');
                }
            }

            var o_id_str = o_id.join(',');
            layer.confirm('确认确定强制下架？', function () {
                $.ajax({
                    type: "POST",
                    url: GLOBAL.module_url + "/ajax-force-down-ticket",
                    data: {id: o_id_str, '_csrf': GLOBAL.csrf},
                    dataType: "json",
                    success: function (res) {
                        layer.alert(res.msg);
                        if (res.success) {
                            listForm.loadList();
                        } else {
                            layer.alert(res.msg);
                        }
                    }
                });
            });
        })
    })
})();
