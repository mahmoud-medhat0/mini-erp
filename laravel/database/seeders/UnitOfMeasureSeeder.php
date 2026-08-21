<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    public function run(): void
    {
        $uoms = [
            [
                'code' => 'PCS',
                'name' => ['en' => 'Piece', 'ar' => 'قطعة'],
                'symbol' => 'pc',
                'is_active' => true,
            ],
            [
                'code' => 'KG',
                'name' => ['en' => 'Kilogram', 'ar' => 'كيلوجرام'],
                'symbol' => 'kg',
                'is_active' => true,
            ],
            [
                'code' => 'M',
                'name' => ['en' => 'Meter', 'ar' => 'متر'],
                'symbol' => 'm',
                'is_active' => true,
            ],
            [
                'code' => 'HR',
                'name' => ['en' => 'Hour', 'ar' => 'ساعة'],
                'symbol' => 'hr',
                'is_active' => true,
            ],
            [
                'code' => 'BOX',
                'name' => ['en' => 'Box', 'ar' => 'صندوق'],
                'symbol' => 'box',
                'is_active' => true,
            ],
        ];

        foreach ($uoms as $uomData) {
            UnitOfMeasure::query()->updateOrCreate(
                ['code' => $uomData['code']],
                [
                    'name' => $uomData['name'],
                    'symbol' => $uomData['symbol'],
                    'is_active' => $uomData['is_active'],
                ]
            );
        }
    }
}
