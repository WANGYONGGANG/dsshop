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
use App\Http\Requests\v1\SubmitGoodIndentRequest;
use App\Models\v1\Good;
use App\Models\v1\User;
use App\common\RedisLock;
use App\Models\v1\GoodIndent;
use App\Models\v1\GoodIndentCommodity;
use App\Models\v1\GoodSku;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Webpatser\Uuid\Uuid;

/**
 * @group [CLIENT]GoodIndent(商品订单)
 * Class GoodIndentController
 * @package App\Http\Controllers\v1\Client
 */
class GoodIndentController extends Controller
{
    /**
     * GoodIndentList
     * 商品订单列表
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @queryParam  limit int 每页显示条数
     * @queryParam  sort string 排序
     * @queryParam  page string 页码
     */
    public function list(Request $request)
    {
        GoodIndent::$withoutAppends = false;
        GoodIndentCommodity::$withoutAppends = false;
        Good::$withoutAppends = false;
        $q = GoodIndent::query();
        $q->where('user_id', auth('web')->user()->id);
        if (intval($request->index) !== 0) {
            $q->where('state', $request->index);
        }
        if ($request->search == 1) {
            $q->where('created_at', '>=', date("Y-m-d 00:00:00", strtotime($request->startTime)))->where('created_at', '<=', date("Y-m-d 23:59:59", strtotime($request->endTime)));
        }
        $limit = $request->limit;
        if ($request->has('sort')) {
            $sortFormatConversion = sortFormatConversion($request->sort);
            $q->orderBy($sortFormatConversion[0], $sortFormatConversion[1]);
        }
        $paginate = $q->with(['goodsList' => function ($q) {
            $q->with(['goodSku', 'good' => function ($q) {
                $q->select('name', 'id', 'type');
            }]);
        }])->paginate($limit);
        return resReturn(1, $paginate);
    }

    /**
     * GoodIndentList
     * 创建商品订单
     * @param SubmitGoodIndentRequest $request
     * @return \Illuminate\Http\Response
     * @queryParam  carriage int 运费
     * @queryParam  indentCommodity array 订单商品
     * @queryParam  remark string 备注
     * @queryParam  address array 收货地址
     */
    public function create(SubmitGoodIndentRequest $request)
    {
        $redis = new RedisService();
        $lock = RedisLock::lock($redis, 'goodIndent');
        if ($lock) {
            $return = DB::transaction(function () use ($request) {
                $GoodIndent = new GoodIndent();
                $GoodIndent->user_id = auth('web')->user()->id;
                $GoodIndent->state = GoodIndent::GOOD_INDENT_STATE_PAY;
                $GoodIndent->carriage = $request->carriage;
                $total = 0;
                foreach ($request->indentCommodity as $indentCommodity) {
                    $Good = Good::select('id', 'is_inventory', 'inventory', 'type')->find($indentCommodity['good_id']);
                    if ($Good->type === Good::GOOD_TYPE_COMMON) {
                        if (!$request->address) {
                            throw new \Exception(__('hint.error.not_null', ['attribute' => __('shipping.location')]), Code::CODE_WRONG);
                        }
                    }
                    if ($Good && $Good->is_inventory == Good::GOOD_IS_INVENTORY_NO) { //拍下减库存
                        if (!$indentCommodity['good_sku_id']) { //非SKU商品
                            if ($Good->inventory - $indentCommodity['number'] < 0) {
                                return array(__('good_indent.deficient_commodity'), Code::CODE_PARAMETER_WRONG);
                            }
                            $Good->inventory = $Good->inventory - $indentCommodity['number'];
                            $Good->save();
                        } else {
                            $GoodSku = GoodSku::find($indentCommodity['good_sku_id']);
                            if ($GoodSku->inventory - $indentCommodity['number'] < 0) {
                                return array(__('good_indent.deficient_commodity_sku'), Code::CODE_PARAMETER_WRONG);
                            }
                            $GoodSku->inventory = $GoodSku->inventory - $indentCommodity['number'];
                            $GoodSku->save();
                        }
                    }
                    $total += $indentCommodity['number'] * $indentCommodity['price'];
                }
                $GoodIndent->identification = orderNumber();
                $GoodIndent->total = $total + $request->carriage;
                $GoodIndent->remark = $request->remark;
                $GoodIndent->overtime = date('Y-m-d H:i:s', time() + config('dsshop.orderOvertime') * 60);
                $GoodIndent->save();
                return array(1, $GoodIndent->id);
            }, 5);
            RedisLock::unlock($redis, 'goodIndent');
            if ($return[0] == 1) {
                return resReturn(1, $return[1]);
            } else {
                return resReturn(0, $return[0], $return[1]);
            }
        } else {
            return resReturn(0, __('common.busy'), Code::CODE_SYSTEM_BUSY);
        }
    }

