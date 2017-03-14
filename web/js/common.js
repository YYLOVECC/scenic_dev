var getCsrfToken = function() {
    return $('meta[name=csrf-token]').attr('content');
};

var getLocalTime = function (nS) {
    return new Date(parseInt(nS) * 1000).toLocaleString().replace(/年|月/g, "-").replace(/日/g, " ");
};

var getDateStr = function(dayDelta) {
    var dd = new Date();
    dd.setDate(dd.getDate() + dayDelta); // 获取dayDelta天后的日期
    var y = dd.getFullYear();
    var m = dd.getMonth() + 1; // 获取当前月份的日期
    var d = dd.getDate();
    return y + "/" + m + "/" + d;
};
//左边导航栏点击加号
$("#navs").delegate(".mn","click",function(){
    $(this).parent().toggleClass("nav_on");
});

/**
 * 封装Kalendae日期空间的便捷方法
 * 后期可以根据具体的需要进行修改
 *
 * @returns {Kalendae.Input}
 * @param options
 */
var kalendaeShow = function(options) {
    Kalendae.moment.lang("zh-cn", {
        months: '一月_二月_三月_四月_五月_六月_七月_八月_九月_十月_十一月_十二月'.split('_'),
        monthsShort: '1月_2月_3月_4月_5月_6月_7月_8月_9月_10月_11月_12月'.split('_'),
        weekdays: '星期日_星期一_星期二_星期三_星期四_星期五_星期六'.split('_'),
        weekdaysShort: '周日_周一_周二_周三_周四_周五_周六'.split('_'),
        weekdaysMin: '日_一_二_三_四_五_六'.split('_')
    });
    return new Kalendae.Input(options.input, {
        months: options['months'] || 2,
        mode: options['mode'] || 'range',
        direction: options['direction'] || 'today-past',
        format: 'YYYY/MM/DD'
        //selected:[Kalendae.moment().subtract({M:1}), Kalendae.moment().add({M:1})]
    });
};

var StringUtil = new Object();
StringUtil.trim = function(str){//删除左右两端的空格
    return str.replace(/(^\s*)|(\s*$)/g, "");
};

StringUtil.ltrim = function(str){
    return str.replace(/(^\s*)/g,"");
};

StringUtil.rtrim = function(str){
    return str.replace(/(\s*$)/g,"");
};
//是否是正整数
StringUtil.isUnsignedNumber = function(str){
    var reg=/^(\+|-)?\d+$/;
    return reg.test(str);
};

function isNullOrEmpty(strVal) {
    if (strVal == '' || strVal == null || strVal == undefined){
//    if (Validator.isEmpty(strVal)){
        return true;
    }else{
        strVal = StringUtil.trim(strVal+'');
        if (strVal == '') {
            return true;
        }
    }
    return false;

}
/**
 * 常用验证方法
 * @type {{isMobile: Function, isEmail: Function, isNumber: Function, isEmpty: Function, test: Function}}
 */
var Validator = {
    isMobile: function(s) {
        if (!s || !(s.toString()).length) {
            return false;
        }
        s = s.toString();

        var reg1 = /^(0[0-9]{2,3}(|-))?([2-9][0-9]{6,7})+(\-[0-9]{1,4})?$/;
        var reg2 = /^1[3-9][0-9]\d{8}$/;
        var reg3 = /^(400)(|-)(\d{3})(|-)(\d{4})$/;
        if (reg1.test(s)) {
            return true;
        }
        else {
            s = s.replace(/\+86/, "");
            if (reg2.test(s)) {
                return true;
            }
            if(reg3.test(s)){
                return true;
            }
        }
        //return this.test(s, /(^0{0,1}1[3|4|5|6|7|8|9][0-9]{9}$)/)
        return false;
    },

    isEmail: function(a) {
        var b = "^[-!#$%&'*+\\./0-9=?A-Z^_`a-z{|}~]+@[-!#$%&'*+\\/0-9=?A-Z^_`a-z{|}~]+.[-!#$%&'*+\\./0-9=?A-Z^_`a-z{|}~]+$";
        return this.test(a, b);
    },

    isNumber: function(s, d) {
        return !isNaN(s.nodeType == 1 ? s.value : s) && (!d || !this.test(s, "^-?[0-9]*\\.[0-9]*$"));
    },

    isEmpty: function(s) {
        return !jQuery.isEmptyObject(s);
    },

    test: function(s, p) {
        s = s.nodeType == 1 ? s.value : s;
        return new RegExp(p).test(s);
    }
};

