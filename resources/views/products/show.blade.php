<x-layouts.app :title="$product->title">
    @php
        $mainMedia = $product->media->first();
        $firstVariant = $product->variants->first();
        $totalStock = $product->variants->sum('stock');
        $isSoldOut = $product->isSoldOut() || ($product->status === \App\Models\Product::STATUS_PUBLISHED && $totalStock <= 0);
        $isPurchasable = $product->isDirectlyPurchasable();
        $allowsVoting = $product->allowsVoting();
        $allowsCrowdfunding = $product->allowsCrowdfunding() && $firstVariant;
    @endphp

    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2 text-sm text-slate-600">
            <a class="hover:text-blue-800" href="{{ route('home') }}">首页</a>
            <span class="mx-1">/</span>
            <a class="hover:text-blue-800" href="{{ route('products.index') }}">商品</a>
            @if($product->category)
                <span class="mx-1">/</span>
                <a class="hover:text-blue-800" href="{{ route('products.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a>
            @endif
        </div>

        <div class="grid gap-6 p-4 lg:grid-cols-[minmax(280px,430px)_1fr]">
            <section>
                <div class="relative border border-slate-200 bg-white p-2">
                    @if($isSoldOut)
                        <span class="absolute left-4 top-4 z-10 rounded-sm bg-slate-950/85 px-3 py-1 text-sm font-semibold text-white shadow">售罄</span>
                    @endif
                    @if($mainMedia)
                        @if($mainMedia->isVideo())
                            <video src="{{ $mainMedia->url() }}" class="aspect-square w-full bg-black object-contain {{ $isSoldOut ? 'grayscale' : '' }}" controls preload="metadata"></video>
                        @else
                            <img src="{{ $mainMedia->url() }}" alt="{{ $mainMedia->alt ?? $product->title }}" class="aspect-square w-full object-cover {{ $isSoldOut ? 'grayscale' : '' }}">
                        @endif
                    @else
                        <div class="flex aspect-square items-center justify-center bg-slate-100 text-sm text-slate-500">暂无图片</div>
                    @endif
                </div>

                @if($product->media->count() > 1)
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach($product->media as $media)
                            <figure class="border border-slate-200 bg-white p-1">
                                @if($media->isVideo())
                                    <video src="{{ $media->url() }}" class="aspect-square w-full bg-black object-contain" muted preload="metadata"></video>
                                @else
                                    <img src="{{ $media->url() }}" alt="{{ $media->alt ?? $product->title }}" class="aspect-square w-full object-cover">
                                @endif
                                <figcaption class="truncate pt-1 text-center text-[11px] text-slate-500">{{ $media->type === 'concept' ? '概念' : '预览' }}{{ $media->isVideo() ? '视频' : '图' }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="min-w-0">
                <p class="mb-1 text-sm text-slate-500">{{ $product->category?->name ?? '未分类' }}</p>
                <div class="flex flex-wrap items-start gap-3">
                    <h1 class="text-2xl font-semibold leading-8">{{ $product->title }}</h1>
                    <span class="rounded-sm border {{ $isSoldOut ? 'border-slate-500 bg-slate-900 text-white' : 'border-blue-200 bg-blue-50 text-blue-700' }} px-2 py-1 text-xs font-medium">{{ $isSoldOut ? '售罄' : $product->statusLabel() }}</span>
                </div>

                @if($product->summary)
                    <p class="mt-3 text-sm leading-6 text-slate-700">{{ $product->summary }}</p>
                @endif

                @if($product->tags->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($product->tags as $tag)
                            <a class="rounded-sm border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-800 hover:border-blue-400 hover:bg-blue-100" href="{{ route('tags.show', $tag) }}"># {{ $tag->name }}</a>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 rounded-sm border border-red-200 bg-red-50 px-4 py-3">
                    <p class="text-sm text-slate-600">{{ $product->isConcept() ? '预计价格' : '售价' }}</p>
                    <p class="text-3xl font-semibold text-red-700">@money($product->starting_price_cents)</p>
                    @if($firstVariant?->hasActiveDiscount())
                        <p class="text-sm text-slate-500">原价 <span class="line-through">@money($firstVariant->price_cents)</span></p>
                        <p class="mt-1 text-xs text-red-700">限时折扣中</p>
                    @elseif($firstVariant?->compare_at_price_cents)
                        <p class="text-sm text-slate-500">原价 <span class="line-through">@money($firstVariant->compare_at_price_cents)</span></p>
                    @endif
                </div>

                <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <dt class="text-slate-500">{{ $product->isIncoming() ? '进货数量' : '库存' }}</dt>
                        <dd class="font-medium">{{ $product->isIncoming() ? $product->incoming_quantity : $totalStock }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <dt class="text-slate-500">交付</dt>
                        <dd class="font-medium">{{ $product->fulfillmentLabel() }}</dd>
                    </div>
                </dl>

                @if($product->isConcept())
                    <div class="mt-5 rounded-sm border border-pink-200 bg-pink-50 p-4 text-sm leading-6 text-slate-700">
                        这是概念商品，当前用于收集购买意愿、价格区间反馈和筹款记录。概念商品没有库存限制，筹款订单会走正常付款和后台确认流程，方便后续按订单溯源。
                    </div>
                    @if($product->crowdfunding_enabled)
                        <div class="mt-3 rounded-sm border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-slate-700">
                            <p class="font-medium">筹款目标：@money($product->crowdfunding_goal_cents)</p>
                            @if($product->crowdfunding_reward)
                                <p class="mt-2 whitespace-pre-line">{{ $product->crowdfunding_reward }}</p>
                            @endif
                        </div>
                    @endif
                    @if($allowsCrowdfunding)
                        <form method="post" action="{{ route('cart.buy-now') }}" class="mt-4 rounded-sm border border-slate-300 bg-slate-50 p-4">
                            @csrf
                            <input type="hidden" name="variant_id" value="{{ $firstVariant->id }}">
                            <label class="block">
                                <span class="text-sm font-medium">筹款数量</span>
                                <input class="mt-1 w-28 rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" type="number" min="1" max="999" name="quantity" value="1" required>
                            </label>
                            <button class="mt-3 rounded-sm border border-pink-600 bg-pink-600 px-5 py-2 text-sm font-medium text-white hover:bg-pink-700" type="submit">参与筹款</button>
                        </form>
                    @endif
                @elseif($product->isIncoming())
                    <div class="mt-5 rounded-sm border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-slate-700">
                        <p class="font-medium">该批次正在进货中，商店内可查看但暂不允许购买。</p>
                        <p class="mt-2">承运商：{{ $product->shippingCarrier?->name ?? '暂无' }}</p>
                        @if($product->tracking_url)
                            <a class="mt-3 inline-flex rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" href="{{ $product->tracking_url }}" target="_blank" rel="noopener">查看国际物流进度</a>
                        @else
                            <p class="mt-2 text-slate-600">后台尚未配置公开物流链接。</p>
                        @endif
                    </div>
                @elseif($isSoldOut)
                    <div class="mt-5 rounded-sm border border-slate-300 bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                        该商品已售罄，暂时不可购买。
                    </div>
                @elseif($isPurchasable)
                    <div class="mt-5 rounded-sm border border-slate-300 bg-slate-50 p-4">
                        @if($product->isPresale())
                            <p class="mb-3 rounded-sm border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-slate-700">该商品为预售品，付款确认后会进入待发货；实际进货后后台会更新为进货中。</p>
                        @endif
                        <label class="block">
                            <span class="text-sm font-medium">规格</span>
                            <select id="product-detail-variant" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" required>
                                @foreach($product->variants as $variant)
                                    <option value="{{ $variant->id }}">{{ $variant->specLabel() }} / @money($variant->effectivePriceCents()) / {{ $product->isPresale() ? '预售' : '库存 '.$variant->stock }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            <label class="block">
                                <span class="text-sm font-medium">数量</span>
                                <input id="product-detail-quantity" class="mt-1 w-28 rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" type="number" min="1" max="999" value="1" required>
                            </label>
                            <form method="post" action="{{ route('cart.items.store') }}" data-cart-add-form data-product-title="{{ $product->title }}" onsubmit="this.variant_id.value = document.getElementById('product-detail-variant').value; this.quantity.value = document.getElementById('product-detail-quantity').value;">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $product->variants->first()?->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button class="rounded-sm border border-blue-700 bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">{{ $product->isPresale() ? '加入预售购物车' : '加入购物车' }}</button>
                            </form>
                            <form method="post" action="{{ route('cart.buy-now') }}" onsubmit="this.variant_id.value = document.getElementById('product-detail-variant').value; this.quantity.value = document.getElementById('product-detail-quantity').value;">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $product->variants->first()?->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button class="rounded-sm border border-emerald-700 bg-emerald-700 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="submit">{{ $product->isPresale() ? '预售下单' : '立即购买' }}</button>
                            </form>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </section>

    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">商品详情</h2>
        </div>
        <div class="px-4 py-4">
            @if($product->description)
                <div class="prose max-w-none text-sm">{!! $product->description !!}</div>
            @else
                <p class="text-sm text-slate-600">暂无详情说明。</p>
            @endif
        </div>
    </section>

    @if($allowsVoting)
    <section id="concept-votes" class="grid gap-4 md:grid-cols-2">
        <div class="rounded-sm border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
                <h2 class="text-base font-semibold">购买意愿</h2>
            </div>
            <div class="px-4 py-4">
                @auth
                    <form method="post" action="{{ route('votes.intent', $product) }}" class="space-y-3">
                        @csrf
                        @foreach(['want' => '想买', 'considering' => '考虑中', 'not_now' => '暂时不买'] as $value => $label)
                            <label class="flex items-center justify-between gap-3 rounded-sm border border-slate-200 px-3 py-2 text-sm">
                                <span class="flex items-center gap-2">
                                    <input type="radio" name="intent" value="{{ $value }}" @checked($myIntentVote?->intent === $value)>
                                    {{ $label }}
                                </span>
                                <span class="text-slate-500">{{ $intentCounts[$value] ?? 0 }} 票</span>
                            </label>
                        @endforeach
                        <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">提交投票</button>
                    </form>
                @else
                    <p class="text-sm text-slate-600">登录后可投票。</p>
                @endauth
            </div>
        </div>

        <div class="rounded-sm border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
                <h2 class="text-base font-semibold">价格区间</h2>
            </div>
            <div class="px-4 py-4">
                @auth
                    <form method="post" action="{{ route('votes.price', $product) }}" class="space-y-3">
                        @csrf
                        @forelse($priceOptions as $option)
                            <label class="flex items-center justify-between gap-3 rounded-sm border border-slate-200 px-3 py-2 text-sm">
                                <span class="flex items-center gap-2">
                                    <input type="radio" name="price_vote_option_id" value="{{ $option->id }}" @checked($myPriceVote?->price_vote_option_id === $option->id)>
                                    {{ $option->label }}
                                </span>
                                <span class="text-slate-500">{{ $option->votes_count }} 票</span>
                            </label>
                        @empty
                            <p class="text-sm text-slate-600">暂无价格投票选项。</p>
                        @endforelse

                        @if($priceOptions->isNotEmpty())
                            <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">提交投票</button>
                        @endif
                    </form>
                @else
                    <p class="text-sm text-slate-600">登录后可投票。</p>
                @endauth
            </div>
        </div>
    </section>
    @endif

    <section class="mt-4 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">商品评论</h2>
        </div>
        @if($product->comments_enabled)
            <div class="divide-y divide-slate-100">
                @forelse($product->comments as $comment)
                    <article class="px-4 py-4 text-sm">
                        <div class="flex items-start gap-3">
                            <a class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white font-semibold text-blue-700" href="{{ route('users.show', $comment->user) }}">
                                @if($comment->user->avatar_path)
                                    <img class="h-full w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public_uploads')->url($comment->user->avatar_path) }}" alt="{{ $comment->user->displayName() }}">
                                @else
                                    {{ mb_substr($comment->user->displayName(), 0, 1) }}
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a class="font-medium hover:text-blue-800" href="{{ route('users.show', $comment->user) }}">{{ $comment->user->displayName() }}</a>
                                    <span class="text-yellow-600">{{ str_repeat('★', $comment->rating) }}{{ str_repeat('☆', 5 - $comment->rating) }}</span>
                                    <span class="text-xs text-slate-500">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
                                    @auth
                                        @if(auth()->id() !== $comment->user_id)
                                            <a class="text-xs text-blue-700 hover:text-blue-900" href="{{ route('messages.thread', $comment->user) }}">私聊</a>
                                        @endif
                                    @endauth
                                </div>
                                <p class="mt-2 whitespace-pre-line text-slate-700">{{ $comment->body }}</p>
                                @if($comment->image_paths)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($comment->image_paths as $path)
                                            <img class="h-20 w-20 rounded-sm border border-slate-200 object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public_uploads')->url($path) }}" alt="评论图片">
                                        @endforeach
                                    </div>
                                @endif
                                @foreach($comment->replies as $reply)
                                    <div class="mt-3 rounded-sm border border-slate-200 bg-slate-50 px-3 py-2">
                                        <p class="text-xs text-slate-500">
                                            <a class="font-medium text-slate-700 hover:text-blue-800" href="{{ route('users.show', $reply->user) }}">{{ $reply->user->displayName() }}</a>
                                            / {{ $reply->created_at->format('Y-m-d H:i') }}
                                        </p>
                                        <p class="mt-1 whitespace-pre-line">{{ $reply->body }}</p>
                                    </div>
                                @endforeach
                                @auth
                                    <form method="post" action="{{ route('product-comments.store', $product) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                                        @csrf
                                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                        <input type="hidden" name="rating" value="{{ $comment->rating }}">
                                        <input class="rounded-sm border border-slate-300 px-3 py-2 text-xs" name="body" maxlength="2000" placeholder="回复该评论" required>
                                        <button class="rounded-sm border border-slate-300 px-3 py-2 text-xs hover:bg-slate-50" type="submit">回复</button>
                                    </form>
                                @endauth
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="px-4 py-8 text-sm text-slate-600">暂无评论。</p>
                @endforelse
            </div>
            @auth
                <form method="post" action="{{ route('product-comments.store', $product) }}" enctype="multipart/form-data" class="space-y-3 border-t border-slate-200 px-4 py-4 text-sm">
                    @csrf
                    <label class="block">
                        <span class="font-medium">评分</span>
                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2" name="rating" required>
                            @for($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}">{{ $i }} 星</option>
                            @endfor
                        </select>
                    </label>
                    <label class="block">
                        <span class="font-medium">评论</span>
                        <textarea class="mt-1 min-h-28 w-full rounded-sm border border-slate-300 px-3 py-2" name="body" maxlength="2000" required>{{ old('body') }}</textarea>
                    </label>
                    <label class="block">
                        <span class="font-medium">图片（最多 3 张）</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2" type="file" name="images[]" accept="image/*" multiple>
                    </label>
                    <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">发布评论</button>
                </form>
            @else
                <div class="border-t border-slate-200 px-4 py-4 text-sm text-slate-600">
                    <a class="text-blue-700 hover:text-blue-900" href="{{ route('login') }}">登录</a> 后可评论。
                </div>
            @endauth
        @else
            <p class="px-4 py-8 text-sm text-slate-600">该商品暂未开启评论。</p>
        @endif
    </section>
</x-layouts.app>
