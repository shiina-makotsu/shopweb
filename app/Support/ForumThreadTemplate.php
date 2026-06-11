<?php

namespace App\Support;

class ForumThreadTemplate
{
    public const GENERAL = 'general';
    public const FRIENDSHIP = 'friendship';
    public const MATCHMAKING = 'matchmaking';
    public const ROOMMATE = 'roommate';
    public const RENT_OFFER = 'rent_offer';
    public const RENT_WANTED = 'rent_wanted';
    public const RESOURCE = 'resource';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::GENERAL => '普通帖子',
            self::FRIENDSHIP => '交友自我介绍',
            self::MATCHMAKING => '相亲介绍与要求',
            self::ROOMMATE => '寻找合租人',
            self::RENT_OFFER => '招租信息',
            self::RENT_WANTED => '找租/承租需求',
            self::RESOURCE => '资源发布',
        ];
    }

    public static function normalize(?string $template): string
    {
        return array_key_exists((string) $template, self::options()) ? (string) $template : self::GENERAL;
    }

    public static function label(?string $template): string
    {
        $template = self::normalize($template);

        return self::options()[$template];
    }

    public static function defaultTitle(?string $template): string
    {
        return match (self::normalize($template)) {
            self::FRIENDSHIP => '交友自我介绍',
            self::MATCHMAKING => '相亲自我介绍',
            self::ROOMMATE => '寻找合租人',
            self::RENT_OFFER => '招租信息',
            self::RENT_WANTED => '找租需求',
            self::RESOURCE => '资源发布',
            default => '',
        };
    }

    public static function defaultBody(?string $template): string
    {
        return match (self::normalize($template)) {
            self::FRIENDSHIP => <<<'MARKDOWN'
## 基本信息
- 昵称：
- 性别/自定义性别：
- 年龄段：
- 常驻城市/区域：
- 可接受联系范围：

## 性格与相处方式
- 性格关键词：
- 平时的沟通习惯：
- 希望认识怎样的朋友：

## 喜好
- 喜欢的活动：
- 喜欢的作品/游戏/音乐/运动：
- 不太接受的事项：

## 会的技能
- 工作/学习技能：
- 生活技能：
- 可以一起交流或互助的方向：

## 照片/视频
可以上传照片或视频附件，也可以在正文补充说明。
MARKDOWN,
            self::MATCHMAKING => <<<'MARKDOWN'
## 我的基本信息
- 性别/自定义性别：
- 年龄段：
- 常驻位置：
- 工作/行业：
- 作息与生活方式：
- 感情状态：

## 我的性格与喜好
- 性格关键词：
- 兴趣爱好：
- 未来规划：

## 物质与现实情况
- 收入/资产情况（可写区间或“不公开”）：
- 是否有房/车/贷款：
- 是否接受异地：

## 希望对方
- 外貌/身高等偏好：
- 性别/性别表达：
- 年龄范围：
- 兴趣爱好：
- 工作/收入/资产期待：
- 常驻城市或距离：
- 不能接受的事项：

## 联系方式与照片
可以上传照片或视频附件，也可以说明私信后交换。
MARKDOWN,
            self::ROOMMATE => <<<'MARKDOWN'
## 合租需求
- 城市：
- 区域/地段：
- 详细位置或地铁站：
- 预算租金：
- 期望入住时间：
- 预计合租时长：

## 房屋与设施要求
- 户型/房间：
- 独卫/阳台/厨房：
- 家具家电：
- 网络/停车/宠物：
- 电梯/楼层：

## 对合租人的期待
- 性别/作息/职业偏好：
- 卫生习惯：
- 访客/宠物/抽烟要求：
- 其他不能接受事项：

## 自我介绍
- 工作/学习：
- 性格：
- 作息：
- 爱好：

## 照片/视频/位置
可上传房源、环境、交通截图、视频或地图位置截图。
MARKDOWN,
            self::RENT_OFFER => <<<'MARKDOWN'
## 房源基本信息
- 城市：
- 区域/地段：
- 详细位置：
- 户型/面积：
- 楼层/电梯：
- 可入住时间：

## 租金与要求
- 月租：
- 押金/付款方式：
- 最短居住时长：
- 水电网物业费用：
- 是否可短租：
- 是否可养宠物：

## 设施
- 家具家电：
- 独卫/厨房/阳台：
- 网络/停车：
- 周边交通：

## 对租客/合租人的要求
- 人数：
- 作息/卫生：
- 访客/抽烟/宠物：
- 其他要求：

## 照片/视频/位置
请上传房间、公共区域、楼栋、周边或视频附件，详细位置可写到地铁站/街道级别。
MARKDOWN,
            self::RENT_WANTED => <<<'MARKDOWN'
## 找租需求
- 城市：
- 期望区域/地段：
- 通勤目标/地铁线路：
- 预算租金：
- 入住时间：
- 计划居住时长：

## 期望房屋
- 整租/合租：
- 户型/房间：
- 独卫/厨房/阳台：
- 家具家电：
- 是否接受宠物/短租：

## 承租人简介
- 人数：
- 工作/学习：
- 作息：
- 性格：
- 是否养宠物：
- 卫生习惯：

## 其他要求
- 不能接受事项：
- 希望看房时间：
- 可提供的证明材料：
MARKDOWN,
            self::RESOURCE => <<<'MARKDOWN'
## 资源信息
- 资源名称：
- 类型：
- 适用场景：
- 版本/日期：
- 来源或授权说明：

## 内容简介
请说明资源包含什么、适合谁使用、使用前需要注意什么。

## 使用方法
1. 下载附件。
2. 按说明打开或导入。
3. 如需解压密码，请在此说明。

## 附件与预览
请上传资源文件、预览图、演示视频或说明文档。
MARKDOWN,
            default => '',
        };
    }
}