var listForm = {
    record_count: 0,
    page_count: 0,
    page: 1,
    page_size: $('#pageSize').val() || 15,
    ordinal_str: '',
    ordinal_type: '',

    get_start_num: function() {
        return (this.page - 1) * this.page_size;
    },
    /**
     * 转去指定页面
     * @param page
     */
    goto_page: function(page) {
        if (page != null) this.page = page;
        if (this.page > this.page_count) this.page = 1;
        this.loadList();
    },

    /**
     * 转去第一页
     */
    goto_first_page: function() {
        if (this.page > 1) {
            this.goto_page(1);
        }
    },

    /**
     * 转去上一页
     */
    goto_pre_page: function() {
        if (this.page > 1) {
            this.goto_page(this.page - 1);
        }
    },

    /**
     * 转去下一页
     */
    goto_next_page: function() {
        if (this.page < this.page_count) {
            this.goto_page(parseInt(this.page) + 1);
        }
    },

    /**
     * 转去最后一页
     */
    goto_last_page: function() {
        if (this.page < this.page_count) {
            this.goto_page(this.page_count);
        }
    },
    gotop: function(){
        window.scrollTo(0,0);
    },

    /**
     * 更改每页显示条目
     * @param num
     * @returns {boolean}
     */
    change_page_size: function(num) {
        this.page_size = num;
        this.goto_page(null);
        return false;
    },

    /**
     * 快速翻页
     * @param obj
     */
    quick_goto_page: function(obj) {
        var num = StringUtil.trim(obj.value);
        if (!Validator.isNumber(num)) {
            num = 1;
        }
        this.goto_page(num);
    },

    /**
     * /动态编辑
     */
    select_all: function (obj, chk) {
        var ov = $(obj);
        var pt = $("input[name='" + chk + "']");
        var hasck = ov.prop("checked");
        if (hasck) {
            pt.prop("checked", true);
        } else {
            pt.prop("checked", false);
        }
    },

    /**
     * 排序
     * @param obj
     * @param ordinal_str
     */
    sort: function(obj, ordinal_str) {
        $('.sort').removeAttr('class');
        if (this.ordinal_str == ordinal_str){
            this.ordinal_type = this.ordinal_type == "ASC" ? "DESC": "ASC";
        }else{
            this.ordinal_str = ordinal_str;
            this.ordinal_type = 'ASC';
        }

        if(this.ordinal_type == 'ASC'){
            $(obj).attr('class', 'sort sortup');
        }else{
            $(obj).attr('class', 'sort');
        }

        this.loadList();
        this.gotop();
    },

    /**
     * 需要覆盖
     */
    loadList: function() {}
};

//省市区插件,请参考ecshop的region.php返回值
var region = {
    loadRegions: function(parent, target, init_val,child_target,child_val){
        if (!StringUtil.isNullOrEmpty(parent)){
            $.ajax({
                url: "/region/ajax/region?target="+target+"&parent="+parent+"&_csrf="+GLOBAL.csrf,
                type: 'GET',
                dataType:'json',
                success: function(result){
                    region.response(result);
                    //处理数据初始化
                    if(!StringUtil.isNullOrEmpty(init_val)){
                        $('#'+target).val(init_val);
                        if(!StringUtil.isNullOrEmpty(child_target) || !StringUtil.isNullOrEmpty(child_val)){
                            region.loadRegions(init_val, child_target, child_val);
                        }
                    }

                }
            });
        }
    },
    loadProvinces: function (country, selName) {
        var objName = (typeof selName == "undefined") ? "selProvinces" : selName;
        region.loadRegions(country, objName);
    },
    loadCities: function (province, selName) {
        var objName = (typeof selName == "undefined") ? "selCities" : selName;
        region.loadRegions(province, objName);
    },
    loadDistricts: function (city, selName) {
        var objName = (typeof selName == "undefined") ? "selAreas" : selName;
        region.loadRegions(city, objName);
    },
    changed: function (obj, selName) {
        var parent = obj.options[obj.selectedIndex].value;
        region.loadRegions(parent, selName);
        if (selName == 'selCities') {
            $('#selAreas').hide();
        } else if (selName == 'selCities2') {
            $('#selAreas2').hide();
        }
    },
    response: function (result) {
        var sel = document.getElementById(result.target);
        sel.length = 1;
        sel.selectedIndex = 0;
        sel.style.display = (result.count == 0) ? "none" : '';
        if (result.data) {
            for (var i = 0; i < result.count; i++) {
                var opt = document.createElement("OPTION");
                opt.value = result.data[i].id;
                opt.text = result.data[i].name;
                opt.setAttribute('data-name', result.data[i].value);
                sel.options.add(opt);
            }
        }
    }
};


