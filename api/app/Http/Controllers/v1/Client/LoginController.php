<?php
/** +----------------------------------------------------------------------
 * | DSSHOP [ 轻量级易扩展低代码开源商城系统 ]
 * +----------------------------------------------------------------------
 * | Copyright (c) 2020~2023 https://www.dswjcms.com All rights reserved.
 * +----------------------------------------------------------------------
 * | Licensed 未经许可不能去掉DSSHOP相关版权
 * +----------------------------------------------------------------------
 * | Author: Purl <383354826@qq.com>
 * +----------------------------------------------------------------------
 */
namespace App\Http\Controllers\v1\Client;

use App\Code;
use App\common\RedisService;
use App\Models\v1\MiniProgram;
use App\Models\v1\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Webpatser\Uuid\Uuid;

/**
 * @group [CLIENT]Login(登录)
 * Class LoginController
 * @package App\Http\Controllers\v1\Client
 */
class LoginController extends Controller
{
    use AuthenticatesUsers;
    /**
     * Login
     * 登录
     * @param Request $request
     * @return string
     * @queryParam  cellphone int 手机号
     * @queryParam  password string 密码
     */
    public function login(Request $request)
    {
        if (!$request->has('cellphone')) {
            return resReturn(0, __('hint.error.not_null', ['attribute' => __('user.cellphone')]), Code::CODE_WRONG);
        }
        if (!$request->has('password')) {
            return resReturn(0, __('hint.error.not_null', ['attribute' => __('user.password')]), Code::CODE_WRONG);
        }
        $user = User::where('cellphone', $request->cellphone)->first();
        if (!$user) {
            return resReturn(0, __('user.cellphone.error.unregistered'), Code::CODE_WRONG);
        }
        if ($user->unsubscribe == User::USER_UNSUBSCRIBE_YES) {
            return resReturn(0, __('user.cellphone.error.cancelled'), Code::CODE_WRONG);
        }
        if ($user->state == User::USER_STATE_FORBID) {
            return resReturn(0, __('user.cellphone.error.forbidden'), Code::CODE_WRONG);
        }
        if (!Hash::check($request->password, $user->password)) {
            return resReturn(0, __('hint.error.mistake', ['attribute' => __('user.password')]), Code::CODE_WRONG);
        }
        if (!$user->api_token) {
            $user->api_token = hash('sha256', Str::random(60));
        }
        $user->updated_at = Carbon::now()->toDateTimeString();
        $user->save();
        $client = new Client();
        $url = request()->root() . '/oauth/token';
        $params = array_merge(config('passport.web.proxy'), [
            'username' => $request->cellphone,
            'password' => $request->password,
        ]);
        $respond = $client->post($url, ['form_params' => $params]);
        $access_token = json_decode($respond->getBody()->getContents(), true);
        $access_token['refresh_expires_in'] = config('passport.refresh_expires_in') / 60 / 60 / 24;
        $access_token['nickname'] = $user->nickname;
        $access_token['cellphone'] = $user->cellphone;
        $access_token['wechat'] = $user->wechat;
        return resReturn(1, $access_token);
    }

    /**
     * token刷新
     * @param Request $request
     * @return string
     */
    public function refresh(Request $request)
    {
        $client = new Client();
        $url = request()->root() . '/oauth/token';
        $access_token = '';
        if ($request->refresh_token) {
            $params = array_merge(config('passport.web.refresh'), [
                'refresh_token' => $request->refresh_token,
            ]);
            $respond = $client->post($url, ['form_params' => $params]);
            $access_token = json_decode($respond->getBody()->getContents(), true);
        }
        return resReturn(1, $access_token);
    }

