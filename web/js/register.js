/**
 * Created by licong on 2017/2/8.
 */
var email_register = {};
/**
 * 验证邮箱
 * @returns {boolean}
 */
function verify_email(ele){
    var email_obj = $(ele);
    var email_tips_obj = email_obj.parent().next();
    var email = $.trim(email_obj.val());
    email_tips_obj.hide();
    if(!email){
        email_tips_obj.text('邮箱不能为空');
        email_tips_obj.show();
        return false;
    }
    email = email.match(/\S+/g).join('');
    var regexp = /^([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+\.[a-zA-Z]{2,3}$/;
    //邮箱格式验证
    if(!regexp.test(email)){
        email_tips_obj.text('邮箱格式有误');
        email_tips_obj.show();
        return false
    }

    //邮箱是否已使用
    var key = 'email_' +_md5(email);
    if(key in email_register) {
        if (email_register[key].success == true) {
            email_tips_obj.text('邮箱已存在');
            email_tips_obj.show();
            return false;
        }else{
            return true;
        }
    }else{
        $.ajax({
            url: '/users/ajax/search_email',
            dataType: 'json',
            type: 'post',
            async: false,
            data: {'email': email},
            success: function (data) {
                email_register[key] = data;
                if (data.success) {
                    email_tips_obj.text('邮箱已存在');
                    email_tips_obj.show();
                    return false;
                }else{
                    email_tips_obj.hide();
                    return true;
                }
            }
        });
    }
}

/**
 * 将每个字符拆分成唯一值
 * @param str
 * @returns {string}
 * @private
 */
var _md5 = function (str) {
    str = (str || "").toString();
    var md5 = "";
    for (var i = 0, len = str.length; i < len; i++) {
        md5 += str.charAt(i).charCodeAt;
    }
    return md5;
};

/**
 * 验证用户名
 */
function verify_name(ele){
    var name_obj = $(ele);
    var user_name = $.trim(name_obj.val());
    var name_tips_obj = name_obj.parent().next();
    name_tips_obj.hide();
    if(!user_name || user_name==undefined || user_name==null){
        name_tips_obj.text('用户名不能为空');
        name_tips_obj.show();
        return false;
    }
    user_name = user_name.match(/\S+/g).join('');
    var regexp = eval('/' + "^[A-Za-z0-9\u4E00-\u9FA5_]{2,20}$" + '/i');
    if(!regexp.test(user_name)){
        name_tips_obj.text('长度为2-20个字符');
        name_tips_obj.show();
        return false;
    }
    return true;
}

/**
 * 验证密码强度
 */
function verify_password(ele) {
    var password_obj = $(ele);
    var password = $.trim(password_obj.val());
    var password_tips_obj = password_obj.parent().next();
    password_tips_obj.hide();
    if (!password || password == undefined || password == null) {
        password_tips_obj.text('请填写用户密码');
        password_tips_obj.show();
        return false;
    }
    //检测是否有中文
    var regexp = /[^\x00-\x80]/;
    if (regexp.test(password)) {
        password_tips_obj.text('密码不能包含中文及全角符号');
        password_tips_obj.show();
        return false;
    }
    if(password.length<9 || password.length>16){
        password_tips_obj.text('密码必须为9-16位的字母、数字或特殊字符组成，区分大小写');
        password_tips_obj.show();
        return false;
    }
    //检测是否有全角符合
    //检测
    //检测密码强度
    if(checkStrong(password)<2){
        password_tips_obj.text('密码太简单，请重新填写');
        password_tips_obj.show();
        return false;
    }
    return true;
}


function checkStrong(sValue) {
    var modes = 0;
    //正则表达式验证符合要求的
    if (sValue.length < 1) return modes;
    if (/\d/.test(sValue)) modes++; //数字
    if (/[a-z]/.test(sValue)) modes++; //小写
    if (/[A-Z]/.test(sValue)) modes++; //大写
    if (/\W/.test(sValue)) modes++; //特殊字符

    //逻辑处理
    switch (modes) {
        case 1:
            return 1;
            break;
        case 2:
            return 2;
        case 3:
        case 4:
            return sValue.length < 12 ? 3 : 4;
            break;
    }
}
