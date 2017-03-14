(function() {

    'use strict';
    var GLOBAL = {
        csrf: $('meta[name=csrf-token]').attr('content'),
        module_url: $('#module_url').val()
    };
    var gotoPage     = $('#gotoPage'),
        record_count = $('#record_count'),
        page_str = $('#page_str');

	/**
	 * 获取表单数据
	 */
	var get_search_params = function() {
        var post_data      = {};
        var riqi           = $('#riqi').val(),
            order_sn       = $('#order_sn').val(),
            scenic_name    = $('#scenic_name').val(),
            mobile         = $('#mobile').val(),
            tourist_name   = $('#tourist_name').val(),
            order_status   = $('#order_status').val(),
            pay_status     = $('#pay_status').val(),
            ticket_price   = $('#ticket_price').val(),
            audit_user     = $('#audit_user_id').val(),
            distributor_id        = $('#distributor_id').val();


		// 判断是否包含日期条件
		if (riqi) {
            post_data['created_at_str'] = riqi.trim();
        }

		// 判断是否包含订单号条件
		if (order_sn) {
			post_data['sn'] = order_sn.trim();
		}
		//门票名称
        if (scenic_name) {
		    post_data['scenic_name'] = scenic_name;
        }
        // 判断是否包含手机号码条件
	    if (mobile) {
			post_data['mobile'] = mobile;
		}
		//游客姓名
        if (tourist_name) {
		    post_data['tourist_name'] = tourist_name;
        }
	    // 判断是否包含订单状态条件(默认请求代发货的数据)
	    if (order_status) {
	        post_data['order_status'] = parseInt(order_status);
	    }
	    //支付状态
	    if(pay_status >-1) {
		    post_data['pay_status'] = parseInt(pay_status);
        }
	    if (ticket_price) {
		    post_data['ticket_price'] = ticket_price;
        }
        if (audit_user) {
		    post_data['audit_user_id'] = audit_user;
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
			url: '/order/ajax/order_list',
			type: 'post',
			dataType: 'json',
			data: post_data,
			success: function(data) {
				self.record_count = data.count;
				record_count.html(data.count);
				self.page_count = Math.ceil(data.count / self.page_size);
				page_str.html(self.page + '/' + self.page_count);
				var html_func = _.template($('#order_list_template').html());
				$('#order_list_layer').html(html_func({order_info: data.order_data}));

			}
		});
	};

    /**
     * 进详情页面
     * @param e
     */
    var goToDetail = function(e) {
        e.preventDefault();
        var target = $(e.currentTarget),
            order_id = target.data('order-id');
        window.open(GLOBAL.module_url + '/' + order_id);
    };

	$(function() {
        var zs_searchBtn = $('#zs_searchBtn'),
            zs_mask = $('#zs_mask'),
            searchBtn = $('#searchBtn'),
            table = $('table'),
            uiAuditBtn = $('#uiAuditBtn'),
            uiReviewBtn = $('#uiReviewBtn'),
            uiExportDataBtn = $('#uiExportDataBtn'),
            uiRefundAudit = $('#uiRefundAudit');


        zs_searchBtn.on('click', function () {
            listForm.loadList();
        });

        // 橘黄色查询按钮事件
        searchBtn.on('click', function () {
            listForm.page = 1;
            listForm.loadList();
            $('#checked_count').html(0);
        });
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


        // 首次加载获取数据
        listForm.loadList();

        //双击进入详情页
        table.on('dblclick', '#order_list_layer tr', function (e) {
            goToDetail(e);
        });

        // 详情点击事件
        table.on('click', '.detail-btn', function (e) {
            goToDetail(e);
        });

        //客审，待确认订单
        uiAuditBtn.bind('click', function () {
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
                var order_sn = pv.attr('data-order_sn');
                var pv_order_status = pv.data("order_status");
                if (pv_order_status == "2") {
                    o_id.push(pv_i);
                } else {
                    layer.alert('只能客审 待确认 状态订单哦~');
                    return false;
                }
            }

            var o_id_str = o_id.join(',');
            layer.confirm('确认进行审核操作？', function () {
                $.ajax({
                    type: "POST",
                    url: GLOBAL.module_url + "/ajax/to_examine",
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

        //反审核
        uiReviewBtn.bind('click', function () {
            var o_id = [];
            var pt = $("input[name='ckbox']:checked");
            var plen = pt.length;
            if (plen == 0) {
                layer.alert('请勾选你要处理的数据');
                return;
            }
            for (var i = 0; i < plen; i++) {
                var pv = pt.eq(i);
                var pv_i = pv.val();
                var pv_order_status = pv.data("order_status");
                if (pv_order_status == "3") {
                    o_id.push(pv_i);
                } else {
                    layer.alert('反审只能处理交易成功的订单哦~');
                    return;
                }
            }
            var o_id_str = o_id.join(',');
            layer.confirm('确认进行反审操作？', function () {
                $.ajax({
                    type: "POST",
                    url: GLOBAL.module_url + "/ajax/cancel_examine",
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

        //导出
        uiExportDataBtn.on('click', function () {
            if (listForm.record_count < 1) {
                layer.alert('无可导出的订单');
                return false;
            }
            var query_data = get_search_params();
            query_data['_csrf'] = GLOBAL.csrf;

            var order_ids = [],
                checkedBoxes = $('input[name="ckbox"]:checked');

            checkedBoxes.each(function (index, item) {
                order_ids.push($(item).data('order-id'));
            });
            if (order_ids.length < 1) {
                layer.alert('请选择需要导出的数据');
                return false;
            } else {
                query_data['ids'] = order_ids.join(',');
            }

            // 发送请求
            var get_data = '';
            $.each(query_data, function (i, n) {
                get_data += i + '=' + n + '&';
            });
            var url = GLOBAL.module_url + '/ajax/export_data';
            if (get_data) {
                url += '?' + get_data;
            }
            window.location.href = url;
        });

        //退款审核
        uiRefundAudit.bind('click', function () {
            var o_id = [];
            $('input[name="ckbox"]:checked').each(function (index, item) {
                o_id.push($(item).data('id'));
            });
            var o_id_str = o_id.join(',');
            if (o_id_str.length == 0) {
                layer.alert('请勾选你要处理的数据');
                return;
            }
            layer.confirm('是否确认退款审核？', function () {
                $.ajax({
                    type: "POST",
                    url: GLOBAL.module_url + "/ajax/order_refund_audit",
                    data: {ids: o_id_str, '_csrf': GLOBAL.csrf},
                    dataType: "json",
                    success: function (res) {
                        if (res.hasOwnProperty('error_data') && res.error_data.length > 0) {
                            res.msg += "错误单号如下：" + res.error_data.join(',')
                        }
                        layer.alert(res.msg, function (index) {
                            layer.close(index);
                            listForm.loadList();
                        }, null, true)
                    }
                });
            });
        });
    });
})();
