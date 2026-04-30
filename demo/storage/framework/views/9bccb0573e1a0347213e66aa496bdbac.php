<?php if (isset($component)) { $__componentOriginal905c8db14136db2e275af46ff5de7fa2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal905c8db14136db2e275af46ff5de7fa2 = $attributes; } ?>
<?php $component = App\View\Components\ShopLayout::resolve(['title' => $product->name] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
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
                <li><a href="<?php echo e(route('shop.products')); ?>" class="hover:text-amber-600">Products</a></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->categories->isNotEmpty()): ?>
                <li>/</li>
                <li><a href="<?php echo e(route('shop.products', ['category' => $product->categories->first()?->slug])); ?>" class="hover:text-amber-600"><?php echo e($product->categories->first()?->name); ?></a></li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <li>/</li>
                <li class="text-gray-900 font-medium"><?php echo e($product->name); ?></li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Product Image -->
            <div class="bg-gray-100 rounded-2xl aspect-square flex items-center justify-center relative">
                <span class="text-9xl">
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
                </span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->compare_price && $product->compare_price > $product->price): ?>
                <span class="absolute top-4 left-4 bg-red-500 text-white text-sm font-bold px-3 py-1 rounded">
                    <?php echo e(round((1 - $product->price / $product->compare_price) * 100)); ?>% OFF
                </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- Product Details -->
            <div>
                <div class="mb-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->categories->isNotEmpty()): ?>
                    <a href="<?php echo e(route('shop.products', ['category' => $product->categories->first()?->slug])); ?>" 
                       class="text-amber-600 text-sm font-medium hover:text-amber-700">
                        <?php echo e($product->categories->first()?->name); ?>

                    </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <h1 class="text-3xl font-bold text-gray-900 mb-4"><?php echo e($product->name); ?></h1>

                <!-- Price -->
                <div class="flex items-baseline gap-4 mb-6">
                    <span class="text-4xl font-bold text-amber-600">RM <?php echo e(number_format($product->price / 100, 2)); ?></span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->compare_price && $product->compare_price > $product->price): ?>
                    <span class="text-xl text-gray-400 line-through">RM <?php echo e(number_format($product->compare_price / 100, 2)); ?></span>
                    <span class="text-sm bg-red-100 text-red-700 px-2 py-1 rounded font-medium">
                        Save RM <?php echo e(number_format(($product->compare_price - $product->price) / 100, 2)); ?>

                    </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Stock Status -->
                <div class="flex items-center gap-4 mb-6 p-4 rounded-lg <?php echo e($product->isInStock() ? 'bg-green-50' : 'bg-red-50'); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->isInStock()): ?>
                    <svg class="h-6 w-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-semibold text-green-800">In Stock</p>
                        <p class="text-sm text-green-600">Available now</p>
                    </div>
                    <?php else: ?>
                    <svg class="h-6 w-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-semibold text-red-800">Out of Stock</p>
                        <p class="text-sm text-red-600">This product is currently unavailable</p>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Description -->
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->description): ?>
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-900 mb-2">Description</h3>
                    <p class="text-gray-600 leading-relaxed"><?php echo e($product->description); ?></p>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <!-- Product Info -->
                <div class="border-t border-b py-4 mb-6">
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->sku): ?>
                        <div>
                            <dt class="text-gray-500">SKU</dt>
                            <dd class="font-medium text-gray-900"><?php echo e($product->sku); ?></dd>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div>
                            <dt class="text-gray-500">Category</dt>
                            <dd class="font-medium text-gray-900"><?php echo e($product->categories->first()?->name ?? 'Uncategorized'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Currency</dt>
                            <dd class="font-medium text-gray-900"><?php echo e($product->currency ?? 'MYR'); ?></dd>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->tracksInventory()): ?>
                        <div>
                            <dt class="text-gray-500">Stock Tracking</dt>
                            <dd class="font-medium text-green-600">Enabled</dd>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </dl>
                </div>

                <!-- Add to Cart -->
                <form action="<?php echo e(route('shop.cart.add')); ?>" method="POST" class="space-y-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="product_id" value="<?php echo e($product->id); ?>">
                    
                    <div class="flex items-center gap-4">
                        <label for="quantity" class="font-medium text-gray-900">Quantity:</label>
                        <div class="flex items-center border rounded-lg">
                            <button type="button" onclick="decrementQty()" 
                                    class="px-4 py-2 text-gray-600 hover:bg-gray-100">−</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" 
                                   max=""
                                   class="w-16 text-center border-0 focus:ring-0">
                                <button type="button" onclick="incrementQty(null)" 
                                    class="px-4 py-2 text-gray-600 hover:bg-gray-100">+</button>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" 
                                <?php echo e(! $product->isInStock() ? 'disabled' : ''); ?>

                                class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-3 rounded-lg font-semibold text-lg transition">
                            <?php echo e(! $product->isInStock() ? 'Out of Stock' : 'Add to Cart'); ?>

                        </button>
                        <button type="button" 
                                class="px-4 py-3 border-2 border-gray-300 rounded-lg hover:border-amber-500 transition">
                            <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                    </div>
                </form>

                <!-- Buy Now -->
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->isInStock()): ?>
                <form action="<?php echo e(route('shop.checkout.buy-now')); ?>" method="POST" class="mt-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="product_id" value="<?php echo e($product->id); ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" 
                            class="w-full bg-gray-900 hover:bg-gray-800 text-white py-3 rounded-lg font-semibold text-lg transition">
                        Buy Now
                    </button>
                </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <!-- Trust Badges -->
                <div class="mt-8 grid grid-cols-3 gap-4 text-center text-sm">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl mb-1">🚚</div>
                        <p class="font-medium text-gray-900">Free Shipping</p>
                        <p class="text-gray-500">Orders over RM100</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl mb-1">🔒</div>
                        <p class="font-medium text-gray-900">Secure Payment</p>
                        <p class="text-gray-500">CHIP Gateway</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl mb-1">↩️</div>
                        <p class="font-medium text-gray-900">Easy Returns</p>
                        <p class="text-gray-500">30-day policy</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($relatedProducts->count() > 0): ?>
        <section class="mt-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Related Products</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $relatedProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $related): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="group bg-white border rounded-2xl overflow-hidden hover:shadow-lg transition">
                    <a href="<?php echo e(route('shop.product', $related)); ?>" class="block">
                        <div class="aspect-square bg-gray-100 flex items-center justify-center text-5xl">
                            📦
                        </div>
                    </a>
                    <div class="p-4">
                        <h3 class="font-semibold text-gray-900 group-hover:text-amber-600 transition mb-2 line-clamp-1">
                            <a href="<?php echo e(route('shop.product', $related)); ?>"><?php echo e($related->name); ?></a>
                        </h3>
                        <p class="text-lg font-bold text-amber-600">RM <?php echo e(number_format($related->price / 100, 2)); ?></p>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <script>
        function incrementQty(max) {
            const input = document.getElementById('quantity');
            const current = parseInt(input.value);
            const limit = Number(max);

            if (Number.isNaN(limit) || limit <= 0) {
                input.value = current + 1;

                return;
            }

            if (current < limit) {
                input.value = current + 1;
            }
        }
        
        function decrementQty() {
            const input = document.getElementById('quantity');
            const current = parseInt(input.value);
            if (current > 1) {
                input.value = current - 1;
            }
        }
    </script>
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
<?php /**PATH /Users/saiffil/Herd/commerce/demo/resources/views/shop/product.blade.php ENDPATH**/ ?>