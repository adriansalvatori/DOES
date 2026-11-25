<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case COORDINATOR = 'coordinator';
    case DESIGNER = 'designer';
    case SALES = 'sales';
    case CUSTOMER_SERVICE = 'customer_service';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => __('Administrator'),
            self::COORDINATOR => __('Coordinator / PM'),
            self::DESIGNER => __('Designer'),
            self::SALES => __('Sales'),
            self::CUSTOMER_SERVICE => __('Customer service'),
        };
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::ADMIN => 'bg-purple-100 text-purple-800 border-purple-300',
            self::COORDINATOR => 'bg-blue-100 text-blue-800 border-blue-300',
            self::DESIGNER => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::SALES => 'bg-amber-100 text-amber-800 border-amber-300',
            self::CUSTOMER_SERVICE => 'bg-rose-100 text-rose-800 border-rose-300',
        };
    }
}
