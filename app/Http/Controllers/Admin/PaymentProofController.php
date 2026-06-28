<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentProofStorage;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function show(Order $order, PaymentProofStorage $proofStorage): StreamedResponse|Response
    {
        abort_if(blank($order->payment_proof_path), 404);

        abort_unless($proofStorage->exists($order->payment_proof_path), 404);

        return $proofStorage->response($order->payment_proof_path);
    }
}
