<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DanaQrisGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DanaNotificationController extends Controller
{
    public function __invoke(Request $request, DanaQrisGateway $dana): JsonResponse
    {
        $timestamp = (string) $request->header('X-TIMESTAMP');
        $signature = (string) $request->header('X-SIGNATURE');
        if (! $dana->verifyNotification($request->getContent(), $timestamp, $signature)) {
            return response()->json(['responseCode' => '4015600', 'responseMessage' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'originalPartnerReferenceNo' => ['required', 'string', 'max:64'],
            'originalReferenceNo' => ['required', 'string', 'max:64'],
            'latestTransactionStatus' => ['required', 'string', 'max:2'],
        ]);
        $order = Order::where('order_number', $data['originalPartnerReferenceNo'])->first();

        if ($order && $data['latestTransactionStatus'] === '00') {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
                'payment_reference' => $data['originalReferenceNo'],
                'paid_at' => now(),
            ]);
        } elseif ($order && $data['latestTransactionStatus'] === '05') {
            $order->update(['payment_status' => 'expired']);
        }

        return response()->json(['responseCode' => '2005600', 'responseMessage' => 'Successful']);
    }
}
