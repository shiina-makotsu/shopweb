<?php

namespace App\Http\Controllers;

use App\Models\PriceVoteOption;
use App\Models\Product;
use App\Models\ProductIntentVote;
use App\Models\ProductPriceVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoteController extends Controller
{
    public function intent(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->allowsVoting(), 404);

        $data = $request->validate([
            'intent' => ['required', Rule::in([
                ProductIntentVote::INTENT_WANT,
                ProductIntentVote::INTENT_CONSIDERING,
                ProductIntentVote::INTENT_NOT_NOW,
            ])],
        ]);

        ProductIntentVote::query()->updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            ['intent' => $data['intent']],
        );

        return back()->with('status', '购买意愿已记录。');
    }

    public function price(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->allowsVoting(), 404);

        $data = $request->validate([
            'price_vote_option_id' => ['required', 'exists:price_vote_options,id'],
        ]);

        $option = PriceVoteOption::query()
            ->whereBelongsTo($product)
            ->active()
            ->findOrFail($data['price_vote_option_id']);

        ProductPriceVote::query()->updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            ['price_vote_option_id' => $option->id],
        );

        return back()->with('status', '价格区间投票已记录。');
    }
}
