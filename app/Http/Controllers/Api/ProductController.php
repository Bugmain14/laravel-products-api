<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Product::class, only: ['index']),
            new Middleware('can:view,product', only: ['show']),
            new Middleware('can:create,'.Product::class, only: ['store']),
            new Middleware('can:update,product', only: ['update']),
            new Middleware('can:delete,product', only: ['destroy']),
            new Middleware('can:viewTrashed,'.Product::class, only: ['trashed']),
            new Middleware('can:restore,product', only: ['restore']),
            new Middleware('can:forceDelete,product', only: ['forceDestroy']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::with('currency')
            ->latest('id')
            ->paginate($this->perPage($request));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        $product->load('currency');

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['currency', 'prices.currency']);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());
        $product->load('currency');

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    public function trashed(Request $request): AnonymousResourceCollection
    {
        $products = Product::onlyTrashed()
            ->with('currency')
            ->latest('deleted_at')
            ->paginate($this->perPage($request));

        return ProductResource::collection($products);
    }

    public function restore(Product $product): ProductResource
    {
        abort_unless($product->trashed(), Response::HTTP_CONFLICT, 'The product is not deleted.');

        $product->restore();
        $product->load(['currency', 'prices.currency']);

        return new ProductResource($product);
    }

    public function forceDestroy(Product $product): Response
    {
        abort_unless(
            $product->trashed(),
            Response::HTTP_CONFLICT,
            'The product must be deleted before it can be permanently removed.'
        );

        $product->forceDelete();

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 15), 1), 100);
    }
}
