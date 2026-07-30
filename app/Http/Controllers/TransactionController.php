<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    // Initiate purchase request
    public function initiate(Request $request, Listing $listing)
    {
        // Prevent sellers from buying their own items
        if ($listing->user_id === Auth::id()) {
            return back()->with('error', 'You cannot purchase your own listing.');
        }

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => Auth::id(),
            'seller_id' => $listing->user_id,
            'amount' => $listing->price,
            'status' => 'initiated',
        ]);

        // Reserve the listing
        $listing->update(['status' => 'pending']);

        // Record Audit Log
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'TRANSACTION_INITIATED',
            'entity_type' => Transaction::class,
            'entity_id' => $transaction->id,
            'payload' => $transaction->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Transaction initiated!');
    }

    // Complete transaction
    public function complete(Request $request, Transaction $transaction)
    {
        // Only buyer or seller involved can mark complete
        if (!in_array(Auth::id(), [$transaction->buyer_id, $transaction->seller_id])) {
            abort(403);
        }

        $transaction->update(['status' => 'completed']);
        $transaction->listing->update(['status' => 'sold']);

        // Record Audit Log
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'TRANSACTION_COMPLETED',
            'entity_type' => Transaction::class,
            'entity_id' => $transaction->id,
            'payload' => ['status' => 'completed'],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Transaction completed!');
    }
}