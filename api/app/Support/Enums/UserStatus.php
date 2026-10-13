<?php

namespace App\Support\Enums;

enum UserStatus: string
{
    case Enabled = 'enabled';
    case Pending = 'pending';
    case Disabled = 'disabled';

    public function title(): string
    {
        return match ($this) {
            self::Enabled => 'Enabled',
            self::Pending => 'Pending',
            self::Disabled => 'Disabled'
        };
    }

    public function class(): string
    {
        return match ($this) {
            self::Enabled => 'status-success',
            self::Pending => 'status-info',
            self::Disabled => 'status-danger',
        };
    }
    /**
     * Get values to be used in resource
     */
    public function resource(): array
    {
        return [
            'name' => $this->title(),
            'value' => $this->value,
            'class' => $this->class(),
        ];
    }
}
