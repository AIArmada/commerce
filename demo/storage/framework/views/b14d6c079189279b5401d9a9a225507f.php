<?php if (isset($component)) { $__componentOriginal905c8db14136db2e275af46ff5de7fa2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal905c8db14136db2e275af46ff5de7fa2 = $attributes; } ?>
<?php $component = App\View\Components\ShopLayout::resolve(['title' => 'Shopping Cart'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ShopLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cartItems->count() > 0): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2 space-y-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $cartItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="bg-white rounded-xl shadow p-6 flex gap-6">
                    <!-- Product Image -->
                    <div class="w-24 h-24 bg-gray-100 rounded-lg flex items-center justify-center text-4xl flex-shrink-0">
                        📦
                    </div>

                    <!-- Product Details -->
                    <div class="flex-1">
                        <div class="flex justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900"><?php echo e($item->name); ?></h3>
                                <p class="text-sm text-gray-500">SKU: <?php echo e($item->attributes['sku'] ?? 'N/A'); ?></p>
                            </div>
                            <form action="<?php echo e(route('shop.cart.remove', $item->id)); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('DELETE'); ?>
                                <button type="submit" class="text-gray-400 hover:text-red-500">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </form>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <!-- Quantity -->
                            <form action="<?php echo e(route('shop.cart.update', $item->id)); ?>" method="POST" class="flex items-center gap-2">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('PATCH'); ?>
                                <div class="flex items-center border rounded-lg">
                                    <button type="submit" name="quantity" value="<?php echo e(max(1, $item->quantity - 1)); ?>"
                                            class="px-3 py-1 text-gray-600 hover:bg-gray-100">−</button>
                                    <span class="w-12 text-center py-1"><?php echo e($item->quantity); ?></span>
                                    <button type="submit" name="quantity" value="<?php echo e($item->quantity + 1); ?>"
                                            class="px-3 py-1 text-gray-600 hover:bg-gray-100">+</button>
                                </div>
                            </form>

                            <!-- Price -->
                            <div class="text-right">
                                <p class="text-lg font-bold text-gray-900">RM <?php echo e(number_format($item->getRawSubtotal() / 100, 2)); ?></p>
                                <p class="text-sm text-gray-500">RM <?php echo e(number_format($item->price / 100, 2)); ?> each</p>
                            </div>
                        </div>

                        <!-- Item Conditions (discounts) -->
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->conditions && count($item->conditions) > 0): ?>
                        <div class="mt-3 space-y-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $item->conditions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $condition): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <div class="flex items-center gap-2 text-sm text-green-600">
                                <span>🎫</span>
                                <span><?php echo e($condition->getName()); ?>: -RM <?php echo e(number_format(abs((float) $condition->getValue()) / 100, 2)); ?></span>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <!-- Continue Shopping -->
                <div class="text-center py-4">
                    <a href="<?php echo e(route('shop.products')); ?>" class="text-amber-600 hover:text-amber-700 font-medium">
                        ← Continue Shopping
                    </a>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>

                    <!-- Apply Voucher -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Voucher Code</label>
                        <form action="<?php echo e(route('shop.cart.voucher')); ?>" method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="flex gap-2">
                                <input type="text" name="voucher_code" placeholder="Enter code" 
                                       value=""
                                       class="flex-1 border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                <button type="submit" 
                                        class="bg-gray-900 text-white px-4 py-2 rounded-lg font-medium hover:bg-gray-800">
                                    Apply
                                </button>
                            </div>
                        </form>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($appliedVouchers) > 0): ?>
                        <div class="mt-4 space-y-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $appliedVouchers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <div class="flex items-center justify-between text-sm bg-green-50 p-2 rounded border border-green-100">
                                <span class="text-green-700 font-medium">✓ <?php echo e($code); ?></span>
                                <form action="<?php echo e(route('shop.cart.voucher.remove')); ?>" method="POST" class="inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="voucher_code" value="<?php echo e($code); ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-600 font-medium">Remove</button>
                                </form>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <!-- Totals -->
                    <div class="space-y-3 border-t pt-4">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal (<?php echo e($cartQuantity); ?> items)</span>
                            <span>RM <?php echo e(number_format($cartSubtotal / 100, 2)); ?></span>
                        </div>

                        <?php
                            $totalVoucherDiscount = 0;
                            $vouchers = collect($cartConditions)->filter(fn($c) => ($c['type'] ?? '') === 'voucher');
                        ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $vouchers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $cond): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <div class="flex justify-between text-green-600 text-sm">
                                <span>🎫 <?php echo e($name); ?></span>
                                <?php
                                    // Use the same logic as Cart::getVoucherDiscount() or just calculate from conditions
                                    // But since we are already in the view and have conditions, let's show their parsed values if possible
                                    // Actually, let's just show the calculated value if we had it.
                                    // For now, let's use a simpler approach since we know it's a discount.
                                ?>
                                <span>Applied</span>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cartSubtotal > $cartTotal): ?>
                        <div class="flex justify-between text-green-600 font-bold border-t border-dashed pt-2">
                            <span>Total Discount</span>
                            <span>-RM <?php echo e(number_format(($cartSubtotal - $cartTotal) / 100, 2)); ?></span>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span class="text-green-600">Free</span>
                        </div>

                        <hr>

                        <div class="flex justify-between text-xl font-bold text-gray-900">
                            <span>Total</span>
                            <span>RM <?php echo e(number_format($cartTotal / 100, 2)); ?></span>
                        </div>
                    </div>

                    <!-- Checkout Button -->
                    <a href="<?php echo e(route('shop.checkout')); ?>" 
                       class="mt-6 block w-full bg-amber-500 hover:bg-amber-600 text-white text-center py-3 rounded-lg font-semibold text-lg transition">
                        Proceed to Checkout
                    </a>

                    <!-- Affiliate Info -->
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('affiliate_code')): ?>
                    <div class="mt-4 p-3 bg-green-50 rounded-lg text-sm">
                        <p class="text-green-800">
                            🤝 You're supporting affiliate: <strong><?php echo e(session('affiliate_code')); ?></strong>
                        </p>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <!-- Payment Methods -->
                    <div class="mt-6 text-center">
                        <p class="text-xs text-gray-500 mb-2">Secure payment with</p>
                        <div class="flex justify-center gap-4 text-2xl">
                            <span title="Credit Card">💳</span>
                            <span title="FPX">🏦</span>
                            <span title="E-Wallet">📱</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Empty Cart -->
        <div class="text-center py-16">
            <div class="text-8xl mb-6">🛒</div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Your cart is empty</h2>
            <p class="text-gray-500 mb-8">Looks like you haven't added any products yet.</p>
            <a href="<?php echo e(route('shop.products')); ?>" 
               class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold transition">
                Start Shopping
            </a>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <!-- Active Vouchers Hint -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeVouchers->count() > 0): ?>
        <div class="mt-12 bg-amber-50 rounded-xl p-6">
            <h3 class="font-semibold text-amber-800 mb-4">🎉 Available Vouchers</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $activeVouchers->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="bg-white rounded-lg p-4 border border-amber-200">
                    <p class="font-mono font-bold text-amber-600"><?php echo e($voucher->code); ?></p>
                    <p class="text-sm text-gray-600">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($voucher->type->value === 'percentage'): ?>
                            <?php echo e($voucher->value / 100); ?>% OFF
                        <?php else: ?>
                            RM <?php echo e(number_format($voucher->value / 100, 2)); ?> OFF
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($voucher->min_cart_value): ?>
                    <p class="text-xs text-gray-500">Min. order: RM <?php echo e(number_format($voucher->min_cart_value / 100, 2)); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /Users/saiffil/Herd/commerce/demo/resources/views/shop/cart.blade.php ENDPATH**/ ?>