    /**
     * 授权登录
     * @param Request $request
     * @return array
     * @queryParam  iv array iv
     */
    public function authorization(Request $request)
    {
        if ($request->has('iv')) {
            $openid = $request->header('openid');
            if (!$openid) {
                return resReturn(0, __('common.arguments'), Code::CODE_MISUSE);
            }
            $MiniProgram = new MiniProgram();
            $miniPhoneNumber = $MiniProgram->miniPhoneNumber($request->platform, $request->session_key, $request->iv, $request->encryptedData);
            if ($miniPhoneNumber['result'] == 'error') {
                return resReturn(0, $miniPhoneNumber['msg'], Code::CODE_MISUSE);
            }
            $User = User::where('cellphone', $miniPhoneNumber['purePhoneNumber'])->first();
            $client = new Client();
            $url = request()->root() . '/oauth/token';
            if (!$User) {
                $return = DB::transaction(function () use ($request, $miniPhoneNumber, $openid) {
                    $password = substr(MD5(time()), 5, 6);  //随机生成密码
                    $redis = new RedisService();
                    $redis->setex('password.register.' . $miniPhoneNumber['purePhoneNumber'], 5, $password);
                    $user = new User();
                    $user->name = $miniPhoneNumber['purePhoneNumber'];
                    $user->cellphone = $miniPhoneNumber['purePhoneNumber'];
                    $user->password = bcrypt($password);
                    $user[strtolower($request->platform)] = $openid;
                    $user->api_token = hash('sha256', Str::random(60));
                    $user->uuid = (string)Uuid::generate();
                    $user->save();
                    return [
                        'state' => 1,
                        'data' => $user
                    ];
                }, 5);
                if ($return['state'] == 1) {
                    $params = array_merge(config('passport.web.proxy'), [
                        'username' => $return['data']->name,
                        'password' => $return['data']->password,
                    ]);
                    $respond = $client->post($url, ['form_params' => $params]);
                    $access_token = json_decode($respond->getBody()->getContents(), true);
                    $access_token['refresh_expires_in'] = config('passport.refresh_expires_in') / 60 / 60 / 24;
                    $access_token['nickname'] = $return['data']->nickname;
                    $access_token['cellphone'] = $return['data']->cellphone;
                    $access_token['portrait'] = $return['data']->portrait;
                    $access_token['wechat'] = $return['data']->wechat;
                    return resReturn(1, $access_token);
                } else {
                    return resReturn(0, $return['msg'], $return['code']);
                }
            } else {
                if ($User->unsubscribe == User::USER_UNSUBSCRIBE_YES) {
                    return resReturn(0, __('user.cellphone.error.cancelled'), Code::CODE_WRONG);
                }
                $return = DB::transaction(function () use ($request, $miniPhoneNumber, $User, $openid) {
                    $User->updated_at = Carbon::now()->toDateTimeString();
                    if (!$User[strtolower($request->platform)]) {
                        $User[strtolower($request->platform)] = $openid;
                    }
                    $User->save();
                    return [
                        'state' => 1,
                        'data' => $User
                    ];
                }, 5);
                if ($return['state'] == 1) {

                    $params = array_merge(config('passport.web.proxy'), [
                        'username' => $return['data']->name,
                        'password' => $return['data']->password,
                    ]);
                    $respond = $client->post($url, ['form_params' => $params]);
                    $access_token = json_decode($respond->getBody()->getContents(), true);
                    $access_token['refresh_expires_in'] = config('passport.refresh_expires_in') / 60 / 60 / 24;
                    $access_token['nickname'] = $return['data']->nickname;
                    $access_token['cellphone'] = $return['data']->cellphone;
                    $access_token['portrait'] = $return['data']->portrait;
                    $access_token['wechat'] = $return['data']->wechat;
                    return resReturn(1, $access_token);
                }
            }
        } else {
            return resReturn(0, __('user.authorization.error'), Code::CODE_MISUSE);
        }
    }

    /**
     * Logout
     * 登出
     * @param Request $request
     * @return string
     */
    public function logout(Request $request)
    {
        $user = User::find(auth('web')->user()->id);
        $user->updated_at = Carbon::now()->toDateTimeString();
        $user->save();
        return resReturn(1, __('common.succeed'));
    }

    /**
     * Register
     * 注册
     * @param Request $request
     * @return string
     * @queryParam  cellphone int 手机号
     * @queryParam  password string 密码
     * @queryParam  rPassword string 重复密码
     * @queryParam  code int 验证码
     */
    public function register(Request $request)
    {
        if (!$request->has('cellphone')) {
            return resReturn(0, __('hint.error.not_null', ['attribute' => __('user.cellphone')]), Code::CODE_WRONG);
        }
        if (!$request->has('password')) {
            return resReturn(0, __('hint.error.not_null', ['attribute' => __('user.password')]), Code::CODE_WRONG);
        }
        if (!$request->has('rPassword')) {
            return resReturn(0, __('user.duplicate_password.error.null'), Code::CODE_WRONG);
        }
        if ($request->password != $request->rPassword) {
            return resReturn(0, __('user.duplicate_password.error.different'), Code::CODE_WRONG);
        }
        $user = User::where('cellphone', $request->cellphone)->first();
        if ($user) {
            if ($user->unsubscribe == User::USER_UNSUBSCRIBE_YES) {
                return resReturn(0, __('user.cellphone.error.cancelled'), Code::CODE_WRONG);
            }
            return resReturn(0, __('user.cellphone.error.exist'), Code::CODE_WRONG);
        }
        $redis = new RedisService();
        $code = $redis->get('code.register.' . $request->cellphone);
        if (!$code) {
            return resReturn(0, __('user.email.error.lost_effectiveness'), Code::CODE_MISUSE);
        }
        if ($code != $request->code) {
            return resReturn(0, __('user.email_code.error'), Code::CODE_MISUSE);
        }
        $return = DB::transaction(function () use ($request) {
            $addUser = new User();
            $addUser->name = $request->cellphone;
            $addUser->cellphone = $request->cellphone;
            $addUser->password = bcrypt($request->password);
            $addUser->api_token = hash('sha256', Str::random(60));
            $addUser->uuid = (string)Uuid::generate();
            $addUser->save();
            return [
                'result' => 'ok',
                'msg' => __('common.succeed')
            ];
        }, 5);
        if ($return['result'] == 'ok') {
            return resReturn(1, __('common.succeed'));
        } else {
            return resReturn(0, __('common.fail'), Code::CODE_PARAMETER_WRONG);
        }
    }

