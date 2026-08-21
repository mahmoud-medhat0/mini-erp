<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'RAW',
                'name' => ['en' => 'Raw Materials', 'ar' => 'مواد خام'],
                'description' => ['en' => 'Unprocessed components and raw items', 'ar' => 'المكونات والمواد الأوليةغير المصنعة'],
                'is_active' => true,
            ],
            [
                'code' => 'FG',
                'name' => ['en' => 'Finished Goods', 'ar' => 'منتجات تامة الصنع'],
                'description' => ['en' => 'Completed products ready for sale', 'ar' => 'المنتجات الجاهزة للبيع'],
                'is_active' => true,
            ],
            [
                'code' => 'SERV',
                'name' => ['en' => 'Services', 'ar' => 'خدمات'],
                'description' => ['en' => 'Professional, consulting, and maintenance services', 'ar' => 'الخدمات الاستشارية والفنية والصيانة'],
                'is_active' => true,
            ],
            [
                'code' => 'OFFICE',
                'name' => ['en' => 'Office Supplies', 'ar' => 'أدوات مكتبية ومستلزمات'],
                'description' => ['en' => 'Consumables and office materials', 'ar' => 'المستلزمات والأدوات المكتبية المستهلكة'],
                'is_active' => true,
            ],
        ];

        foreach ($categories as $catData) {
            ProductCategory::query()->updateOrCreate(
                ['code' => $catData['code']],
                [
                    'name' => $catData['name'],
                    'description' => $catData['description'],
                    'is_active' => $catData['is_active'],
                ]
            );
        }
    }
}
