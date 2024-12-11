<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use App\Models\Request as RequestModel;

class DepositController extends Controller
{
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $validatedData = $request->validate([
            'pay-method' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string',
        ]);

        $payMethod = $validatedData['pay-method'];
        $amount = round($validatedData['amount'], 2);
        $currency = $validatedData['currency'];

        try {

            switch ($payMethod) {
                case 'easymoney':

                    $data = [
                        'amount' => $amount,
                        'currency' => $currency,
                    ];
                    // Realizar la solicitud POST a la API de EasyMoney
                    $response = Http::post('http://localhost:3000/process', $data);

                    // Validar el código de respuesta
                    if ($response->successful()) {
                        Transaction::insert([
                            'pay_method' => $payMethod,
                            'amount' => $amount,
                            'currency' => $currency,
                            'status' => 'pending',
                        ]);
                        return response()->json(['message' => 'Pago procesado con exito', 'data' => $response->successful()], 200);
                    }else {
                        return response()->json(['message' => 'Error al procesar el pago con EasyMoney', 'data' => $response->json()], 500);
                    }

                case 'superwalletz':
                    $validated = $request->validate([
                        'amount' => 'required|numeric',
                        'currency' => 'required|string',
                        'callback_url' => 'url',
                    ]);

                    // Guardar la petición
                    RequestModel::create([
                        'request_type' => 'payment',
                        'data' => json_encode($validated),
                    ]);

                    // Realizar la llamada a la API
                    $response = Http::post('http://localhost:3003/pay', $validated);

                    if ($response->successful()) {
                        $data = $response->json();

                        // Guardar la transacción
                        $transaction = Transaction::create([
                            'pay_method' => $payMethod,
                            'amount' => $validated['amount'],
                            'currency' => $validated['currency'],
                            'transaction_id' => $data['transaction_id'],
                            'status' => 'success',
                        ]);
                        return response()->json($transaction);
                    }else {
                        // Manejar el error
                        return response()->json(['error' => 'Payment failed'], 400);
                    }

            }
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Se produjo un error: ' . $e->getMessage()], 500);
        }
    }

    public function webhook(Request $request){
        // Guardar el webhook
        RequestModel::create([
            'request_type' => 'webhook',
            'data' => json_encode($request->all()),
        ]);

        // Procesar la confirmación de pago
        $transactionId = $request->input('transaction_id');
        $status = $request->input('status');

        // Actualizar el estado de la transacción
        $transaction = Transaction::where('transaction_id', $transactionId)->first();
        if ($transaction) {
            $transaction->status = $status;
            $transaction->save();
        }

        return response()->json(['message' => 'Webhook received']);
    }
}