    /**
     * AddItemsToShoppingCart
     * 添加商品到购物车
     * @param Request $request
     * @return string
     */
    public function addShoppingCart(Request $request)
    {
        $redis = new RedisService();
        $return = $request->all();
        $redis->del('shoppingCart' . auth('web')->user()->id);
        if (count($return) > 0) {
            $redis->set('shoppingCart' . auth('web')->user()->id, json_encode($return));
        }
        return resReturn(1, __('common.succeed'));
    }

    /**
     * EmptyCart
     * 清空购物车
     * @param Request $request
     * @return string
     */
    public function clearShoppingCart(Request $request)
    {
        $redis = new RedisService();
        $redis->del('shoppingCart' . auth('web')->user()->id);
        return resReturn(1, __('common.succeed'));
    }

    /**
     * SynchronizationInventory
     * 同步线上商品库存
     * @param Request $request
     * @return string
     */
    public function synchronizationInventory(Request $request)
    {
        $redis = new RedisService();
        $shoppingCart = $redis->get('shoppingCart' . auth('web')->user()->id);
        $redisData = $shoppingCart ? json_decode($shoppingCart, true) : [];
        if (count($redisData) > 0) {
            foreach ($redisData as $id => $all) {
                if ($all['good_sku_id']) { //sku商品
                    $Good = Good::find($all['good_id']);
                    if ($Good->is_show == Good::GOOD_SHOW_ENTREPOT || $Good->deleted_at) {
                        $redisData[$id]['invalid'] = true;  //标记为失效
                        continue;
                    }
                    $GoodSku = GoodSku::find($all['good_sku_id']);
                    if ($GoodSku->deleted_at) {
                        $redisData[$id]['invalid'] = true;  //标记为失效
                    } else {
                        if ($GoodSku->inventory < $all['number']) { //库存不足时
                            $redisData[$id]['invalid'] = true;  //标记为失效
                        } else {
                            $redisData[$id]['invalid'] = false;
                        }
                    }
                } else {
                    $Good = Good::find($all['good_id']);
                    if ($Good->deleted_at) {
                        $redisData[$id]['invalid'] = true;  //标记为失效
                    } else {
                        if ($Good->inventory < $all['number']) {
                            $redisData[$id]['invalid'] = true;  //标记为失效
                        } else {
                            $redisData[$id]['invalid'] = false;
                        }
                    }
                }
            }
            $redis->set('shoppingCart' . auth('web')->user()->id, json_encode($redisData));
        }
        return resReturn(1, $redisData);
    }

    /**
     * GoodIndentDetail
     * 商品订单详情
     * @param int $id
     * @return \Illuminate\Http\Response
     * @queryParam  id int 订单ID
     */
    public function detail($id)
    {
        GoodIndentCommodity::$withoutAppends = false;
        GoodSku::$withoutAppends = false;
        GoodIndent::$withoutAppends = false;
        $GoodIndent = GoodIndent::with(['goodsList' => function ($q) {
            $q->with(['good' => function ($q) {
                $q->with(['resourcesMany', 'goodSku' => function ($q) {
                    $q->with('resources')->where('inventory', '>', 0);
                }]);
            }, 'goodSku' => function ($q) {
                $q->with(['resources' => function ($q) {
                    $q->where('depict', 'product_sku_file');
                }]);
            }]);
        }, 'GoodLocation', 'GoodCode', 'Dhl'])->find($id);
        foreach ($GoodIndent->goodsList as $commodity) {
            if ($commodity->goodSku->resources) {
                $GoodIndent->download = true;
            }
            unset($commodity->goodSku->resources);
        }
        return resReturn(1, $GoodIndent);
    }

    /**
     * GoodIndentDownload
     * 下载订单地址
     * @param int $id
     * @return string
     * @queryParam  id int 订单ID
     * @throws \Exception
     */
    public function download($id)
    {
        if (!$id) {
            throw new \Exception(__('common.arguments'), Code::CODE_WRONG);
        }
        $GoodIndent = GoodIndent::with(['goodsList' => function ($q) {
            $q->with(['goodSku' => function ($q) {
                $q->with(['resources' => function ($q) {
                    $q->where('depict', 'product_sku_file');
                }]);
            }]);
        }])->find($id);
        if ($GoodIndent->user_id != auth('web')->user()->id) {
            throw new \Exception(__('good_indent.error.download.impuissance'), Code::CODE_NO_ACCESS);
        }
        $code = (string)Uuid::generate();
        foreach ($GoodIndent->goodsList as $commodity) {
            if (!$commodity->goodSku->resources) {
                throw new \Exception(__('good_indent.error.download.nothing'), Code::CODE_WRONG);
            }
            $redis = new RedisService();
            $redis->setex($code, 30, $commodity->goodSku->resources->img);
        }
        return resReturn(1, $code);
    }

