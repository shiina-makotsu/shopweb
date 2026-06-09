<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function show(Order $order): StreamedResponse|Response
    {
        abort_if(blank($order->payment_proof_path), 404);

        $disk = Storage::disk('payment_proofs');

        abort_unless($disk->exists($order->payment_proof_path), 404);

        return $disk->response($order->payment_proof_path);
    }
}
