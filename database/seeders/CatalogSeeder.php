<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\RequestCategory;
use App\Models\RequestTemplate;
use App\Models\SettlementMethod;
use App\Services\Catalogs\CatalogDefinitions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = app(CatalogDefinitions::class);
        DB::transaction(function () use ($definitions): void {
            foreach ($definitions->expenses() as $index => $name) {
                ExpenseCategory::query()->updateOrCreate(['code' => sprintf('T%d', $index + 1)], ['name' => $name, 'sort_order' => $index + 1]);
            }
            foreach ($definitions->requests() as $index => $name) {
                RequestCategory::query()->updateOrCreate(['code' => sprintf('O%d', $index + 1)], ['name' => $name, 'sort_order' => $index + 1]);
            }
            foreach ($definitions->templates() as $index => $item) {
                RequestTemplate::query()->updateOrCreate(['code' => $item['code']], ['request_category_id' => RequestCategory::query()->where('code', sprintf('O%d', $item['category']))->sole()->id, 'name' => $item['name'], 'type' => $item['type'], 'sort_order' => $index + 1]);
            }
            foreach ($definitions->methods() as $index => $item) {
                SettlementMethod::query()->updateOrCreate(['code' => $item['code']], ['user_id' => null, 'kind' => $item['code'], 'name' => $item['name'], 'details' => $item['details'], 'options' => $item['options'], 'sort_order' => $index + 1]);
            }
        }, attempts: 3);
        Log::info('catalogs.seeded', ['expenses' => count($definitions->expenses()), 'categories' => count($definitions->requests()), 'templates' => count($definitions->templates()), 'methods' => count($definitions->methods())]);
    }
}
