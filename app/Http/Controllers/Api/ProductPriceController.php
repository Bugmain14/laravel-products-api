<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductPriceRequest;
use App\Http\Resources\ProductPriceResource;
use App\Models\Currency;
use App\Models\Product;
use App\Services\PriceConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductPriceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:view,product', only: ['index']),
            new Middleware('can:createPrice,product', only: ['store']),
        ];
    }

    public function index(Product $product): AnonymousResourceCollection
    {
        $prices = $product->prices()->with('currency')->orderBy('id')->get();

        return ProductPriceResource::collection($prices);
    }

    public function store(StoreProductPriceRequest $request, Product $product, PriceConverter $converter): JsonResponse
    {
        $data = $request->validated();
        $target = Currency::findOrFail($data['currency_id']);

        $price = $data['price'] ?? $converter->convert($product->price, $product->currency, $target);

        $productPrice = $product->prices()->create([
            'currency_id' => $target->id,
            'price' => $price,
        ]);
        $productPrice->load('currency');

        return (new ProductPriceResource($productPrice))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
