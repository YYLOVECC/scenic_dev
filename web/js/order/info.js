(function() {
    var GLOBAL = {
        csrf: $('meta[name=csrf-token]').attr('content'),
        module_url: $('#module_url').val(),
        hasr: true
    };

    $(function() {
        var zs_rzbtn = $('#zs_rzbtn'),
            order_id = $('#order_id').val(),
            service_id = $('#service_id'),
            zs_mask = $('#zs_mask'),
            exportBtn = $('#exportBtn'),
            aduitBtn = $('#auditBtn'),
            reviewBtn = $('#reviewBtn'),
            refundAuditBtn = $('#refundAuditBtn'),
            uiActionLogs = $('#uiActionLogs');

        /**
         * 操作日志
         */
        zs_rzbtn.on('click', function(){
            if (GLOBAL.hasr) {
                $.ajax({
                    type:'POST',
                    url: GLOBAL.module_url + '/ajax-action-logs',
                    data:{id:order_id, '_csrf':GLOBAL.csrf},
                    dataType:'json',
                    success:function(res){
                        if(res.success){
                            GLOBAL.hasr = false;
                            if (res.success) {
                                var template_func = _.template($('#operation-item-template').html());
                                $('#operation-list-content').html(template_func({logs: res.data}));
                            }else{
                                $('#operation-list-content').html(res.msg);
                            }
                        }else{
                            $('#operation-list-content').html(res.msg);
                        }
                    }
                });
            } else{
                $("#zs_rz").toggle();
                GLOBAL.hasr = true;
            }
        });

        //审核
        aduitBtn.on('click', function () {
            var cancel = function () {
            };
            var yes = function () {
                $.ajax({
                    type: "POST",
                    url: GLOBAL.module_url + "/ajax/to_examine",
                    data: {id: order_id,'_csrf': GLOBAL.csrf},
                    dataType: "json",
                    success: function (res) {
                        layer.alert(res.msg);
                        if (res.success) {
                            layer.alert(res.msg, function (index){
                                layer.close(index);
                                window.location.reload();
                            });
                        } else {
                            layer.alert(res.msg);
                        }
                    }
                });
            };
            layer.confirm("是否确认审核？", yes, cancel);
        });
        //反审
        reviewBtn.on('click', function () {
            var cancel = function () {
            };
            var yes = function () {
                $.ajax({
                    type: "POST",
                    url: GLOBAL.module_url + "/ajax/cancel_examine",
                    data: {id: order_id, '_csrf': GLOBAL.csrf},
                    dataType: "json",
                    success: function (res) {
                        if (!res.success) {
                            layer.alert(res.msg);
                        } else {
                            layer.alert(res.msg, function (index){
                                layer.close(index);
                                window.location.reload();
                            });
                        }
                    }
                });
            };
            layer.confirm('是否确认反审', yes, cancel);
        });
        //退款审核
        /**
         * 退款审核
         */
        refundAuditBtn.bind('click', function() {
            layer.confirm('确定进行退款审核吗？', function () {
                $.ajax({
                    type: 'POST',
                    url: GLOBAL.module_url + "/ajax/order_refund_audit",
                    dataType: 'json',
                    data: {'_csrf': GLOBAL.csrf, 'ids': order_id},
                    async: false,
                    success: function (res) {
                        if (res.success) {
                            layer.alert(res.msg);
                            window.location.reload();
                        } else {
                           layer.alert(res.msg);
                        }
                    }

                })
            })
        });
    });
})();
