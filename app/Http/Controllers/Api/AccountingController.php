<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function journal(Request $request): JsonResponse
    {
        $entries = JournalEntry::with('details.account')
            ->when($request->query('type'), fn ($query, string $type) => $query->where('type', $type))
            ->orderByDesc('date')
            ->limit(100)
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function chartOfAccounts(): JsonResponse
    {
        return response()->json(['data' => ChartOfAccount::all()]);
    }
}
