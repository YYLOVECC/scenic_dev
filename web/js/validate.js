(function ($) {
    var errorobj = null, msgobj, msghidden = true, tipmsg = {
        tit: "提示信息",
        w: "",
        r: "",
        c: "正在检测…",
        s: "",
        v: "所填信息没有经过验证，请稍后…",
        p: "正在提交数据…",
        err: "出错了！请检查提交地址或返回数据格式是否正确！"
    }, creatMsgbox = function () {
        if ($("#Validform_msg").length !== 0) {
            return false
        }
        msgobj = $('<div id="Validform_msg"><div class="Validform_title">' + tipmsg.tit + '<a class="Validform_close" href="javascript:void(0);">&chi;</a></div><div class="Validform_info"></div><div class="iframe"><iframe frameborder="0" scrolling="no" height="100%" width="100%"></iframe></div></div>').appendTo("body");
        msgobj.find("a.Validform_close").click(function () {
            msgobj.hide();
            msghidden = true;
            if (errorobj) {
                errorobj.focus().addClass("Validform_error")
            }
            return false
        }).focus(function () {
            this.blur()
        });
        $(window).bind("scroll resize", function () {
            if (!msghidden) {
                var left = ($(window).width() - msgobj.outerWidth()) / 2, top = ($(window).height() - msgobj.outerHeight()) / 2, topTo = (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop) + (top > 0 ? top : 0);
                msgobj.animate({left: left, top: topTo}, {duration: 400, queue: false})
            }
        })
    };
    $.Tipmsg = tipmsg;
    $.fn.Validform = function (settings) {
        var defaults = {};
        settings = $.extend({}, $.fn.Validform.sn.defaults, settings);
        settings.datatype && $.extend($.Datatype, settings.datatype);
        this.each(function (index) {
            var $this = $(this), posting = false;
            $this.find("[tip]").each(function () {
                var defaultvalue = $(this).attr("tip");
                var altercss = $(this).attr("altercss");
                $(this).focus(function () {
                    if ($(this).val() == defaultvalue) {
                        $(this).val('');
                        if (altercss) {
                            $(this).removeClass(altercss)
                        }
                    }
                }).blur(function () {
                    if ($.trim($(this).val()) === '') {
                        $(this).val(defaultvalue);
                        if (altercss) {
                            $(this).addClass(altercss)
                        }
                    }
                })
            });
            $this.find("input[recheck]").each(function () {
                var _this = $(this);
                var recheckinput = $this.find("input[name='" + $(this).attr("recheck") + "']");
                recheckinput.bind("keyup", function () {
                    if (recheckinput.val() == _this.val() && recheckinput.val() != "") {
                        if (recheckinput.attr("tip")) {
                            if (recheckinput.attr("tip") == recheckinput.val()) {
                                return false
                            }
                        }
                        _this.trigger("blur")
                    }
                }).bind("blur", function () {
                    if (recheckinput.val() != _this.val() && _this.val() != "") {
                        if (_this.attr("tip")) {
                            if (_this.attr("tip") == _this.val()) {
                                return false
                            }
                        }
                        _this.trigger("blur")
                    }
                })
            });
            if (settings.usePlugin) {
                if (settings.usePlugin.swfupload) {
                    var swfuploadinput = $this.find("input[plugin='swfupload']").val(""), custom = {
                        custom_settings: {
                            form: $this,
                            showmsg: function (msg, type) {
                                $.fn.Validform.sn.showmsg(msg, settings.tiptype, {
                                    obj: swfuploadinput,
                                    curform: $this,
                                    type: type
                                })
                            }
                        }
                    };
                    custom = $.extend(true, {}, settings.usePlugin.swfupload, custom);
                    if (typeof(swfuploadhandler) != "undefined") {
                        swfuploadhandler.init(custom, index)
                    }
                }
                if (settings.usePlugin.datepicker) {
                    if (settings.usePlugin.datepicker.format) {
                        Date.format = settings.usePlugin.datepicker.format;
                        delete settings.usePlugin.datepicker.format
                    }
                    if (settings.usePlugin.datepicker.firstDayOfWeek) {
                        Date.firstDayOfWeek = settings.usePlugin.datepicker.firstDayOfWeek;
                        delete settings.usePlugin.datepicker.firstDayOfWeek
                    }
                    var datepickerinput = $this.find("input[plugin='datepicker']");
                    settings.usePlugin.datepicker.callback && datepickerinput.bind("dateSelected", function () {
                        var d = new Date($.event._dpCache[this._dpId].getSelected()[0]).asString(Date.format);
                        settings.usePlugin.datepicker.callback(d, this)
                    });
                    datepickerinput.datePicker(settings.usePlugin.datepicker)
                }
                if (settings.usePlugin.passwordstrength) {
                    settings.usePlugin.passwordstrength.showmsg = function (obj, msg, type) {
                        $.fn.Validform.sn.showmsg(msg, settings.tiptype, {
                            obj: obj,
                            curform: $this,
                            type: type,
                            sweep: settings.tipSweep
                        }, "hide")
                    };
                    $this.find("input[plugin*='passwordStrength']").passwordStrength(settings.usePlugin.passwordstrength)
                }
            }
            $this.find("[datatype]").blur(function () {
                var flag = true;
                flag = $.fn.Validform.sn.checkform($(this), $this, {
                    type: settings.tiptype,
                    sweep: settings.tipSweep
                }, "hide");
                if (!flag) {
                    return false
                }
                if (typeof(flag) != "boolean") {
                    $(this).removeClass("Validform_error");
                    return false
                }
                flag = $.fn.Validform.sn.regcheck($(this).attr("datatype"), $(this).val(), $(this), $this);
                if (!flag) {
                    if ($(this).attr("ignore") === "ignore" && ($(this).val() === "" || $(this).val() === $(this).attr("tip"))) {
                        if (settings.tiptype == 2) {
                            var tempobj = $(this).next();
                            if (tempobj.is(".Validform_right")) {
                                tempobj.text("")
                            }
                            tempobj.removeClass().addClass("Validform_checktip")
                        } else if (typeof settings.tiptype == "function") {
                            (settings.tiptype)("", {obj: $(this), type: 4}, $.fn.Validform.sn.cssctl)
                        }
                        flag = true;
                        errorobj = null;
                        return true
                    }
                    $.fn.Validform.sn.showmsg($(this).attr("errormsg") || tipmsg.w, settings.tiptype, {
                        obj: $(this),
                        curform: $this,
                        type: 3,
                        sweep: settings.tipSweep
                    }, "hide")
                } else {
                    if ($(this).attr("ajaxurl")) {
                        var inputobj = $(this);
                        var subpost = arguments[1];
                        if (inputobj.attr("valid") == "posting") {
                            return false
                        }
                        inputobj.attr("valid", "posting");
                        $.fn.Validform.sn.showmsg(tipmsg.c, settings.tiptype, {
                            obj: inputobj,
                            curform: $this,
                            type: 1,
                            sweep: settings.tipSweep
                        }, "hide");
                        $.ajax({
                            type: "POST",
                            url: inputobj.attr("ajaxurl"),
                            data: "param=&" + $(this).attr("id") + "=" + $(this).val(),
                            dataType: "text",
                            success: function (s) {
                                if ($.trim(s) == "y") {
                                    inputobj.attr("valid", "true");
                                    $.fn.Validform.sn.showmsg(tipmsg.r, settings.tiptype, {
                                        obj: inputobj,
                                        curform: $this,
                                        type: 2,
                                        sweep: settings.tipSweep
                                    }, "hide");
                                    if (subpost === "postform") {
                                        $this.trigger("submit")
                                    }
                                } else {
                                    inputobj.attr("valid", s);
                                    $.fn.Validform.sn.showmsg(s, settings.tiptype, {
                                        obj: inputobj,
                                        curform: $this,
                                        type: 3,
                                        sweep: settings.tipSweep
                                    })
                                }
                            },
                            error: function () {
                                inputobj.attr("valid", tipmsg.err);
                                $.fn.Validform.sn.showmsg(tipmsg.err, settings.tiptype, {
                                    obj: inputobj,
                                    curform: $this,
                                    type: 3,
                                    sweep: settings.tipSweep
                                })
                            }
                        })
                    } else {
                        $.fn.Validform.sn.showmsg(tipmsg.r, settings.tiptype, {
                            obj: $(this),
                            curform: $this,
                            type: 2,
                            sweep: settings.tipSweep
                        }, "hide")
                    }
                }
            });
            $this.find(":checkbox[datatype],:radio[datatype]").each(function () {
                var _this = $(this);
                var name = _this.attr("name");
                $this.find("[name='" + name + "']").filter(":checkbox,:radio").bind("click", function () {
                    _this.trigger("blur")
                })
            });
            var subform = function () {
                settings.beforeCheck && settings.beforeCheck($this);
                var flag = true, inflag = true;
                if (posting) {
                    return false
                }
                $this.find("[datatype]").each(function () {
                    inflag = $.fn.Validform.sn.checkform($(this), $this, {
                        type: settings.tiptype,
                        sweep: settings.tipSweep
                    });
                    if (!inflag) {
                        if (!settings.showAllError) {
                            errorobj.focus();
                            flag = false;
                            return false
                        }
                        flag && (flag = false);
                        return true
                    }
                    if (typeof(inflag) != "boolean") {
                        return true
                    }
                    inflag = $.fn.Validform.sn.regcheck($(this).attr("datatype"), $(this).val(), $(this), $this);
                    if (inflag && $(this).is("[datatype*='option_']")) {
                        $(this).trigger("blur")
                    }
                    if (!inflag) {
                        if ($(this).attr("ignore") === "ignore" && ($(this).val() === "" || $(this).val() === $(this).attr("tip"))) {
                            errorobj = null;
                            return true
                        }
                        $.fn.Validform.sn.showmsg($(this).attr("errormsg") || tipmsg.w, settings.tiptype, {
                            obj: $(this),
                            curform: $this,
                            type: 3,
                            sweep: settings.tipSweep
                        });
                        if (!settings.showAllError) {
                            errorobj.focus();
                            flag = false;
                            return false
                        }
                        flag && (flag = false);
                        return true
                    }
                    if ($(this).attr("ajaxurl")) {
                        if ($(this).attr("valid") != "true") {
                            var thisobj = $(this);
                            $.fn.Validform.sn.showmsg(tipmsg.v, settings.tiptype, {
                                obj: thisobj,
                                curform: $this,
                                type: 3,
                                sweep: settings.tipSweep
                            });
                            if (!msghidden || settings.tiptype != 1) {
                                setTimeout(function () {
                                    thisobj.trigger("blur", ["postform"])
                                }, 1500)
                            }
                            if (!settings.showAllError) {
                                flag = false;
                                return false
                            }
                            flag && (flag = false);
                            return true
                        } else {
                            $.fn.Validform.sn.showmsg(tipmsg.r, settings.tiptype, {
                                obj: $(this),
                                curform: $this,
                                type: 2,
                                sweep: settings.tipSweep
                            }, "hide")
                        }
                    }
                });
                if (settings.showAllError) {
                    $this.find(".Validform_error:first").focus()
                }
                if (flag && !posting) {
                    errorobj = null;
                    if (settings.postonce) {
                        posting = true
                    }
                    settings.beforeSubmit && settings.beforeSubmit($this);
                    if (settings.ajaxPost) {
                        $.fn.Validform.sn.showmsg(tipmsg.p, settings.tiptype, {
                            obj: $this,
                            curform: $this,
                            type: 1,
                            sweep: settings.tipSweep
                        }, "alwaysshow");
                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: $this.attr("action"),
                            data: $this.serializeArray(),
                            success: function (data) {
                                if (data.status === "y") {
                                    $.fn.Validform.sn.showmsg(data.info, settings.tiptype, {
                                        obj: $this,
                                        curform: $this,
                                        type: 2,
                                        sweep: settings.tipSweep
                                    }, "alwaysshow")
                                } else {
                                    posting = false;
                                    $.fn.Validform.sn.showmsg(data.info, settings.tiptype, {
                                        obj: $this,
                                        curform: $this,
                                        type: 3,
                                        sweep: settings.tipSweep
                                    }, "alwaysshow")
                                }
                                settings.callback && settings.callback(data)
                            },
                            error: function () {
                                posting = false;
                                $.fn.Validform.sn.showmsg(tipmsg.err, settings.tiptype, {
                                    obj: $this,
                                    curform: $this,
                                    type: 3,
                                    sweep: settings.tipSweep
                                }, "alwaysshow")
                            }
                        });
                        return false
                    } else {
                        settings.callback ? settings.callback($this) : $this.get(0).submit()
                    }
                }
            };
            settings.btnSubmit && $this.find(settings.btnSubmit).bind("click", subform);
            $this.submit(function () {
                subform();
                return false
            });
            $this.find("input[type='reset']").click(function () {
                $this.find(".Validform_right").text("");
                $this.find(".Validform_checktip").removeClass("Validform_wrong Validform_right Validform_loading");
                $this.find(".Validform_error").removeClass("Validform_error");
                $this.find("input:first").focus()
            })
        });
        if (settings.tiptype == 1 || (settings.tiptype == 2 && settings.ajaxPost)) {
            creatMsgbox()
        }
    };
    $.fn.Validform.sn = {
        defaults: {tiptype: 1, tipSweep: false, showAllError: false, postonce: false, ajaxPost: false},
        toString: Object.prototype.toString,
        regcheck: function (type, gets, obj, curform) {
            if (this.toString.call($.Datatype[type]) == "[object Function]") {
                return ($.Datatype[type])(gets, obj, curform)
            }
            if (!(type in $.Datatype)) {
                var mac = type.match($.Datatype["match"]), temp;
                reg:for (var name in $.Datatype) {
                    temp = name.match($.Datatype["match"]);
                    if (!temp) {
                        continue reg
                    }
                    if (mac[1] === temp[1]) {
                        var str = $.Datatype[name].toString();
                        var regxp = new RegExp("\\{" + temp[2] + "," + temp[3] + "\\}");
                        str = str.replace(regxp, "{" + mac[2] + "," + mac[3] + "}").replace(/^\//, "").replace(/\/$/, "");
                        $.Datatype[type] = new RegExp(str);
                        break reg
                    }
                }
            }
            if (this.toString.call($.Datatype[type]) == "[object RegExp]") {
                return $.Datatype[type].test(gets)
            }
            return false
        },
        showmsg: function (msg, type, o, show) {
            if (o.type == 3 && !o.obj.is("form")) {
                errorobj = o.obj
            } else {
                errorobj && errorobj.removeClass("Validform_error");
                errorobj = null
            }
            errorobj && errorobj.addClass("Validform_error");
            if (typeof type == "function") {
                if (!(o.sweep && show == "hide")) {
                    type(msg, o, this.cssctl)
                }
                return false
            }
            if (type == 1 || show == "alwaysshow") {
                msgobj.find(".Validform_info").text(msg)
            }
            if (type == 1 && show != "hide" || show == "alwaysshow") {
                msghidden = false;
                msgobj.find(".iframe").css("height", msgobj.outerHeight());
                var left = ($(window).width() - msgobj.outerWidth()) / 2;
                var top = ($(window).height() - msgobj.outerHeight()) / 2;
                top = (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop) + (top > 0 ? top : 0);
                msgobj.css({"left": left}).show().animate({top: top}, 100)
            }
            if (type == 2 && o.obj) {
                o.obj.next().text(msg);
                this.cssctl(o.obj.next(), o.type)
            }
        },
        checkform: function (obj, parentobj, tiptype, show) {
            var errormsg = obj.attr("errormsg") || tipmsg.w, inputname;
            if (obj.is("[datatype='radio']")) {
                inputname = obj.attr("name");
                var radiovalue = parentobj.find(":radio[name='" + inputname + "']:checked").val();
                if (!radiovalue) {
                    this.showmsg(errormsg, tiptype.type, {
                        obj: obj,
                        curform: parentobj,
                        type: 3,
                        sweep: tiptype.sweep
                    }, show);
                    return false
                }
                this.showmsg(tipmsg.r, tiptype.type, {
                    obj: obj,
                    curform: parentobj,
                    type: 2,
                    sweep: tiptype.sweep
                }, "hide");
                return "radio"
            }
            if (obj.is("[datatype='checkbox']")) {
                inputname = obj.attr("name");
                var checkboxvalue = parentobj.find(":checkbox[name='" + inputname + "']:checked").val();
                if (!checkboxvalue) {
                    this.showmsg(errormsg, tiptype.type, {
                        obj: obj,
                        curform: parentobj,
                        type: 3,
                        sweep: tiptype.sweep
                    }, show);
                    return false
                }
                this.showmsg(tipmsg.r, tiptype.type, {
                    obj: obj,
                    curform: parentobj,
                    type: 2,
                    sweep: tiptype.sweep
                }, "hide");
                return "checkbox"
            }
            if (obj.is("[datatype='select']")) {
                if (!obj.val()) {
                    this.showmsg(errormsg, tiptype.type, {
                        obj: obj,
                        curform: parentobj,
                        type: 3,
                        sweep: tiptype.sweep
                    }, show);
                    return false
                }
                this.showmsg(tipmsg.r, tiptype.type, {
                    obj: obj,
                    curform: parentobj,
                    type: 2,
                    sweep: tiptype.sweep
                }, "hide");
                return "select"
            }
            if (obj.is("[datatype*='option_']")) {
                obj.removeClass("Validform_error");
                errorobj = null;
                return true
            }
            var defaultvalue = obj.attr("tip");
            if (($.trim(obj.val()) === "" || obj.val() === defaultvalue) && obj.attr("ignore") != "ignore") {
                this.showmsg(obj.attr("nullmsg") || tipmsg.s, tiptype.type, {
                    obj: obj,
                    curform: parentobj,
                    type: 3,
                    sweep: tiptype.sweep
                }, show);
                return false
            }
            if (obj.attr("recheck")) {
                var theother = parentobj.find("input[name='" + obj.attr("recheck") + "']:first");
                if (obj.val() != theother.val()) {
                    this.showmsg(errormsg, tiptype.type, {
                        obj: obj,
                        curform: parentobj,
                        type: 3,
                        sweep: tiptype.sweep
                    }, show);
                    return false
                }
                this.showmsg(tipmsg.r, tiptype.type, {
                    obj: obj,
                    curform: parentobj,
                    type: 2,
                    sweep: tiptype.sweep
                }, "hide")
            }
            obj.removeClass("Validform_error");
            errorobj = null;
            return true
        },
        cssctl: function (obj, status) {
            switch (status) {
                case 1:
                    obj.removeClass("Validform_right Validform_wrong").addClass("Validform_checktip Validform_loading");
                    break;
                case 2:
                    obj.removeClass("Validform_wrong Validform_loading").addClass("Validform_checktip Validform_right");
                    break;
                case 4:
                    obj.removeClass("Validform_right Validform_wrong Validform_loading").addClass("Validform_checktip");
                    break;
                default:
                    obj.removeClass("Validform_right Validform_loading").addClass("Validform_checktip Validform_wrong")
            }
        }
    };
    $.Showmsg = function (msg) {
        creatMsgbox();
        $.fn.Validform.sn.showmsg(msg, 1, {})
    };
    $.Hidemsg = function () {
        msgobj.hide();
        msghidden = true
    };
    $.Datatype = {
        "match": /^(.+?)(\d+)-(\d+)$/,
        "*": /.+/,
        "*6-16": /^.{6,16}$/,
        "n": /^\d+$/,
        "n6-16": /^\d{6,16}$/,
        "s": /^[\u4E00-\u9FA5\uf900-\ufa2d\w\.\s]+$/m,
        "s6-18": /^[\u4E00-\u9FA5\uf900-\ufa2d\w\.\s]{6,18}$/,
        "p": /^[0-9]{6}$/,
        "m": /^13[0-9]{9}$|15[0-9]{9}$|18[0-9]{9}$/,
        "e": /^\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/,
        "url": /^(\w+:\/\/)?\w+(\.\w+)+$/
    }
})(jQuery);