var Browser = {
    isMozilla: (typeof document.implementation != 'undefined') && (typeof document.implementation.createDocument != 'undefined') && (typeof HTMLDocument != 'undefined'),
    isIE: window.ActiveXObject ? true: false,
    sFirefox: (navigator.userAgent.toLowerCase().indexOf("firefox") != -1),
    isSafari: (navigator.userAgent.toLowerCase().indexOf("safari") != -1),
    isOpera: (navigator.userAgent.toLowerCase().indexOf("opera") != -1)
};
var Utils = {
    fixEvent: function (e) {
        var evt = (typeof e == "undefined") ? window.event : e;
        return evt;
    },
    srcElement: function (e) {
        if (typeof e == "undefined") e = window.event;
        var src = document.all ? e.srcElement : e.target;
        return src
    },
    isInt: function (val) {
        if (val == "") {
            return false
        }
        var reg = /\D+/;
        return !reg.test(val)
    },
    /**
     * 检测字符串是否是价格格式
     */
    isPriceNumber: function(_keyword) {
        if (_keyword == "0" || _keyword == "0." || _keyword == "0.0" || _keyword == "0.00") {
            _keyword = "0";
            return true;
        } else {
            var index = _keyword.indexOf("0");
            var length = _keyword.length;
            if (index == 0 && length > 1) {/*0开头的数字串*/
                var reg = /^[0]{1}[.]{1}[0-9]{1,2}$/;
                if (!reg.test(_keyword)) {
                    return false;
                } else {
                    return true;
                }
            } else {/*非0开头的数字*/
                var reg = /^[1-9]{1}[0-9]{0,10}[.]{0,1}[0-9]{0,2}$/;
                if (!reg.test(_keyword)) {
                    return false;
                } else {
                    return true;
                }
            }
            return false;
        }
    }
};

//弹窗
$.fn.hjPopBox = function(options) {
    var defaults = {
            closeBtn: '.close',
            mask: '.mask'
        },
        opts = $.extend(defaults, options),
        popBox = $(this),
        Ie6 = Ie6check(),
        objHeight = popBox.height(),
        objWidth = popBox.width(),
        init = {};

    function setPos() {
        var winCHeight = document.documentElement.clientHeight,
            objCssTop = (winCHeight - objHeight) / 2;

        popBox.css({
            'top': objCssTop
        });
    }

    /*ie 6 check*/
    function Ie6check() {
        var bool;
        if (window.XMLHttpRequest) {
            bool = false;
        } else {
            bool = true;
        }
        return bool;
    }

    function setIe6Pos() {
        var winTop = document.documentElement.scrollTop || document.body.scrollTop,
            winCHeight = document.documentElement.clientHeight,
            objIe6Top = winTop + ((winCHeight - objHeight) / 2);
        popBox.css({
            'top': objIe6Top
        });
    }

    function setLeftPos() {
        var winCWidth = document.documentElement.clientWidth,
            objCssLeft = (winCWidth - objWidth) / 2;

        popBox.css({
            'left': objCssLeft
        });
    }

    popBox.delegate(opts.closeBtn, 'click', function() {
        popBox.addClass('popBoxHid');
        if (opts.mask == '') {
            return false;
        } else {
            var objMask = $(opts.mask);
            objMask.addClass('maskHid');
        }

    });

    init = {
        Ie: function() {
            setIe6Pos();
            setLeftPos();
        },
        noIe: function() {
            setPos();
            setLeftPos();
        }
    };

    if (Ie6) {
        init.Ie();
        $(window).bind('scroll', function() {
            setIe6Pos();
        });
        $(window).bind('resize', function() {
            init.Ie();
        })

    } else {
        init.noIe();
        $(window).bind('resize', function() {
            init.noIe();
        })

    }

};

function ajaxbefore(id, content){
    $("#loading").remove();
    $("#mask").hide();
    if(id==1){
        if(StringUtil.isNullOrEmpty(content)){
            content = "努力加载中，请稍后...";
        }
        var _html = '<div class="alert zs_pt300" id="loading"><div class="mark_layer">' +
            '<div class="pdad"><p class="wrap">' +
            '<img class="mr5" width="24px" height="24px" src="../css/loading.gif">'+content+'</p></div>';
        $("body").append(_html);
        $("#mask").show();
    }
}

//全选反选
$("#ck_tr").click(function () {
    var pt = $("#listDiv").find("input");
    var tr = $("#listDiv tbody").find("tr");
    var hasck = $(this).prop("checked");
    if (hasck) {
        pt.prop("checked", true);
        tr.addClass("on");
    } else {
        pt.prop("checked", false);
        tr.removeClass("on");
    }
});

$("#listDiv").delegate("input", "click", function () {
    var hasck = $(this).prop("checked");
    var tr = $(this).parent().parent();
    var i = $(this).attr("id");
    if (hasck) {
        if (i != "ck_tr") {
            tr.removeClass("on");
        }
    } else {
        tr.addClass("on");
    }
});

