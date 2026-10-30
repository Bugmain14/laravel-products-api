<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CurrencyController extends Controller implements HasMiddleware
{
    private const IN_USE_MESSAGE = 'The currency is in use by products or product prices and cannot be deleted.';

    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Currency::class, only: ['index']),
            new Middleware('can:view,currency', only: ['show']),
            new Middleware('can:create,'.Currency::class, only: ['store']),
            new Middleware('can:update,currency', only: ['update']),
            new Middleware('can:delete,currency', only: ['destroy']),
        ];
    }

    public function index(): AnonymousResourceCollection
    {
        return CurrencyResource::collection(Currency::orderBy('name')->get());
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $currency = Currency::create($request->validated());

        return (new CurrencyResource($currency))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Currency $currency): CurrencyResource
    {
        return new CurrencyResource($currency);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource
    {
        $currency->update($request->validated());

        return new CurrencyResource($currency);
    }

    public function destroy(Currency $currency): Response
    {
        abort_if($currency->isInUse(), Response::HTTP_CONFLICT, self::IN_USE_MESSAGE);

        try {
            $currency->delete();
        } catch (QueryException $e) {
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            abort(Response::HTTP_CONFLICT, self::IN_USE_MESSAGE);
        }

        return response()->noContent();
    }
}
