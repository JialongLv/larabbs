<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Overtrue\EasySms\EasySms;
use App\Http\Requests\Api\VerificationCodeRequest;

class VerificationCodesController extends Controller
{
    public function store(VerificationCodeRequest $request,EasySms $easySms)
    {
        $captchaDate = \Cache::get($request->captcha_key);
//        dd( $captchaDate);
        if (!$captchaDate){
            return $this->response->error('图片验证码已失效',422);
        }

        if (!hash_equals($captchaDate['code'], $request->captcha_code)){
            //验证错误清除缓存
            \Cache::forget($request->captcha_key);
            return $this->response->errorUnauthorized('验证码错误');
        }

        $phone = $captchaDate['phone'];

        if (!app()->environment('production')){
            $code = '1234';
        }else{
            //生成验证码
            $code = str_pad(random_int(1,9999),4,0,STR_PAD_LEFT);
            try{
                $result = $easySms->send($phone,[
                    'content' => "【吕加龙】您的验证码是{$code}"
                ]);
            }catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception){
                $message = $exception->getException('yinpian')->getMessage();
                return $this->response->errorInternal($message ?? '短信发送异常');
            }
        }

        $key = 'verificationCode_'.str_random(15);
        $expiredAt = now()->modify('10 minute');
        //缓存验证码,10分钟过期
        \Cache::put($key,['phone' => $phone,'code' => $code],$expiredAt);

        return $this->response->array(
            [
                'key' =>$key,
                'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
            ])->setStatusCode(201);
    }
}
