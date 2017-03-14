/**
 * Created by licong on 2017/2/8.
 */
/**
 *登录验证
 */
function get_valid() {
    var captcha_tips = $('#captcha_tips');
    captcha_tips.hide();
    captcha_tips.attr('class', 'control-label-left text-danger');
    //邮箱
    var email = $.trim($('#email').val());
    if(!email || email==undefined || email==null){
        captcha_tips.text('邮箱不能为空');
        captcha_tips.show();
        return false;
    }
    //密码
    var password = $.trim($('#password').val());
    if(!password || password==undefined || password==null){
        captcha_tips.text('密码不能为空');
        captcha_tips.show();
        return false;
    }

}
