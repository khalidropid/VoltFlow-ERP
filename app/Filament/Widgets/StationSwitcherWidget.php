<?php

namespace App\Filament\Widgets;

use App\Support\StationContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class StationSwitcherWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static ?int $sort = 0;

    protected static string $view = 'filament.widgets.station-switcher';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'station_id' => app(StationContext::class)->currentId(),
        ]);
    }

    public function form(Form $form): Form
    {
        $options = app(StationContext::class)
            ->stations()
            ->pluck('name', 'id')
            ->all();

        return $form
            ->schema([
                Select::make('station_id')
                    ->label('المحطة الحالية')
                    ->options($options)
                    ->required()
                    ->native(false),
            ])
            ->statePath('data');
    }

    public function switchStation(): void
    {
        $stationId = (int) ($this->data['station_id'] ?? 0);

        abort_unless($stationId, 422, 'A station must be selected.');

        $station = app(StationContext::class)->set($stationId);

        Notification::make()
            ->success()
            ->title('تم تغيير المحطة')
            ->body('المحطة الحالية: ' . $station->name)
            ->send();

        $this->redirect(request()->header('Referer') ?: '/admin');
    }
}
