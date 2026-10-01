<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;
use App\Filament\Resources\PayrollRunResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreatePayrollRun extends CreateRecord {
 protected static string $resource=PayrollRunResource::class;
 private function currentUser(): ?\App\Models\User
 {
     $user = request()->user();
     return $user instanceof \App\Models\User ? $user : null;
 }

 protected function mutateFormDataBeforeCreate(array $data): array
 {
     $data['station_id']=(int)app(StationContext::class)->currentId();
     $data['created_by']=(int)($this->currentUser()?->id ?? 0);
     return $data;
 }
}