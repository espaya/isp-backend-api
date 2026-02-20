<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * List all payments for the logged-in user
     */
    public function index()
    {
        $user = Auth::user();
        try {

            $payments = Payment::where('user_id', $user->id)->orderBy('id', 'DESC')->paginate(20);

            if ($payments->isEmpty()) {
                return response()->json(['message' => 'No payments found'], 404);
            }

            return response()->json($payments, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Show a specific payment
     */
    public function show($id)
    {
        $payment = Payment::findOrFail($id);

        // Optional: check ownership
        if ($payment->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($payment);
    }

    /**
     * Create a new payment
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'method' => 'required|string',
            'status' => 'required|string', // pending, completed, failed
        ]);

        $payment = Payment::create([
            'user_id' => Auth::id(),
            'amount' => $request->amount,
            'method' => $request->method,
            'status' => $request->status,
            'reference' => $request->reference ?? null, // optional
        ]);

        return response()->json($payment, 201);
    }

    /**
     * Update payment status
     */
    public function updateStatus(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        $request->validate([
            'status' => 'required|string',
        ]);

        $payment->status = $request->status;
        $payment->save();

        return response()->json($payment);
    }


}
