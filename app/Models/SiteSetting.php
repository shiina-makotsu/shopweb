<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'contact_info',
        'copyright_text',
        'payment_instructions',
        'payment_qr_path',
        'payment_account_name',
        'payment_account_note',
        'payment_auto_check_enabled',
        'payment_gateway_provider',
        'payment_enabled_methods',
        'payment_gateway_config',
        'payment_gateway_notes',
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
        'support_ai_enabled',
        'support_ai_endpoint',
        'support_ai_api_key',
        'support_ai_model',
        'support_ai_system_prompt',
        'support_ai_idle_minutes',
        'ai_default_endpoint',
        'ai_default_api_key',
        'ai_default_image_endpoint',
        'ai_default_image_api_key',
        'ai_default_chat_endpoint',
        'ai_default_chat_api_key',
        'ai_default_user_quota_k',
    ];

    protected function casts(): array
    {
        return [
            'show_order_numbers_to_users' => 'boolean',
            'show_tracking_numbers_to_users' => 'boolean',
            'payment_auto_check_enabled' => 'boolean',
            'payment_enabled_methods' => 'array',
            'payment_gateway_config' => 'array',
            'enabled_locales' => 'array',
            'home_welcome_enabled' => 'boolean',
            'page_music_enabled' => 'boolean',
            'guide_pet_enabled' => 'boolean',
            'support_ai_enabled' => 'boolean',
        ];
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
