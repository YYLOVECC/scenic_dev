//树形插件
/*
 data:[]//json数组
 ckid:""//默认选中的值，radio是单个数字，checkbox是个数组 :[1,2,3]
 fid:"parent_id", //指定赋值的input框id
 fun_name:"ckval", //指定input点击后操作的函数名称，会返回id 和文本2个值
 radio:true/false  单选还是多选 默认是单选
 only:true/fasle  只能选择一个，默认false
 */

(function ($) {
    $.fn.vtree = function (q) {
        var timer = undefined;
        q = $.extend({
                data: [],
                ckid: "",
                fid: "parent_id",
                fun_name: "ckval",
                radio: true,
                only: false,
                radio_name: 'dan_radio',
                pselect: true
            },
            q || {});
        return this.each(function () {
            var tree = $(this);
            var nodes = q.data;
            nodes = nodes.sort(function (a, b) {
                return (a.pid - b.pid);
            });
            var f_name = q.fun_name;
            var isradio = q.radio;
            var ckval = [];
            var oly = q.only;
            var pselect = q.pselect;

            //树形结构
            var builddata = function () {
                var source = [];
                var items = [];
                for (i = 0; i < nodes.length; i++) {
                    var item = nodes[i];
                    var val = item["val"];
                    var parentid = item["pid"];
                    var id = item["id"];
                    var n = item["names"];
                    var oly = q.only;

                    if (items[parentid]) {
                        var item = {pid: parentid, id: id, val: val, names: n, item: item};
                        if (!items[parentid].items) {
                            items[parentid].items = [];
                        }
                        items[parentid].items[items[parentid].items.length] = item;
                        items[id] = item;
                    }
                    else {
                        items[id] = {pid: parentid, id: id, val: val, names: n, item: item};
                        source[id] = items[id];
                    }
                }
                return source;
            };
            var source = builddata();
            var buildUL = function (parent, items) {
                $.each(items, function () {
                    if (this.val) {
                        var p = this.items && this.items.length > 0;
                        var li;
                        var rad;
                        var na;
                        if (isradio) {

                            if (oly) {
                                rad = "type='radio' name='" + q.radio_name + "'";
                            } else {
                                rad = "type='radio' name='" + this.names + "'";
                            }
                        } else {
                            rad = "type='checkbox' name='checkbox'";
                        }
                        if (p) {
                            li = $("<li><a href='javascript:;' class='fvg fon' onClick='tre(this)'></a><label class='rlr_on' onClick=\"" + f_name + "('" + this.id + "','" + this.val + "')\"><input " + rad + " value='" + this.id + "' class='rtr_on'>" + this.val + "</label></li>");
                        } else {
                            li = $("<li><label onClick=\"" + f_name + "('" + this.id + "','" + this.val + "')\"><input " + rad + " value='" + this.id + "'>" + this.val + "</label></li>");
                        }


                        li.appendTo(parent);
                        if (this.items && this.items.length > 0) {
                            var ul = $("<ul class='tree'></ul>");
                            ul.appendTo(li);
                            buildUL(ul, this.items);
                        }
                    }
                });
            };


            var ul = $("<ul class='tree' id='vtree'></ul>");
            tree.append(ul);
            buildUL(ul, source);

            var $treep = tree.find("input");

            if (isradio) {//单选
                $treep.bind("click", function () {
                    var i = $(this).val();
                    var c = $(this).prop("checked");
                    $treep.prop("checked", false);
                    $(this).prop("checked", true);
                    if (!oly) {
                        xck($(this));
                    }
                    $("#" + q.fid).val(i);
                });

            } else {//多选
                $treep.bind("click", function () {
                    var i = $(this).val();
                    var c = $(this).attr("checked");
                    if (pselect) {
                        updateParent(this);
                    }

                    updateChildren(this);
                    ckval = [];
                    var ckva = tree.find("input:checked");
                    var ck_len = ckva.length;

                    ckva.each(function () {
                        var kk = $(this).val();
                        ckval.push(kk);
                    });

                    var z = ckval.join(",");
                    $("#" + q.fid).val(z);
                });

            }

//循环查找父节点
            function xck(obj) {
                var p1 = obj.parent().parent().parent().prev();
                var p2 = p1.parent().parent().prev();
                var p3 = p2.parent().parent().prev();

                if (p1.attr("class") == "rlr_on") {
                    p1.find(".rtr_on").prop("checked", true);
                }
                if (p2.attr("class") == "rlr_on") {
                    p2.find(".rtr_on").prop("checked", true);
                }
                if (p3.attr("class") == "rlr_on") {
                    p3.find(".rtr_on").prop("checked", true);
                }
            }


//默认选中..
            function checkone(id) {
                var pt = tree.find("input");
                var len = pt.length;
                pt.each(function () {
                    var i = $(this).val();
                    if (i == id) {
                        $(this).prop("checked", true);
                        xck($(this));
                    }
                });
            }

            function chonly(id) {
                var pt = tree.find("input");
                pt.each(function () {
                    var i = $(this).val();
                    if (i == id) {
                        $(this).prop("checked", true);
                    }
                });
            }

//默认选中checkbox..
            function checkon(id) {
                var pt = tree.find("input");
                pt.each(function () {
                    var i = $(this).val();
                    if (Arrhas(id, i)) {
                        $(this).prop("checked", true);
                    }
                });
            }


            if (q.ckid != "") {
                if (isradio) {
                    if (!oly) {
                        checkone(q.ckid);
                    } else {
                        chonly(q.ckid);
                    }
                } else {
                    checkon(q.ckid);
                }
            }


        });
    }
})(jQuery);

//展开折叠raido
function tre(obj) {
    var a = $(obj);
    var i = a.hasClass("fon");
    var s = a.next().next();
    if (i) {
        a.removeClass("fon");
        s.hide();
    } else {
        a.addClass("fon");
        s.show();
    }
}

function ckval(id, val) {
    //空函数防止tree没指定时报错
}

//数组操作
Array.prototype.indexOf = function (val) {
    for (var i = 0; i < this.length; i++) {
        if (this[i] == val) return i;
    }
    return -1;
};
Array.prototype.aremove = function (val) {
    var index = this.indexOf(val);
    if (index > -1) {
        this.splice(index, 1);
    }
};


function Arrhas(arr, val) {
    if (arr.indexOf(val) !== -1) {
        return true;
    } else {
        return false;
    }
}
//checkbox操作
function updateChildren(c) {
    var state = c.checked;
    var pla = $(c).parent("label");
    var plat = pla[0];
    var childDivs = $(plat).next("ul");
    if (childDivs.length > 0) {
        var childDiv = childDivs[0];
        $(childDiv).contents().find(":checkbox").each(function () {
            this.checked = state;
        });
    }

}

function updateParent(c) {
    var parentDiv = $(c).parent().parent().parent("ul");
    var pd = parentDiv.contents();
    //var linput=pd.find("input").length;
    var pdiv = pd.find("input:checked").length;
    /*子节点全部选中才选中父节点
     if(pdiv!=linput){
     return;
     }*/
    if (pdiv < 1) {
        return;
    }
    var pla = parentDiv.prev("label");
    if (pla.length > 0) {
        var plat = pla[0];
        var checkbox = $(plat).find('.rtr_on');
        checkbox[0].checked = true;
        //var parentCheckboxes = $(plat).find(":checkbox");
        //var parentCheckbox = parentCheckboxes[0];
        //parentCheckbox.checked = hasSelected ;
    }

}