<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Profiles\UpdateAvatar;
use App\Http\Requests\UpdateAvatarRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

final class ProfileAvatarController extends Controller
{
    public function __construct(private readonly UpdateAvatar $updateAvatar) {}

    public function __invoke(UpdateAvatarRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $photo = $request->file('photo');
        abort_unless($actor instanceof User && $photo instanceof UploadedFile, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->updateAvatar->execute($actor, $actor, $photo);

        return redirect()->route('profile.show')->with('status', __('Фото профиля сохранено.'));
    }
}