    /**
     * FindPassword
     * 找回密码
     * @param Request $request
     * @return string
     * @queryParam  cellphone int 手机号
     * @queryParam  password string 密码
     * @queryParam  rPassword string 重复密码
     * @queryParam  code int 验证码
     */
    public function findPassword(Request $request)
    {
        if (!$request->has('cellphone')) {
            return resReturn(0, __('hint.error.not_null', ['attribute' => __('user.cellphone')]), Code::CODE_WRONG);
        }
        if (!$request->has('password')) {
            return resReturn(0, __('hint.error.not_null', ['attribute' => __('user.new_password')]), Code::CODE_WRONG);
        }
        if (!$request->has('rPassword')) {
            return resReturn(0, __('user.affirm_password.error.null'), Code::CODE_WRONG);
        }
        if ($request->password != $request->rPassword) {
            return resReturn(0, __('user.affirm_password.error.different'), Code::CODE_WRONG);
        }
        $redis = new RedisService();
        $code = $redis->get('code.register.' . $request->cellphone);
        if (!$code) {
            return resReturn(0, __('user.email.error.lost_effectiveness'), Code::CODE_MISUSE);
        }
        if ($code != $request->code) {
            return resReturn(0, __('user.email_code.error'), Code::CODE_MISUSE);
        }
        $user = User::where('cellphone', $request->cellphone)->first();
        $user->password = bcrypt($request->password);
        $user->save();
        return resReturn(1, __('common.succeed'));
    }

    /**
     * AmendPassword
     * 修改密码
     * @param Request $request
     * @return string
     * @queryParam  nowPassword string 当前密码
     * @queryParam  password string 新密码
     * @queryParam  rPassword string 重复密码
     * @queryParam  code int 验证码
     */
    public function amendPassword(Request $request)
    {
        if (!$request->has('nowPassword')) {
            return resReturn(0, __('hint.error.not_null', ['attribute' => __('user.old_password')]), Code::CODE_WRONG);
        }
        if (!$request->has('password')) {
            return resReturn(0, __('hint.error.not_null', ['attribute' => __('user.new_password')]), Code::CODE_WRONG);
        }
        if (!$request->has('rPassword')) {
            return resReturn(0, __('user.affirm_password.error.null'), Code::CODE_WRONG);
        }
        if (!Hash::check($request->nowPassword, auth('web')->user()->password)) {
            return resReturn(0, __('hint.error.mistake', ['attribute' => __('user.old_password')]), Code::CODE_WRONG);
        }
        if ($request->nowPassword == $request->password) {
            return resReturn(0, __('user.old_password.error.not_identical'), Code::CODE_WRONG);
        }
        if ($request->password != $request->rPassword) {
            return resReturn(0, __('user.old_password.error.different'), Code::CODE_WRONG);
        }
        $user = User::find(auth('web')->user()->id);
        $user->password = bcrypt($request->password);
        $user->save();
        return resReturn(1, __('common.succeed'));
    }

    /**
     * 小程序换取openid
     * @param Request $request
     * @return string
     * @throws \Exception
     * @queryParam  platform string 平台标识
     * @queryParam  code string 平台标识
     */
    public function miniLogin(Request $request)
    {
        // 不支持的直接返回
        if (!in_array($request->platform, ['miniWeixin', 'miniAlipay', 'miniToutiao'])) {
            return resReturn(1, []);
        }
        $MiniProgram = new MiniProgram();
        $mini = $MiniProgram->mini($request->platform, $request->code);
        if ($mini['result'] == 'ok') {
            return resReturn(1, $mini);
        } else {
            return resReturn(0, $mini['msg'], Code::CODE_WRONG);
        }
    }
}
