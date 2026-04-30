<?php if (isset($component)) { $__componentOriginal905c8db14136db2e275af46ff5de7fa2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal905c8db14136db2e275af46ff5de7fa2 = $attributes; } ?>
<?php $component = App\View\Components\ShopLayout::resolve(['title' => 'Products'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ShopLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="mb-6">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li><a href="<?php echo e(route('shop.home')); ?>" class="hover:text-amber-600">Home</a></li>
                <li>/</li>
                <li class="text-gray-900 font-medium">Products</li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentCategory): ?>
                <li>/</li>
                <li class="text-gray-900 font-medium"><?php echo e($currentCategory->name); ?></li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ol>
        </nav>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Filters -->
            <aside class="lg:w-64 flex-shrink-0">
                <div class="bg-white rounded-xl shadow p-6 sticky top-24">
                    <h3 class="font-semibold text-gray-900 mb-4">Categories</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="<?php echo e(route('shop.products')); ?>" 
                               class="block py-1 <?php echo e(!$currentCategory ? 'text-amber-600 font-medium' : 'text-gray-600 hover:text-amber-600'); ?>">
                                All Products
                            </a>
                        </li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <li>
                            <a href="<?php echo e(route('shop.products', ['category' => $category->slug])); ?>" 
                               class="block py-1 <?php echo e($currentCategory?->id === $category->id ? 'text-amber-600 font-medium' : 'text-gray-600 hover:text-amber-600'); ?>">
                                <?php echo e($category->name); ?>

                                <span class="text-gray-400 text-sm">(<?php echo e($category->products_count ?? 0); ?>)</span>
                            </a>
                        </li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>

                    <hr class="my-6">

                    <h3 class="font-semibold text-gray-900 mb-4">Price Range</h3>
                    <form action="<?php echo e(route('shop.products')); ?>" method="GET">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentCategory): ?>
                        <input type="hidden" name="category" value="<?php echo e($currentCategory->slug); ?>">
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm text-gray-600">Min (RM)</label>
                                <input type="number" name="min_price" value="<?php echo e(request('min_price')); ?>" 
                                       class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="0">
                            </div>
                            <div>
                                <label class="text-sm text-gray-600">Max (RM)</label>
                                <input type="number" name="max_price" value="<?php echo e(request('max_price')); ?>" 
                                       class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="10000">
                            </div>
                            <button type="submit" 
                                    class="w-full bg-gray-900 text-white py-2 rounded-lg text-sm font-medium hover:bg-gray-800">
                                Apply Filter
                            </button>
                        </div>
                    </form>

                    <hr class="my-6">

                    <h3 class="font-semibold text-gray-900 mb-4">Availability</h3>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="in_stock" 
                               <?php echo e(request('in_stock') ? 'checked' : ''); ?>

                               onchange="this.form.submit()"
                               class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        <span class="text-gray-600">In Stock Only</span>
                    </label>
                </div>
            </aside>

            <!-- Products Grid -->
            <div class="flex-1">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            <?php echo e($currentCategory ? $currentCategory->name : 'All Products'); ?>

                        </h1>
                        <p class="text-gray-500"><?php echo e($products->total()); ?> products found</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="text-sm text-gray-600">Sort by:</label>
                        <select onchange="window.location.href = this.value" 
                                class="border rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <option value="<?php echo e(request()->fullUrlWithQuery(['sort' => 'newest'])); ?>" 
                                    <?php echo e(request('sort', 'newest') === 'newest' ? 'selected' : ''); ?>>Newest</option>
                            <option value="<?php echo e(request()->fullUrlWithQuery(['sort' => 'price_asc'])); ?>"
                                    <?php echo e(request('sort') === 'price_asc' ? 'selected' : ''); ?>>Price: Low to High</option>
                            <option value="<?php echo e(request()->fullUrlWithQuery(['sort' => 'price_desc'])); ?>"
                                    <?php echo e(request('sort') === 'price_desc' ? 'selected' : ''); ?>>Price: High to Low</option>
                            <option value="<?php echo e(request()->fullUrlWithQuery(['sort' => 'name'])); ?>"
                                    <?php echo e(request('sort') === 'name' ? 'selected' : ''); ?>>Name: A-Z</option>
                        </select>
                    </div>
                </div>

                <!-- Products -->
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($products->count() > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <div class="group bg-white border rounded-2xl overflow-hidden hover:shadow-lg transition">
                        <a href="<?php echo e(route('shop.product', $product)); ?>" class="block">
                            <div class="relative aspect-square bg-gray-100">
                                <div class="absolute inset-0 flex items-center justify-center text-6xl">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_contains(strtolower($product->name), 'phone') || str_contains(strtolower($product->name), 'iphone')): ?>
                                        📱
                                    <?php elseif(str_contains(strtolower($product->name), 'laptop') || str_contains(strtolower($product->name), 'macbook')): ?>
                                        💻
                                    <?php elseif(str_contains(strtolower($product->name), 'watch')): ?>
                                        ⌚
                                    <?php elseif(str_contains(strtolower($product->name), 'airpod') || str_contains(strtolower($product->name), 'headphone')): ?>
                                        🎧
                                    <?php elseif(str_contains(strtolower($product->name), 'dress') || str_contains(strtolower($product->name), 'shirt')): ?>
                                        👗
                                    <?php elseif(str_contains(strtolower($product->name), 'chair') || str_contains(strtolower($product->name), 'lamp')): ?>
                                        🪑
                                    <?php elseif(str_contains(strtolower($product->name), 'yoga') || str_contains(strtolower($product->name), 'fitness')): ?>
                                        🧘
                                    <?php elseif(str_contains(strtolower($product->name), 'skin') || str_contains(strtolower($product->name), 'serum')): ?>
                                        🧴
                                    <?php else: ?>
                                        📦
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->compare_price && $product->compare_price > $product->price): ?>
                                <span class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                                    <?php echo e(round((1 - $product->price / $product->compare_price) * 100)); ?>% OFF
                                </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $product->isInStock()): ?>
                                <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                    <span class="bg-white text-gray-900 px-4 py-2 rounded-lg font-semibold">Out of Stock</span>
                                </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </a>
                        <div class="p-4">
                            <p class="text-xs text-gray-500 mb-1"><?php echo e($product->categories->first()?->name ?? 'Uncategorized'); ?></p>
                            <h3 class="font-semibold text-gray-900 group-hover:text-amber-600 transition mb-2">
                                <a href="<?php echo e(route('shop.product', $product)); ?>"><?php echo e($product->name); ?></a>
                            </h3>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->description): ?>
                            <p class="text-sm text-gray-500 mb-3 line-clamp-2"><?php echo e(Str::limit($product->description, 80)); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg font-bold text-amber-600">RM <?php echo e(number_format($product->price / 100, 2)); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->compare_price && $product->compare_price > $product->price): ?>
                                <span class="text-sm text-gray-400 line-through">RM <?php echo e(number_format($product->compare_price / 100, 2)); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="flex items-center justify-between text-xs mb-3">
                                <span class="<?php echo e($product->isInStock() ? 'text-green-600' : 'text-red-600'); ?>">
                                    <?php echo e($product->isInStock() ? '✓ In Stock' : '✗ Out of Stock'); ?>

                                </span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->sku): ?>
                                <span class="text-gray-400">SKU: <?php echo e($product->sku); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <form action="<?php echo e(route('shop.cart.add')); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="product_id" value="<?php echo e($product->id); ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" 
                                        <?php echo e(! $product->isInStock() ? 'disabled' : ''); ?>

                                        class="w-full bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-2 rounded-lg font-medium transition">
                                    Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                <!-- Pagination -->
                <div class="mt-8">
                    <?php echo e($products->withQueryString()->links()); ?>

                </div>
                <?php else: ?>
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">🔍</div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No products found</h3>
                    <p class="text-gray-500 mb-6">Try adjusting your filters or search terms.</p>
                    <a href="<?php echo e(route('shop.products')); ?>" class="text-amber-600 hover:text-amber-700 font-medium">
                        ← Clear all filters
                    </a>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal905c8db14136db2e275af46ff5de7fa2)): ?>
<?php $attributes = $__attributesOriginal905c8db14136db2e275af46ff5de7fa2; ?>
<?php unset($__attributesOriginal905c8db14136db2e275af46ff5de7fa2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal905c8db14136db2e275af46ff5de7fa2)): ?>
<?php $component = $__componentOriginal905c8db14136db2e275af46ff5de7fa2; ?>
<?php unset($__componentOriginal905c8db14136db2e275af46ff5de7fa2); ?>
<?php endif; ?>
<?php /**PATH /Users/saiffil/Herd/commerce/demo/resources/views/shop/products.blade.php ENDPATH**/ ?>