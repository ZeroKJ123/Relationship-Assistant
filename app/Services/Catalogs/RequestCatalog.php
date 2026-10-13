<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Models\ExpenseCategory;
use App\Models\RequestTemplate;
use App\Models\SettlementMethod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class RequestCatalog
{
    /** @return array{request_template_id:?int,expense_category_id:?int,template_name:?string,category_name:?string} */
    public function requestSnapshot(NewRequestData $data): array
    {
        $template = $data->templateId === null ? null : RequestTemplate::query()->with('category')->whereKey($data->templateId)->lockForUpdate()->first();
        $expense = $data->expenseCategoryId === null ? null : ExpenseCategory::query()->whereKey($data->expenseCategoryId)->lockForUpdate()->first();
        if (($data->templateId !== null && $template === null) || ($data->expenseCategoryId !== null && $expense === null) || ($template !== null && $template->type !== $data->type)) {
            throw ValidationException::withMessages(['request_template_id' => __('Каталог изменился или шаблон не подходит виду просьбы. Обновите страницу.')]);
        }

        return ['request_template_id' => $template?->id, 'expense_category_id' => $expense?->id, 'template_name' => $template?->name, 'category_name' => $expense->name ?? $template?->category->name];
    }

    /** @return array{type:ConditionType,description:string,method_kind:?string,method_name:?string,sushi_size:?string} */
    public function conditionSnapshot(User $actor, RequestResponseData $data): array
    {
        if ($data->methodId === null) {
            if ($data->sushiSize !== null) {
                $this->deny();
            }

            return ['type' => $data->conditionType ?? ConditionType::Custom, 'description' => $data->description ?? '', 'method_kind' => null, 'method_name' => null, 'sushi_size' => null];
        }
        $method = SettlementMethod::query()->whereKey($data->methodId)->lockForUpdate()->first();
        if ($method === null || ($method->user_id !== null && $method->user_id !== $actor->id) || ! in_array($method->kind, ['action', 'kisses', 'sushi', 'custom', 'money', 'wishlist', 'fuel'], true)) {
            $this->deny();
        }
        if (($method->kind === 'sushi' && ! in_array($data->sushiSize, ['small', 'medium', 'large'], true)) || ($method->kind !== 'sushi' && $data->sushiSize !== null)) {
            $this->deny();
        }
        $size = match ($data->sushiSize) {
            'small' => __('маленький набор'),'medium' => __('средний набор'),'large' => __('большой набор'),default => ''
        };
        $description = sprintf('%s. %s %s %s', __($method->name), __($method->details), $size, $data->description ?? '');
        if (mb_strlen($description) > 2000) {
            $this->deny();
        }

        return ['type' => match ($method->kind) {
            'money' => ConditionType::Money,'wishlist' => ConditionType::Gift,'kisses' => ConditionType::Kisses,'action' => ConditionType::Action,default => ConditionType::Custom
        }, 'description' => trim($description), 'method_kind' => $method->kind, 'method_name' => $method->name, 'sushi_size' => $data->sushiSize];
    }

    private function deny(): never
    {
        throw ValidationException::withMessages(['settlement_method_id' => __('Выберите доступный способ и его детали. Для суши нужен размер набора.')]);
    }
}
