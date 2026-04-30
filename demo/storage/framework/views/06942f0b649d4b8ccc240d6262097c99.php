<?php if (isset($component)) { $__componentOriginal905c8db14136db2e275af46ff5de7fa2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal905c8db14136db2e275af46ff5de7fa2 = $attributes; } ?>
<?php $component = App\View\Components\ShopLayout::resolve(['title' => 'Payment Successful'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ShopLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="max-w-3xl mx-auto px-4 py-16 sm:px-6 lg:px-8 text-center">
        <!-- Success Icon -->
        <div class="mx-auto w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-8">
            <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-900 mb-4">Payment Successful!</h1>
        <p class="text-xl text-gray-600 mb-2">Thank you for your purchase.</p>
        <p class="text-gray-500 mb-8">Order Number: <span class="font-mono font-bold text-gray-900"><?php echo e($order->order_number); ?></span></p>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->paid_at === null): ?>
        <!-- Processing Notice (only shown while waiting for webhook) -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-8">
            <div class="flex items-center justify-center gap-3 mb-3">
                <svg class="animate-spin h-5 w-5 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="font-semibold text-amber-700">Processing Payment Confirmation...</span>
            </div>
            <p class="text-sm text-amber-600">
                We're waiting for payment confirmation from CHIP. This usually takes a few seconds.
                Your order will be updated automatically once payment is confirmed.
            </p>
        </div>
        <?php else: ?>
        <!-- Payment Confirmed Notice -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-8">
            <div class="flex items-center justify-center gap-3 mb-3">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-semibold text-green-700">Payment Confirmed!</span>
            </div>
            <p class="text-sm text-green-600">
                Your payment has been confirmed and your order is being processed.
            </p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <!-- Order Summary Card -->
        <div class="bg-white rounded-2xl shadow-lg p-8 text-left mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>

            <!-- Status -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-6">
                <div>
                    <p class="text-sm text-gray-600">Order Status</p>
                    <p class="font-semibold text-gray-900"><?php echo e($order->status->label()); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Payment Status</p>
                    <p class="font-semibold <?php echo e($order->paid_at !== null ? 'text-green-600' : 'text-amber-600'); ?>">
                        <?php echo e($order->paid_at !== null ? 'Paid' : 'Pending'); ?>

                    </p>
                </div>
            </div>

            <!-- Items -->
            <div class="space-y-4 mb-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="flex justify-between items-center py-3 border-b">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-xl">📦</div>
                        <div>
                            <p class="font-medium text-gray-900"><?php echo e($item->name); ?></p>
                            <p class="text-sm text-gray-500">Qty: <?php echo e($item->quantity); ?></p>
                        </div>
                    </div>
                    <p class="font-medium text-gray-900">RM <?php echo e(number_format($item->total / 100, 2)); ?></p>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <!-- Totals -->
            <div class="space-y-2 border-t pt-4">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span>RM <?php echo e(number_format($order->subtotal / 100, 2)); ?></span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->discount_total > 0): ?>
                <div class="flex justify-between text-green-600">
                    <span>Discount</span>
                    <span>-RM <?php echo e(number_format($order->discount_total / 100, 2)); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="flex justify-between text-gray-600">
                    <span>Shipping</span>
                    <span><?php echo e($order->shipping_total > 0 ? 'RM '.number_format($order->shipping_total / 100, 2) : 'Free'); ?></span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->tax_total > 0): ?>
                <div class="flex justify-between text-gray-600">
                    <span>Tax</span>
                    <span>RM <?php echo e(number_format($order->tax_total / 100, 2)); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="flex justify-between text-xl font-bold text-gray-900 pt-2 border-t">
                    <span>Total</span>
                    <span>RM <?php echo e(number_format($order->grand_total / 100, 2)); ?></span>
                </div>
            </div>

            <!-- Shipping Info -->
            <div class="mt-6 pt-6 border-t">
                <h3 class="font-semibold text-gray-900 mb-3">Shipping Address</h3>
                <div class="text-gray-600">
                    <p><?php echo e($order->shippingAddress?->getFullName() ?? ''); ?></p>
                    <p><?php echo e($order->shippingAddress?->line1 ?? ''); ?></p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($order->shippingAddress?->line2)): ?>
                    <p><?php echo e($order->shippingAddress?->line2); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <p><?php echo e($order->shippingAddress?->city ?? ''); ?>, <?php echo e($order->shippingAddress?->state ?? ''); ?> <?php echo e($order->shippingAddress?->postcode ?? ''); ?></p>
                </div>
            </div>

            <!-- CHIP Payment Info -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->metadata['chip_purchase_id'] ?? null): ?>
            <div class="mt-6 pt-6 border-t">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-gray-600">CHIP Transaction ID</p>
                    <p class="font-mono text-sm text-blue-700"><?php echo e($order->metadata['chip_purchase_id']); ?></p>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- What's Next -->
        <div class="bg-gray-50 rounded-2xl p-8 mb-8">
            <h3 class="font-bold text-gray-900 mb-4">What's Next?</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                <div>
                    <div class="text-3xl mb-2">📧</div>
                    <p class="font-medium text-gray-900">Confirmation Email</p>
                    <p class="text-sm text-gray-500">Check your inbox for order details</p>
                </div>
                <div>
                    <div class="text-3xl mb-2">📦</div>
                    <p class="font-medium text-gray-900">Order Processing</p>
                    <p class="text-sm text-gray-500">We're preparing your order</p>
                </div>
                <div>
                    <div class="text-3xl mb-2">🚚</div>
                    <p class="font-medium text-gray-900">Shipping</p>
                    <p class="text-sm text-gray-500">J&T will deliver to you</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?php echo e(route('shop.products')); ?>" 
               class="bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold transition">
                Continue Shopping
            </a>
            <a href="<?php echo e(route('shop.orders')); ?>" 
               class="border-2 border-gray-300 hover:border-gray-400 text-gray-700 px-8 py-3 rounded-lg font-semibold transition">
                View All Orders
            </a>
        </div>

        <!-- Demo Note -->
        <div class="mt-12 p-4 bg-green-50 rounded-lg">
            <p class="text-sm text-green-700">
                <strong>✅ CHIP Payment Gateway:</strong> Payment was processed through the CHIP sandbox environment.
                In production, this would use real payment credentials.
            </p>
        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        // Auto-refresh to check for webhook confirmation
        let checkCount = 0;
        const maxChecks = 30; // Stop after 30 checks (60 seconds)
        
        function checkPaymentStatus() {
            checkCount++;
            if (checkCount >= maxChecks) return;
            
            fetch(window.location.href, { method: 'HEAD' })
                .then(() => {
                    // Refresh the page to show updated status
                    if (checkCount % 5 === 0) { // Refresh every 10 seconds
                        window.location.reload();
                    }
                });
        }
        
        // Check every 2 seconds
        setInterval(checkPaymentStatus, 2000);
    </script>
    <?php $__env->stopPush(); ?>
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
<?php /**PATH /Users/saiffil/Herd/commerce/demo/resources/views/shop/payment-success.blade.php ENDPATH**/ ?>