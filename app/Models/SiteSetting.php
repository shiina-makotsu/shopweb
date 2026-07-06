<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_name',
        'store_email',
        'store_phone',
        'store_address',
        'store_tax_id',
        'store_country',
        'store_timezone',
        'store_currency',
        'currency_base_unit',
        'currency_base_locked',
        'currency_exchange_rates',
        'currency_gold_price',
        'currency_gold_unit',
        'currency_rates_updated_at',
        'logo_path',
        'favicon_path',
        'theme_template',
        'primary_color',
        'accent_color',
        'background_color',
        'home_background_path',
        'auth_background_path',
        'button_radius',
        'product_card_density',
        'home_welcome_enabled',
        'home_title',
        'home_welcome_image_path',
        'welcome_message',
        'home_content',
        'home_product_section_order',
        'contact_info',
        'copyright_text',
        'payment_instructions',
        'payment_qr_path',
        'payment_account_name',
        'payment_account_note',
        'payment_auto_check_enabled',
        'payment_pending_timeout_minutes',
        'presale_default_warehouse_id',
        'payment_gateway_provider',
        'payment_enabled_methods',
        'payment_gateway_config',
        'payment_gateway_notes',
        'payment_fallback_config',
        'wallet_recharge_success_message',
        'show_order_numbers_to_users',
        'show_tracking_numbers_to_users',
        'default_locale_mode',
        'enabled_locales',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
        'shipping_mail_subject',
        'shipping_mail_template',
        'page_music_enabled',
        'page_music_asset_path',
        'page_music_mode',
        'guide_pet_enabled',
        'guide_pet_asset_path',
        'guide_pet_api_endpoint',
        'guide_pet_api_key',
        'guide_pet_model',
        'guide_pet_system_prompt',
        'guide_pet_context_mode',
        'guide_ai_workflow_slug',
        'support_ai_enabled',
        'support_ai_endpoint',
        'support_ai_api_key',
        'support_ai_model',
        'support_ai_system_prompt',
        'support_ai_idle_minutes',
        'support_ai_workflow_slug',
        'ai_default_endpoint',
        'ai_default_api_key',
        'ai_default_image_endpoint',
        'ai_default_image_api_key',
        'ai_default_chat_endpoint',
        'ai_default_chat_api_key',
        'ai_default_user_quota_k',
        'profit_formula',
        'ai_trash_retention_days',
    ];

    protected function casts(): array
    {
        return [
            'show_order_numbers_to_users' => 'boolean',
            'show_tracking_numbers_to_users' => 'boolean',
            'payment_auto_check_enabled' => 'boolean',
            'payment_pending_timeout_minutes' => 'integer',
            'payment_enabled_methods' => 'array',
            'payment_gateway_config' => 'array',
            'payment_fallback_config' => 'array',
            'enabled_locales' => 'array',
            'currency_base_locked' => 'boolean',
            'currency_exchange_rates' => 'array',
            'currency_rates_updated_at' => 'datetime',
            'currency_gold_price' => 'decimal:4',
            'home_welcome_enabled' => 'boolean',
            'home_product_section_order' => 'array',
            'page_music_enabled' => 'boolean',
            'guide_pet_enabled' => 'boolean',
            'support_ai_enabled' => 'boolean',
        ];
    }

    public function presaleDefaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'presale_default_warehouse_id');
    }

    public function logoUrl(): ?string
    {
        return $this->assetUrl($this->logo_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->assetUrl($this->favicon_path);
    }

    public function homeBackgroundUrl(): ?string
    {
        return $this->assetUrl($this->home_background_path);
    }

    public function homeWelcomeImageUrl(): ?string
    {
        return $this->assetUrl($this->home_welcome_image_path);
    }

    public function authBackgroundUrl(): ?string
    {
        return $this->assetUrl($this->auth_background_path);
    }

    public function paymentQrUrl(): ?string
    {
        return $this->assetUrl($this->payment_qr_path);
    }

    public function paymentFallbackQrUrl(): ?string
    {
        return $this->assetUrl($this->payment_fallback_config['fallback_qr_path'] ?? null);
    }

    public function paymentFriendQrUrl(): ?string
    {
        return $this->assetUrl($this->payment_fallback_config['friend_qr_path'] ?? null);
    }

    public function paypalEmail(): ?string
    {
        $email = trim((string) ($this->payment_gateway_config['paypal_email'] ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @return array<int, string>
     */
    public static function defaultHomeProductSectionOrder(): array
    {
        return ['featured', 'discount', 'default', 'flash', 'concept'];
    }

    /**
     * @return array<string, string>
     */
    public static function homeProductSectionLabels(): array
    {
        return [
            'featured' => '推荐商品',
            'discount' => '折扣商品',
            'default' => '默认商品',
            'flash' => '秒杀商品',
            'concept' => '概念商品',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function homeProductSectionOrder(): array
    {
        $allowed = array_keys(self::homeProductSectionLabels());
        $configured = is_array($this->home_product_section_order) ? $this->home_product_section_order : [];
        $ordered = [];

        foreach ($configured as $item) {
            $key = is_array($item) ? ($item['section'] ?? null) : $item;

            if (is_string($key) && in_array($key, $allowed, true) && ! in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        foreach (self::defaultHomeProductSectionOrder() as $key) {
            if (! in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        return $ordered;
    }

    public function guidePetAssetUrl(): ?string
    {
        return $this->assetUrl($this->guide_pet_asset_path);
    }

    /**
     * @return array<string, string>
     */
    public function appearance(): array
    {
        return [
            'theme_template' => $this->theme_template ?: 'default',
            'primary_color' => $this->color($this->primary_color, '#7CBFE2'),
            'accent_color' => $this->color($this->accent_color, '#F2A8BE'),
            'background_color' => $this->color($this->background_color, '#FFF9FC'),
            'button_radius' => match ($this->button_radius) {
                'none' => '0px',
                'md' => '6px',
                default => '2px',
            },
            'product_card_padding' => $this->product_card_density === 'compact' ? '0.5rem' : '0.75rem',
        ];
    }

    private function color(?string $value, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? (string) $value : $fallback;
    }

    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (MediaAsset::isExternalUrl($path)) {
            return $path;
        }

        return Storage::disk('public_uploads')->url($path);
    }
}
