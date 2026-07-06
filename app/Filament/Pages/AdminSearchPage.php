<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AdminActivityLogResource;
use App\Filament\Resources\AdminLoginLogResource;
use App\Filament\Resources\AdminUserResource;
use App\Filament\Resources\AiWorkflowResource;
use App\Filament\Resources\AnnouncementResource;
use App\Filament\Resources\AnnouncementCommentResource;
use App\Filament\Resources\AfterSalesRequestResource;
use App\Filament\Resources\CatalogAttributeResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\DeliveryStatusResource;
use App\Filament\Resources\ForumCommentResource;
use App\Filament\Resources\ForumSectionResource;
use App\Filament\Resources\ForumThreadResource;
use App\Filament\Resources\FlashSaleResource;
use App\Filament\Resources\GuestSupportChatSessionResource;
use App\Filament\Pages\GuideAiSettingsPage;
use App\Filament\Resources\InventoryMovementResource;
use App\Filament\Resources\ManufacturerResource;
use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderStatusSettingResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PaymentVerificationLogResource;
use App\Filament\Resources\ProductCommentResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductTagResource;
use App\Filament\Resources\ProcurementResource;
use App\Filament\Resources\QuantityUnitResource;
use App\Filament\Resources\ReferralRewardRuleResource;
use App\Filament\Resources\ShippingCarrierResource;
use App\Filament\Resources\SiteSettingResource;
use App\Filament\Resources\SoldOutStatusResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SupportChatSessionResource;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\WarehouseResource;
use App\Filament\Resources\WarehouseMovementResource;
use App\Filament\Resources\WarehouseStockResource;
use App\Support\AdminAccess;
use App\Support\RegexSearch;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AdminSearchPage extends Page
{
    protected static ?string $navigationLabel = '后台搜索';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'admin-search';
    protected string $view = 'filament.pages.admin-search';

    public string $search = '';

    public function getTitle(): string
    {
        return '后台搜索';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::canAccessPanel(auth()->user());
    }

    /**
     * @return array<int, array{label:string, group:string, keywords:string, url:string}>
     */
    public function results(): array
    {
        $search = trim($this->search);

        return collect($this->entries())
            ->filter(function (array $entry) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $haystack = "{$entry['group']} {$entry['label']} {$entry['keywords']}";
                $pattern = RegexSearch::patternFrom($search);

                if ($pattern) {
                    return @preg_match('/'.$pattern.'/iu', $haystack) === 1;
                }

                return str_contains(mb_strtolower($haystack), mb_strtolower($search));
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string, group:string, keywords:string, url:string}>
     */
    private function entries(): array
    {
        return [
            ['group' => '首页', 'label' => '仪表盘', 'keywords' => '月度销售 每日销售 数据统计 折线图', 'url' => Dashboard::getUrl()],
            ['group' => '商品', 'label' => '商品管理', 'keywords' => '商品 SKU 库存 预售 概念 进货中 现货 售罄', 'url' => ProductResource::getUrl()],
            ['group' => '商品', 'label' => '商品评论', 'keywords' => '评论 图片 回复 评分 星级 删除', 'url' => ProductCommentResource::getUrl()],
            ['group' => '目录', 'label' => '商品分类', 'keywords' => '分类 目录 SEO', 'url' => CategoryResource::getUrl()],
            ['group' => '目录', 'label' => '商品标签', 'keywords' => 'tag 标签 SEO 搜索 列表分页', 'url' => ProductTagResource::getUrl()],
            ['group' => '目录', 'label' => '属性', 'keywords' => '商品属性 规格', 'url' => CatalogAttributeResource::getUrl()],
            ['group' => '目录', 'label' => '制造商', 'keywords' => '品牌 制造商', 'url' => ManufacturerResource::getUrl()],
            ['group' => '目录', 'label' => '供应商', 'keywords' => '供应商 采购', 'url' => SupplierResource::getUrl()],
            ['group' => '目录', 'label' => '交付状态', 'keywords' => '交付 配送 状态', 'url' => DeliveryStatusResource::getUrl()],
            ['group' => '目录', 'label' => '售罄状态', 'keywords' => '售罄 缺货', 'url' => SoldOutStatusResource::getUrl()],
            ['group' => '目录', 'label' => '数量单位', 'keywords' => '单位 件 个 kg cm', 'url' => QuantityUnitResource::getUrl()],
            ['group' => '交易', 'label' => '订单管理', 'keywords' => '订单号 用户 付款 待发货 正在运输 待收货', 'url' => OrderResource::getUrl()],
            ['group' => '交易', 'label' => '付款设置', 'keywords' => '二维码 支付宝 PayPal Visa Mastercard Amex 付款说明 备用付款码 好友码 口令红包 钱包', 'url' => PaymentSettingsPage::getUrl()],
            ['group' => '交易', 'label' => '钱包设置', 'keywords' => '钱包 充值 充值选项 折扣 赠送余额 钱包余额', 'url' => WalletSettingsPage::getUrl()],
            ['group' => '交易', 'label' => '付款校验日志', 'keywords' => '付款凭证 截图 自动校验 人工复核 订单号 金额', 'url' => PaymentVerificationLogResource::getUrl()],
            ['group' => '交易', 'label' => '优惠码', 'keywords' => '折扣 优惠码 满减 百分比', 'url' => CouponResource::getUrl()],
            ['group' => '交易', 'label' => '奖励规则', 'keywords' => '事件 邀请 购买商品 发帖 钱包付款 优惠券 钱包余额 奖励', 'url' => ReferralRewardRuleResource::getUrl()],
            ['group' => '交易', 'label' => '商品折扣', 'keywords' => '批量折扣 SKU 折扣价 折扣时间', 'url' => ProductDiscountPage::getUrl()],
            ['group' => '交易', 'label' => '秒杀', 'keywords' => '秒杀 抢购 限时 名额 下次秒杀', 'url' => FlashSaleResource::getUrl()],
            ['group' => '交易', 'label' => '订单状态', 'keywords' => '待付款 待发货 运输 收货 完成', 'url' => OrderStatusSettingResource::getUrl()],
            ['group' => '交易', 'label' => '物流承运商', 'keywords' => '物流 查询 国际物流 国内物流 tracking', 'url' => ShippingCarrierResource::getUrl()],
            ['group' => '仓库', 'label' => '采购', 'keywords' => '采购商品 预售 入库 国际物流 分配预售用户', 'url' => ProcurementResource::getUrl()],
            ['group' => '仓库', 'label' => '仓库地址', 'keywords' => '仓库 地址 联系人 多仓 发货', 'url' => WarehouseResource::getUrl()],
            ['group' => '仓库', 'label' => '仓管', 'keywords' => '仓库 入库 出库 退回 二次加工 手动调整', 'url' => WarehouseStockResource::getUrl()],
            ['group' => '仓库', 'label' => '仓库流水', 'keywords' => '入库 出库 退回 加工 调整 仓库流水', 'url' => WarehouseMovementResource::getUrl()],
            ['group' => '仓库', 'label' => '库存流水', 'keywords' => '商品 SKU 库存管理 扣减 恢复 低库存', 'url' => InventoryMovementResource::getUrl()],
            ['group' => '用户', 'label' => '前台用户', 'keywords' => '客户 普通用户 会员用户 订单号可见 物流号可见', 'url' => CustomerResource::getUrl()],
            ['group' => '用户', 'label' => '后台用户', 'keywords' => '管理员 运营 财务 仓库 权限', 'url' => AdminUserResource::getUrl()],
            ['group' => 'AI', 'label' => 'Token 监视', 'keywords' => 'AI 配额 token key url 模型 用量 用户', 'url' => UserAiPage::getUrl()],
            ['group' => 'AI', 'label' => 'AI 工作流', 'keywords' => 'AI 工作流 comfyui 画布 节点 lora 语言模型 画图模型 串联', 'url' => AiWorkflowResource::getUrl()],
            ['group' => 'AI', 'label' => '导购 AI', 'keywords' => '导购 桌宠 AI 工作流 商品推荐', 'url' => GuideAiSettingsPage::getUrl()],
            ['group' => '客服', 'label' => '客服会话', 'keywords' => '即时聊天 图片 文件 接待 结束', 'url' => SupportChatSessionResource::getUrl()],
            ['group' => '客服', 'label' => '游客会话', 'keywords' => '游客 客服 即时聊天 游客ID', 'url' => GuestSupportChatSessionResource::getUrl()],
            ['group' => '客服', 'label' => '售后需求', 'keywords' => '订单售后 退款 补偿 优惠券 处理留言', 'url' => AfterSalesRequestResource::getUrl()],
            ['group' => '客服', 'label' => '客服工单', 'keywords' => '投诉 反馈 工单 支持 提需求', 'url' => SupportTicketResource::getUrl()],
            ['group' => 'AI', 'label' => '客服 AI 设置', 'keywords' => 'AI 安抚 等待时间 接口 key 模型 预设 工作流', 'url' => SupportAiSettingsPage::getUrl()],
            ['group' => '内容', 'label' => '首页', 'keywords' => '首页 Markdown 首页标题', 'url' => HomeContentPage::getUrl()],
            ['group' => '内容', 'label' => '公告', 'keywords' => '铃铛 未读 弹窗 置顶 发布 删除 评论', 'url' => AnnouncementResource::getUrl()],
            ['group' => '内容', 'label' => '公告评论', 'keywords' => '公告 回复 评论 删除', 'url' => AnnouncementCommentResource::getUrl()],
            ['group' => '内容', 'label' => '自定义页面', 'keywords' => '页面 markdown 关于我们 说明', 'url' => PageResource::getUrl()],
            ['group' => '内容', 'label' => '资源管理', 'keywords' => '图片 文件 logo 上传 媒体', 'url' => MediaAssetResource::getUrl()],
            ['group' => '论坛', 'label' => '论坛分区', 'keywords' => '分区 版块 创建 删除', 'url' => ForumSectionResource::getUrl()],
            ['group' => '论坛', 'label' => '帖子', 'keywords' => '发帖 置顶 删除 用户讨论', 'url' => ForumThreadResource::getUrl()],
            ['group' => '论坛', 'label' => '论坛回复', 'keywords' => '回复 评论 删除', 'url' => ForumCommentResource::getUrl()],
            ['group' => '报告', 'label' => '报告', 'keywords' => '销售排行 客户排行 投票 低库存', 'url' => ReportsPage::getUrl()],
            ['group' => '系统', 'label' => '后台搜索', 'keywords' => '搜索 正则 查找 功能 页面', 'url' => static::getUrl()],
            ['group' => '系统', 'label' => '商店信息设置', 'keywords' => 'Logo 联系方式 商店信息 外观 语言', 'url' => StoreInfoPage::getUrl()],
            ['group' => '系统', 'label' => '站点设置', 'keywords' => '订单隐私 桌宠 音乐 外观 语言', 'url' => SiteSettingResource::getUrl()],
            ['group' => '系统', 'label' => '邮件设置', 'keywords' => 'SMTP 发货邮件 通知', 'url' => MailSettingsPage::getUrl()],
            ['group' => '系统', 'label' => '缓存管理', 'keywords' => '缓存 config route view optimize clear git 更新 拉取 pull 回滚 rollback 构建 build', 'url' => CacheManagementPage::getUrl()],
            ['group' => '系统', 'label' => '备份', 'keywords' => '数据库 上传文件 备份', 'url' => BackupPage::getUrl()],
            ['group' => '系统', 'label' => '系统信息', 'keywords' => 'PHP 扩展 权限 环境', 'url' => SystemInfoPage::getUrl()],
            ['group' => '系统', 'label' => '后台操作日志', 'keywords' => '后台日志 操作记录 审计', 'url' => AdminActivityLogResource::getUrl()],
            ['group' => '系统', 'label' => '后台登录日志', 'keywords' => '登录日志 IP 浏览器', 'url' => AdminLoginLogResource::getUrl()],
        ];
    }
}
