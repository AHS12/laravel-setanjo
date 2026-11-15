<?php

namespace Ahs12\Setanjo\Tests\Fixtures;

use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Traits\DefinesSetting;

enum TestSetting: string implements SettingDefinition
{
    use DefinesSetting;

    case COMPANY_NAME = 'company_name';
    case RETENTION_DAYS = 'retention_days';
    case FREQUENCY = 'frequency';
    case AUTO_APPROVE = 'auto_approve';
    case PHONE = 'phone';
    case COMPANY_ONLY = 'company_only';

    // feature flags
    case TAX_FEATURE = 'tax_feature';
    case BETA_FEATURE = 'beta_feature';

    public function default(): mixed
    {
        return match ($this) {
            self::COMPANY_NAME => 'Acme',
            self::RETENTION_DAYS => 180,
            self::FREQUENCY => 'daily',
            self::AUTO_APPROVE, self::TAX_FEATURE => false,
            self::BETA_FEATURE => true,
            self::PHONE, self::COMPANY_ONLY => null,
        };
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::PHONE, self::COMPANY_ONLY => SettingType::STRING,
            default => $this->isFeature() ? SettingType::BOOLEAN : SettingType::fromValue($this->default()),
        };
    }

    public function group(): ?string
    {
        return match ($this) {
            self::COMPANY_NAME, self::PHONE => 'Company',
            self::RETENTION_DAYS => 'audit',
            default => null,
        };
    }

    public function description(): ?string
    {
        return match ($this) {
            self::RETENTION_DAYS => 'How long audit entries are kept',
            default => null,
        };
    }

    public function rules(): array|string
    {
        return match ($this) {
            self::COMPANY_NAME => 'string|max:50',
            default => [],
        };
    }

    public function meta(): array
    {
        return match ($this) {
            self::RETENTION_DAYS => ['unit' => 'days', 'min' => 1, 'max' => 3650],
            self::FREQUENCY => ['input' => 'select', 'options' => ['daily' => 'Daily', 'weekly' => 'Weekly']],
            default => [],
        };
    }

    public function isFeature(): bool
    {
        return in_array($this, [self::TAX_FEATURE, self::BETA_FEATURE], true);
    }

    public function scopes(): array
    {
        return $this === self::COMPANY_ONLY ? [Company::class] : [];
    }
}
