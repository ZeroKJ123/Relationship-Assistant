<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Catalogs\CreateCar;
use App\Http\Requests\StoreCarRequest;
use App\Models\Car;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class CarController extends Controller
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly CreateCar $create) {}

    public function index(Request $request): View
    {
        return view('catalogs.cars', ['cars' => Car::query()->where('user_id', $request->user()?->id)->latest('id')->paginate(self::PAGE_SIZE), 'fuels' => CreateCar::FUELS, 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(StoreCarRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->create->execute($actor, $request->getData());

        return redirect()->route('cars.index')->with('status', __('Машина добавлена. Заправку можно выбрать как встречное условие в просьбе.'));
    }
}
