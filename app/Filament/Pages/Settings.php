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
            'delivery_weekday_cutoff' => $settings->deliveryWeekdayCutoff(),
            'delivery_weekday_time' => $settings->deliveryWeekdayTime(),
            'delivery_weekend_cutoff' => $settings->deliveryWeekendCutoff(),
            'delivery_weekend_time' => $settings->deliveryWeekendTime(),
            'manual_payment_reservation_hours' => $settings->manualPaymentReservationHours(),
            'manual_payment_account_name' => $settings->manualPaymentAccountName(),
            'manual_payment_account_number' => $settings->manualPaymentAccountNumber(),
            'manual_payment_network' => $settings->manualPaymentNetwork(),
            'manual_payment_instructions' => $settings->manualPaymentInstructions(),
            'order_emails_enabled' => $settings->orderEmailsEnabled(),
        ], $smsTemplates));
    }

    public function form(Form $form): Form
    {
        $smsFields = [];
        foreach (OrderEventType::cases() as $event) {
            $smsFields[] = Forms\Components\Textarea::make('sms_template_'.$event->value)
                ->label($event->label())
                ->rows(2)
                ->helperText('Placeholders: {order_number} {total} {address} {reason} {delivery}')
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

                Forms\Components\Section::make('Delivery Schedule')
                    ->description('Orders placed before the cut-off are delivered the same day; orders at or after it roll to the next day\'s slot. Times are in the shop timezone ('.config('app.timezone').').')
                    ->schema([
                        Forms\Components\TimePicker::make('delivery_weekday_cutoff')
                            ->label('Mon-Fri Order Cut-off')
                            ->seconds(false)->required(),
                        Forms\Components\TimePicker::make('delivery_weekday_time')
                            ->label('Mon-Fri Delivery Time')
                            ->seconds(false)->required(),
                        Forms\Components\TimePicker::make('delivery_weekend_cutoff')
                            ->label('Sat-Sun Order Cut-off')
                            ->seconds(false)->required(),
                        Forms\Components\TimePicker::make('delivery_weekend_time')
                            ->label('Sat-Sun Delivery Time')
                            ->seconds(false)->required(),
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

                Forms\Components\Section::make('Pay by Transfer')
                    ->description('Pilot payment method: the account customers pay outside the system - leave the account fields blank to hide this option at checkout.')
                    ->schema([
                        Forms\Components\TextInput::make('manual_payment_network')
                            ->label('Network / Bank Name')
                            ->placeholder('e.g. MTN Mobile Money')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('manual_payment_account_name')
                            ->label('Account Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('manual_payment_account_number')
                            ->label('Account Number')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('manual_payment_reservation_hours')
                            ->label('Stock Hold Time (hours)')
                            ->numeric()->required()->minValue(1)
                            ->helperText('How long we hold the items while waiting for a staff member to confirm the transfer.'),
                        Forms\Components\Textarea::make('manual_payment_instructions')
                            ->label('Instructions shown to the customer')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Email Notifications')
                    ->description('Customers are emailed when they place an order and whenever its status changes.')
                    ->schema([
                        Forms\Components\Toggle::make('order_emails_enabled')
                            ->label('Send order emails')
                            ->helperText('Turn off to stop all automatic order emails. SMS and in-app notifications are not affected.'),
                    ]),

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
        foreach (['delivery_weekday_cutoff', 'delivery_weekday_time', 'delivery_weekend_cutoff', 'delivery_weekend_time'] as $key) {
            $settings->set($key, substr((string) $state[$key], 0, 5));
        }
        $settings->set('stock_reservation_minutes', (int) $state['stock_reservation_minutes']);
        $settings->set('abandoned_cart_hours', (int) $state['abandoned_cart_hours']);
        $settings->set('order_escalation_hours', (int) $state['order_escalation_hours']);
        $settings->set('manual_payment_reservation_hours', (int) $state['manual_payment_reservation_hours']);
        $settings->set('manual_payment_network', (string) ($state['manual_payment_network'] ?? ''));
        $settings->set('manual_payment_account_name', (string) ($state['manual_payment_account_name'] ?? ''));
        $settings->set('manual_payment_account_number', (string) ($state['manual_payment_account_number'] ?? ''));
        $settings->set('manual_payment_instructions', (string) ($state['manual_payment_instructions'] ?? ''));

        $settings->set('order_emails_enabled', ! empty($state['order_emails_enabled']) ? '1' : '0');

        foreach (OrderEventType::cases() as $event) {
            $key = 'sms_template_'.$event->value;
            $settings->set($key, (string) ($state[$key] ?? ''), 'sms_templates');
        }

        Notification::make()->title('Settings saved')->success()->send();
    }
}
