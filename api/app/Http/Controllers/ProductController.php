<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Business;
use App\Models\Product;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $membership = $this->membership($request);
        abort_unless($membership->allows('products.view') || $membership->allows('products.manage'), 403);

        $products = $business->products()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->query('type')))
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 50));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'products.manage');
        $this->ensureUniqueSku($business, $request->validated('sku'));
        $product = $business->products()->create($request->validated());
        $this->audit->record($request->user(), $business, 'product.created', $product, null, $product->toArray(), $request);

        return response()->json(['message' => 'Product or service added.', 'product' => new ProductResource($product)], 201);
    }

    public function show(Request $request, Business $business, Product $product): ProductResource
    {
        $this->assertBusiness($business, $product);
        return new ProductResource($product);
    }

    public function update(StoreProductRequest $request, Business $business, Product $product): JsonResponse
    {
        $this->requirePermission($request, 'products.manage');
        $this->assertBusiness($business, $product);
        $this->ensureUniqueSku($business, $request->validated('sku'), $product->id);
        $before = $product->toArray();
        $product->update($request->validated());
        $this->audit->record($request->user(), $business, 'product.updated', $product, $before, $product->fresh()->toArray(), $request);

        return response()->json(['message' => 'Product or service updated.', 'product' => new ProductResource($product->fresh())]);
    }

    public function destroy(Request $request, Business $business, Product $product): JsonResponse
    {
        $this->requirePermission($request, 'products.manage');
        $this->assertBusiness($business, $product);
        $before = $product->toArray();
        $product->delete();
        $this->audit->record($request->user(), $business, 'product.archived', $product, $before, null, $request);

        return response()->json(['message' => 'Product or service archived.']);
    }

    private function assertBusiness(Business $business, Product $product): void
    {
        abort_unless($product->business_id === $business->id, 404);
    }

    private function ensureUniqueSku(Business $business, ?string $sku, ?int $ignoreId = null): void
    {
        if (! $sku) {
            return;
        }

        $exists = $business->products()
            ->withTrashed()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('sku', $sku)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['sku' => 'This SKU is already used in the business.']);
        }
    }
}
