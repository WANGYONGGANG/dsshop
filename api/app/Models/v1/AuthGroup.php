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
namespace App\Models\v1;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed roles
 * @property mixed introduction
 * @property mixed rules
 * @property string rules_implode
 * @property mixed id
 */
class AuthGroup extends Model
{
    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 获取管理组下的管理员。
     */
    public function Admin()
    {
        return $this->belongsToMany(Admin::class);
    }

    public function AuthRule()
    {
        return $this->belongsToMany(AuthRule::class, 'auth_group_auth_rules');
    }

    //分解权限
    public function returnRulesData($data, &$arr = [])
    {
        foreach ($data as $d) {
            if (!in_array($d['id'], $arr)) {
                $arr[] = $d['id'];
            }

            if (array_key_exists('children', $d)) {
                $this->returnRulesData($d['children'], $arr);
            }
        }
        return $arr;
    }
}
