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


        // 判断是否包含日期条件
        if (riqi) {
            post_data['created_at_str'] = riqi.trim();
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
    })
})();