$("#listDiv").delegate("tr","click",function(event){
    var i=$(this).hasClass("on");
    if(!i){
        $(this).addClass("on");
        $(this).find("input[name='ckbox']").prop("checked",true);
    }else{
        $(this).removeClass("on");
        $(this).find("input[name='ckbox']").prop("checked",false);
    }
    $('body').trigger('OrderList:UpdateSelectedItems');
});
$("#listDiv").delegate("em","click",function(event){
    event.stopPropagation();
});


//数组操作
Array.prototype.indexOf = function(val) {
    for (var i = 0; i < this.length; i++) {
        if (this[i] == val) return i;
    }
    return -1;
};
Array.prototype.removes = function(val) {
    var index = this.indexOf(val);
    if (index > -1) {
        this.splice(index, 1);
    }
};

function hasarr(arr,val) {
    if (arr.indexOf(val) !== -1) {
        return true;
    } else {
        return false;
    }
}

/**
 * 浮点型数字相乘
 * @param arg1
 * @param arg2
 * @returns {number}
 */
function accMul(arg1,arg2)
{
    var m=0,s1=arg1.toString(),s2=arg2.toString();
    try{m+=s1.split(".")[1].length}catch(e){}
    try{m+=s2.split(".")[1].length}catch(e){}
    return Number(s1.replace(".",""))*Number(s2.replace(".",""))/Math.pow(10,m);
}

/**
 * 浮点数相加
 * @param arg1
 * @param arg2
 * @returns {number}
 */
function accAdd(arg1,arg2){
    var r1,r2,m;
    try{r1=arg1.toString().split(".")[1].length}catch(e){r1=0}
    try{r2=arg2.toString().split(".")[1].length}catch(e){r2=0}
    m=Math.pow(10,Math.max(r1,r2));
    return (arg1*m+arg2*m)/m
}


/**
 * 浮点数减法
 * @param arg1
 * @param arg2
 * @returns {string}
 * @constructor
 */
function Subtr(arg1,arg2){
    var r1,r2,m,n;
    try{r1=arg1.toString().split(".")[1].length}catch(e){r1=0}
    try{r2=arg2.toString().split(".")[1].length}catch(e){r2=0}
    m=Math.pow(10,Math.max(r1,r2));
    //last modify by deeka
    //动态控制精度长度
    n=(r1>=r2)?r1:r2;
    return ((arg1*m-arg2*m)/m).toFixed(n);
}

/**
 * 浮点数除法
 * @param arg1
 * @param arg2
 * @returns {number}
 */
function accDiv(arg1,arg2){
    var t1=0,t2=0,r1,r2;
    try{t1=arg1.toString().split(".")[1].length}catch(e){}
    try{t2=arg2.toString().split(".")[1].length}catch(e){}
    with(Math){
        r1=Number(arg1.toString().replace(".",""));
        r2=Number(arg2.toString().replace(".",""));
        return (r1/r2)*pow(10,t2-t1);
    }
}

/**
 * 获取两个数组的交集
 * @param a
 * @param b
 * @returns {Array}
 */
function arrayIntersection(a, b) {
    var ai = 0, bi = 0;
    var result = [];
    while (ai < a.length && bi < b.length) {
        if (a[ai] < b[bi]) { ai++; }
        else if (a[ai] > b[bi]) { bi++; }
        else {
            result.push(a[ai]);
            ai++;
            bi++;
        }
    }
    return result;
}

var getBrowserInfo = function(ua){
    var sTempInfo,
        oBrowserInfo = {},
        sBrowserString = ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];

    //trident check (IE11 and below)
    if(/trident/i.test(sBrowserString[1])) {
        sTempInfo = /\brv[ :]+(\d+)/g.exec(ua) || [];
        oBrowserInfo.sName = 'MSIE';
        oBrowserInfo.sVersion = sTempInfo[1];
        return oBrowserInfo;
    }
    if(sBrowserString[1]=== 'Chrome') {
        sTempInfo = ua.match(/\b(OPR|Edge)\/(\d+)/);
        //Opera/Edge case:
        if(sTempInfo !== null) {
            if(sTempInfo.indexOf('Edge')) {
                oBrowserInfo.sName = 'MSIE';   //mark ms edge browser as MSIE
            } else {
                oBrowserInfo.sName = 'Opera';
            }
            oBrowserInfo.sVersion = sTempInfo.slice(1);
            return oBrowserInfo;
        }
    }
    sBrowserString = sBrowserString[2]? [sBrowserString[1], sBrowserString[2]]: [navigator.appName, navigator.appVersion, '-?'];
    sTempInfo = ua.match(/version\/(\d+)/i);

    if(sTempInfo!== null) {
        sBrowserString.splice(1, 1, sTempInfo[1]);
    }
    oBrowserInfo.sName = sBrowserString[0];
    oBrowserInfo.sVersion = sBrowserString[1];
    return oBrowserInfo;
};
