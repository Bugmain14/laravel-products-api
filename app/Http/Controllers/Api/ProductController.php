<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateProductWithPrices;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

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

    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        return $this->listing(Product::query(), $request, 'id');
    }

    public function store(StoreProductRequest $request, CreateProductWithPrices $action): JsonResponse
    {
        $product = $action->handle($request->validated());

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
        DB::transaction(fn () => $product->update($request->validated()));
        $product->load('currency');

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    public function trashed(IndexProductRequest $request): AnonymousResourceCollection
    {
        return $this->listing(Product::onlyTrashed(), $request, 'deleted_at');
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

    private function listing(Builder $query, IndexProductRequest $request, string $defaultSort): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $products = $query->with('currency')
            ->filter($filters, $defaultSort)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return ProductResource::collection($products);
    }
}
