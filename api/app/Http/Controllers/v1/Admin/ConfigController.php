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
namespace App\Http\Controllers\v1\Admin;

use App\Code;
use App\common\RedisService;
use App\Http\Resources\ConfigResources;
use App\Models\v1\Config;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * @group [ADMIN]Config(配置)
 * Class ConfigController
 * @package App\Http\Controllers\v1\Admin
 */
class ConfigController extends Controller
{
    /**
     * ConfigList
     * 配置列表
     * @param Request $request
     * @return string
     * @queryParam  name string 品牌名称
     * @queryParam  limit int 每页显示条数
     * @queryParam  sort string 排序
     * @queryParam  page string 页码
     */
    public function list(Request $request)
    {
        Config::$withoutAppends = false;
        $q = Config::query();
        $paginate = $q->where('parent_id', 0)->with(['children'])->get();
        return resReturn(1, ConfigResources::collection($paginate));
    }

    /**
     * ConfigEdit
     * 保存配置
     * @param Request $request
     * @param int $id
     * @return string
     * @queryParam  id int 品牌ID
     * @queryParam  name string 品牌名称
     * @queryParam  sort int 排序
     */
    public function edit(Request $request, $id)
    {
        DB::transaction(function () use ($request, $id) {
            $redis = new RedisService();
            foreach ($request->children as $children) {
                if ($children['required'] && !isset($children['value'])) {
                    throw new \Exception(__('hint.error.not_null', ['attribute' => $children['name']]), Code::CODE_WRONG);
                }
                // 重置
                if ($children['keys'] == 'dsshop.reset' && $children['value']) {
                    Config::where('id', '>', 0)->delete();
                    $redis->del('config');
                    return true;
                }
                // 有子级的，父类不更新
                if (isset($children['children'])) {
                    foreach ($children['children'] as $c) {
                        if ($c['required'] && !isset($c['value'])) {
                            throw new \Exception(__('hint.error.not_null', ['attribute' => $c['name']]), Code::CODE_WRONG);
                        }
                        $Config = Config::find($c['id']);
                        $Config->value = $c['value'];
                        $Config->save();
                    }
                } else {
                    $Config = Config::find($children['id']);
                    $Config->value = $children['value'];
                    $Config->save();
                }
                $redis->del('config');
            }
        }, 5);
        return resReturn(1, __('hint.succeed.win', ['attribute' => __('common.update')]));
    }
}
