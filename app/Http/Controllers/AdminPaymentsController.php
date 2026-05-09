<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminPaymentsController extends Controller
{
    public function index()
    {
        try {
            $payments = Payment::with(['user'])->orderBy('id', 'DESC')->paginate(10);

            if ($payments->isEmpty()) {
                return response()->json(['message' => 'No payments found'], 404);
            }

            return response()->json($payments, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $payment = Payment::find($id);

            if (!$payment) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $payment->delete();

            return response()->json(['message' => 'Payment deleted successfully'], 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

}
