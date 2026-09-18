<?php

namespace App\Filament\Pages;

use App\Enums\OrderEventType;
use App\Services\SettingsService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Super-Admin-only settings page (TOR §6.10 RBAC - "settings (delivery
 * fee, SMS templates)"). Reads/writes through SettingsService so every
 * other part of the app (cart, checkout, SMS) sees changes immediately.
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administration';

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $smsTemplates = [];
        foreach (OrderEventType::cases() as $event) {
            $smsTemplates['sms_template_'.$event->value] = $settings->smsTemplate($event->value, '');
        }

        $this->form->fill(array_merge([
            'delivery_fee' => $settings->deliveryFee(),
            'pilot_zone_name' => $settings->pilotZoneName(),
            'stock_reservation_minutes' => $settings->stockReservationMinutes(),
            'abandoned_cart_hours' => $settings->abandonedCartHours(),
            'order_escalation_hours' => $settings->orderEscalationHours(),
        ], $smsTemplates));
    }

    public function form(Form $form): Form
    {
        $smsFields = [];
        foreach (OrderEventType::cases() as $event) {
            $smsFields[] = Forms\Components\Textarea::make('sms_template_'.$event->value)
                ->label($event->label())
                ->rows(2)
                ->helperText('Placeholders: {order_number} {total} {address} {reason}')
                ->maxLength(320);
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Delivery & Pilot Zone')
                    ->description('TOR §3.1 - single fixed delivery fee for the pilot zone.')
                    ->schema([
                        Forms\Components\TextInput::make('delivery_fee')
                            ->label('Delivery Fee (GHS)')
                            ->numeric()->required()->minValue(0),
                        Forms\Components\TextInput::make('pilot_zone_name')
                            ->label('Pilot Zone Name')
                            ->required()->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Operational Timers')
                    ->description('TOR §11 fallback & edge-case safeguards.')
                    ->schema([
                        Forms\Components\TextInput::make('stock_reservation_minutes')
                            ->label('Stock Reservation Timeout (minutes)')
                            ->numeric()->required()->minValue(1),
                        Forms\Components\TextInput::make('abandoned_cart_hours')
                            ->label('Abandoned Cart Window (hours)')
                            ->numeric()->required()->minValue(1),
                        Forms\Components\TextInput::make('order_escalation_hours')
                            ->label('Stale Order Escalation (hours)')
                            ->numeric()->required()->minValue(1),
                    ])->columns(3),

                Forms\Components\Section::make('SMS Templates')
                    ->description('Sent on every order-lifecycle event (TOR §6.6).')
                    ->schema($smsFields)
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $settings = app(SettingsService::class);
        $state = $this->form->getState();

        $settings->set('delivery_fee', (float) $state['delivery_fee']);
        $settings->set('pilot_zone_name', $state['pilot_zone_name']);
        $settings->set('stock_reservation_minutes', (int) $state['stock_reservation_minutes']);
        $settings->set('abandoned_cart_hours', (int) $state['abandoned_cart_hours']);
        $settings->set('order_escalation_hours', (int) $state['order_escalation_hours']);

        foreach (OrderEventType::cases() as $event) {
            $key = 'sms_template_'.$event->value;
            $settings->set($key, (string) ($state[$key] ?? ''), 'sms_templates');
        }

        Notification::make()->title('Settings saved')->success()->send();
    }
}
