<?php

namespace Database\Seeders;

use App\Models\IntegrationSource;
use Illuminate\Database\Seeder;

class IntegrationSeeder extends Seeder
{
    public function run(): void
    {
        IntegrationSource::updateOrCreate(
            ['code' => 'flutter-mobile'],
            [
                'name' => 'Flutter Mobile Collectors',
                'type' => 'flutter',
                'is_active' => true,
            ],
        );

        IntegrationSource::updateOrCreate(
            ['code' => 'legacy-import'],
            [
                'name' => 'Legacy Data Import',
                'type' => 'legacy',
                'is_active' => true,
            ],
        );
    }
}
