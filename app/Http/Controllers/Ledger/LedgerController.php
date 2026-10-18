<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Ledger\SummarizeLedgerPeriod;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\IndexLedgerRequest;
use App\Models\LedgerEntry;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    /**
     * Show the ledger transactions for a period, with its opening and
     * closing balance.
     */
    public function index(IndexLedgerRequest $request, SummarizeLedgerPeriod $summarizeLedgerPeriod): Response
    {
        $filters = $request->filters();

        $entries = LedgerEntry::query()
            ->with(['createdBy:id,name', 'reversedBy:id,reverses_entry_id'])
            ->whereBetween('entry_date', [$filters['from'], $filters['to']])
            ->when($filters['type'], fn ($query, LedgerEntryType $type) => $query->where('type', $type))
            ->when($filters['direction'], fn ($query, LedgerDirection $direction) => $query->where('direction', $direction))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (LedgerEntry $entry) => [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date->toDateString(),
                'type' => $entry->type->value,
                'type_label' => $entry->type->label(),
                'direction' => $entry->direction->value,
                'amount' => $entry->amount,
                'signed_amount' => $entry->signedAmount(),
                'reason' => $entry->reason,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reverses_entry_id' => $entry->reverses_entry_id,
                'reversed_by_entry_id' => $entry->reversedBy?->id,
                'created_by' => $entry->createdBy?->name,
                'created_at' => $entry->created_at->toIso8601String(),
            ]);

        return Inertia::render('ledger/index', [
            'entries' => $entries,
            'filters' => [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'type' => $filters['type']?->value,
                'direction' => $filters['direction']?->value,
            ],
            'summary' => $summarizeLedgerPeriod->handle($filters['from'], $filters['to']),
            'types' => LedgerEntryType::options(),
            'directions' => LedgerDirection::options(),
            'can' => [
                'adjust' => $request->user()->can('adjust', LedgerEntry::class),
            ],
        ]);
    }
}
