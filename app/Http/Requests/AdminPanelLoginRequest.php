<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Admin\PanelAccess;
use Illuminate\Foundation\Http\FormRequest;

final class AdminPanelLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && app(PanelAccess::class)->isOwner($user);
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'max:200']];
    }
}