    /**
     * GoodIndentDownload
     * 订单文件下载
     * @param int $code
     * @return string
     * @queryParam  code int 下载码
     * @throws \Exception
     */
    public function showDownload($code)
    {
        $redis = new RedisService();
        $download = $redis->get($code);
        if (!$download) {
            return resReturn(0, __('good_indent.error.download.incorrectness'), Code::CODE_WRONG);
        }
        $redis->del($code);
        return Storage::download($download);
    }

    /**
     * GoodIndentPay
     * 订单支付详情
     * @param $id
     * @return string
     * @queryParam  id int 订单ID
     */
    public function pay($id)
    {
        GoodIndentCommodity::$withoutAppends = false;
        GoodIndent::$withoutAppends = false;
        User::$withoutAppends = false;
        $GoodIndent = GoodIndent::with(['goodsList' => function ($q) {
            $q->select('good_id', 'good_indent_id')->with(['good' => function ($q) {
                $q->select('name', 'id', 'type');
            }]);
        }, 'User' => function ($q) {
            $q->select('id', 'money');
        }, 'GoodLocation'])->select('id', 'total', 'user_id', 'state', 'overtime', 'identification', 'type')->find($id);
        $time_diff = time_diff($GoodIndent->overtime);
        $GoodIndent->day = $time_diff['day'];
        $GoodIndent->hour = $time_diff['hour'];
        $GoodIndent->minute = $time_diff['minute'];
        $GoodIndent->second = $time_diff['second'];
        $GoodIndent->overtime_time = strtotime($GoodIndent->overtime) - time();
        return resReturn(1, $GoodIndent);
    }

    /**
     * GoodIndentReceipt
     * 确认收货
     * @param $id
     * @return string
     * @queryParam  id int 订单ID
     */
    public function receipt($id)
    {
        $return = DB::transaction(function () use ($id) {
            $GoodIndent = GoodIndent::with(['goodsList'])->find($id);
            $GoodIndent->state = GoodIndent::GOOD_INDENT_STATE_ACCOMPLISH;
            $GoodIndent->confirm_time = Carbon::now()->toDateTimeString();
            $GoodIndent->receiving_time = Carbon::now()->toDateTimeString();
            $GoodIndent->save();
            return array(1, __('common.succeed'));
        });
        if ($return[0] == 1) {
            return resReturn(1, $return[1]);
        } else {
            return resReturn(0, $return[0], $return[1]);
        }
    }

    /**
     * GoodIndentCancel
     * 取消订单
     * @param $id
     * @return string
     */
    public function cancel($id)
    {
        $GoodIndent = GoodIndent::with(['goodsList'])->find($id);
        $GoodIndent->state = GoodIndent::GOOD_INDENT_STATE_CANCEL;
        $GoodIndent->save();
        return resReturn(1, __('common.succeed'));
    }

    /**
     * GoodIndentDestroy
     * 删除订单
     * @param $id
     * @return string
     */
    public function destroy($id)
    {
        GoodIndent::destroy($id);
        return resReturn(1, __('hint.succeed.win', ['attribute' => __('common.delete')]));
    }

    /**
     * GoodIndentQuantity
     * 订单数量统计
     * @return string
     */
    public function quantity()
    {
        $GoodIndent = GoodIndent::where('user_id', auth('web')->user()->id)->get();
        $return = [
            'all' => 0, //全部订单
            'obligation' => 0, //待付款
            'waitdeliver' => 0, //待发货
            'waitforreceiving' => 0, //待收货
        ];
        if ($GoodIndent) {
            foreach ($GoodIndent as $indent) {
                if ($indent->deleted_at == null) {
                    if ($indent->state == GoodIndent::GOOD_INDENT_STATE_PAY) {
                        $return['obligation'] += 1;
                    } else if ($indent->state == GoodIndent::GOOD_INDENT_STATE_DELIVER) {
                        $return['waitdeliver'] += 1;
                    } else if ($indent->state == GoodIndent::GOOD_INDENT_STATE_TAKE) {
                        $return['waitforreceiving'] += 1;
                    }
                }
            }
        }
        return resReturn(1, $return);
    }
}
