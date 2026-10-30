<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Notifications\CustomSmsService;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

/**
 * Super-Admin-only: write a custom SMS to one customer or broadcast it to all
 * customers with a valid phone number. Delivery is queued (see
 * CustomSmsService / SendSms) and every recipient lands in Notification Logs
 * with type "custom".
 */
class SendCustomSms extends Page implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Send SMS';

    protected static ?string $title = 'Send Custom SMS';

    protected static string $view = 'filament.pages.send-custom-sms';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['audience' => 'one', 'only_ordered' => false, 'message' => '']);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Radio::make('audience')
                    ->label('Send to')
                    ->options(['one' => 'One customer', 'all' => 'All customers with a valid phone number'])
                    ->live()
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->label('Customer')
                    ->searchable()
                    ->live()
                    ->getSearchResultsUsing(fn (string $search) => $this->customerSearch($search))
                    ->getOptionLabelUsing(fn ($value) => ($u = User::find($value)) ? $u->name.' - '.$u->phone : null)
                    ->afterStateUpdated(fn (Set $set, $state) => $set('phone', User::find($state)?->phone))
                    ->visible(fn (Get $get) => $get('audience') === 'one')
                    ->required(fn (Get $get) => $get('audience') === 'one'),
                Forms\Components\TextInput::make('phone')
                    ->label('Phone number')
                    ->tel()
                    ->helperText('Prefilled from the customer. Ghana numbers like 0244123456 or +233244123456.')
                    ->visible(fn (Get $get) => $get('audience') === 'one')
                    ->required(fn (Get $get) => $get('audience') === 'one')
                    ->rule(fn () => fn (string $attribute, $value, \Closure $fail) => PhoneNumber::isValid($value) ?: $fail('Enter a valid Ghana mobile number.')),
                Forms\Components\Toggle::make('only_ordered')
                    ->label('Only customers who have placed an order')
                    ->live()
                    ->visible(fn (Get $get) => $get('audience') === 'all'),
                Forms\Components\Textarea::make('message')
                    ->label('Message')
                    ->rows(5)
                    ->required()
                    ->maxLength(612)
                    ->live(debounce: 300)
                    ->helperText(function (Get $get) {
                        $info = CustomSmsService::segments((string) $get('message'));

                        return $info['length'].' characters - '.$info['segments'].' SMS segment(s)'
                            .($info['unicode'] ? ' (contains non-GSM characters: 70 per segment)' : ' (160 per segment, 153 when split)')
                            .'. Use {name} for the first name of the customer.';
                    }),
            ])
            ->statePath('data');
    }

    /** @return array<int, string> */
    protected function customerSearch(string $search): array
    {
        return app(CustomSmsService::class)->customersQuery()
            ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' - '.$u->phone])
            ->all();
    }

    public function sendAction(): Action
    {
        return Action::make('send')
            ->label('Send SMS')
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->modalHeading('Send this SMS?')
            ->modalDescription(function () {
                $data = $this->data;

                if (($data['audience'] ?? 'one') === 'one') {
                    return 'The message will be sent to '.(PhoneNumber::toArkesel($data['phone'] ?? null) ?? 'an invalid number').'.';
                }

                $count = app(CustomSmsService::class)->countRecipients((bool) ($data['only_ordered'] ?? false));

                return 'This will send to '.$count['recipients'].' customer(s) ('.$count['skipped'].' skipped for missing, invalid or duplicate phone). It cannot be undone.';
            })
            ->action(fn () => $this->send());
    }

    public function testAction(): Action
    {
        return Action::make('test')
            ->label('Send test to me')
            ->color('gray')
            ->icon('heroicon-o-device-phone-mobile')
            ->action(function () {
                $message = (string) ($this->data['message'] ?? '');
                $me = auth()->user();

                if (blank($message)) {
                    Notification::make()->title('Write a message first')->warning()->send();

                    return;
                }

                if (! PhoneNumber::isValid($me?->phone)) {
                    Notification::make()->title('Your account has no valid phone number')->danger()->send();

                    return;
                }

                app(CustomSmsService::class)->sendToOne($me->phone, $message, $me);

                Notification::make()->title('Test SMS queued to '.PhoneNumber::toArkesel($me->phone))->success()->send();
            });
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $service = app(CustomSmsService::class);

        if ($data['audience'] === 'one') {
            $service->sendToOne($data['phone'], $data['message'], User::find($data['user_id']));

            Notification::make()->title('SMS queued')->success()->send();
        } else {
            $result = $service->broadcast($data['message'], (bool) ($data['only_ordered'] ?? false));

            Notification::make()
                ->title($result['queued'].' SMS queued')
                ->body($result['skipped'].' skipped (missing, invalid or duplicate phone).')
                ->success()
                ->send();
        }

        $this->form->fill(['audience' => $data['audience'], 'only_ordered' => $data['only_ordered'] ?? false, 'message' => '']);
    }
}
